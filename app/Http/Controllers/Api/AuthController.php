<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Bakery;
use App\Models\DeviceToken;
use App\Models\BakeryWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['nullable', 'in:admin,owner,customer'],
            'bakery_name'         => ['required_if:role,owner', 'string', 'max:150'],
            'bakery_contact_info' => ['nullable', 'string', 'max:100'],
        ]);

        // bikin semuanya dalam 1 transaksi biar atomic
        return DB::transaction(function () use ($data) {
            // 1) Buat user
            $role = $data['role'] ?? 'customer';
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'role'     => $role,
            ]);
            $bakery = null;
            $wallet = null;
            // 2) Kalau owner → auto buat bakery & wallet
            if ($role === 'owner') {
                $bakery = Bakery::create([
                    'user_id'        => $user->id,
                    'name'           => $data['bakery_name'],
                    'description'    => $data['bakery_description'] ?? null,
                    'address'        => $data['bakery_address'] ?? null,
                    'contact_info'   => $data['bakery_contact_info'] ?? null,
                    'discount_status' => 'inactive',
                    'is_active'      => false,
                ]);

                $wallet = BakeryWallet::create([
                    'bakery_id'       => $bakery->id,
                    'total_wallet'    => 0,
                    'total_earned'    => 0,
                    'total_withdrawn' => 0,
                    'no_rekening'     => null,
                ]);
            }
            // 3) Generate token + abilities
            $abilities = $this->abilitiesForRole($user->role);
            $token = $user->createToken('api', $abilities)->plainTextToken;
            // 4) Return response
            if ($role === 'owner') {
                $payload = [
                    'success' => true,
                    'message' => 'User and Bakery created successfully',
                    'token' => $token,
                    'data'  => [
                        'user'      => $user,
                        'role'      => $user->role,
                        'abilities' => $abilities,
                        'bakery'    => $bakery,
                        'wallet'    => $wallet,
                    ],
                ];
            } else {
                $payload = [
                    'success' => true,
                    'message' => 'User created successfully',
                    'token' => $token,
                    'data'  => [
                        'user'      => $user,
                        'role'      => $user->role,
                        'abilities' => $abilities,
                    ],
                ];
            }
            return response()->json($payload, 201);
        });
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Tentukan abilities berdasarkan role user
        $abilities = $this->abilitiesForRole($user->role);

        // Buat token dengan abilities tsb
        $token = $user->createToken('api', $abilities)->plainTextToken;

        // Simpan device token jika dikirim dari client (multi-device support)
        if (! empty($data['device_token'])) {
            try {
                DeviceToken::updateOrCreate(
                    ['token' => $data['device_token']],
                    [
                        'user_id'      => $user->id,
                        'platform'     => $data['platform'] ?? 'ios',
                        'device_name'  => $data['device_name'] ?? null,
                        'last_seen_at' => Carbon::now(),
                    ]
                );
            } catch (\Throwable $e) {
                // jangan gagalkan login hanya karena penyimpanan device token gagal
                Log::warning('Failed to save device token on login: ' . $e->getMessage());
            }
        }

        return response()->json([
            'token'     => $token,
            'user'      => $user,
            'role'      => $user->role,
            'abilities' => $abilities,
        ], 200);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:100'],

            // email opsional, tapi kalau diisi harus unik (kecuali email user sendiri)
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            // hanya wajib kalau mau ganti password
            'current_password' => ['required_with:password', 'current_password'],

            'password' => [
                'sometimes',
                'confirmed',
                'min:8',
            ],
        ]);

        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = $data['name'];
        }

        if (array_key_exists('email', $data)) {
            $updates['email'] = $data['email'];
        }

        if (array_key_exists('password', $data)) {
            $updates['password'] = Hash::make($data['password']);
        }

        if (empty($updates)) {
            return response()->json([
                'success' => true,
                'message' => 'No changes',
                'data'    => $user->only(['id', 'name', 'email', 'role']),
            ], 200);
        }

        $user->fill($updates)->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => $user->fresh()->only(['id', 'name', 'email', 'role']),
        ], 200);
    }

    public function logout(Request $request)
    {
        // Hapus personal access token (sanctum)
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        // Jika device_token dikirim, hapus token device itu saja
        if ($request->filled('device_token')) {
            DeviceToken::where('token', $request->device_token)
                ->where('user_id', $request->user()->id)
                ->delete();
        }
        return response()->json(['message' => 'Successfully logged out'], 200);
    }

    public function logoutAllDevices(Request $request)
    {
        // Kalau mau logout dari SEMUA device/token:
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Successfully logged out from all devices'], 200);
    }

    // tentukan abilities berdasarkan role
    private function abilitiesForRole(?string $role): array
    {
        $map = config('sanctum-abilities');              // ambil dari config
        return $map[$role] ?? $map['default'];           // fallback aman
    }
}
