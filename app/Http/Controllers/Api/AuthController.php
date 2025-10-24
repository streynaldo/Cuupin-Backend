<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
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
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'] ?? 'customer',
        ]);

        // optional: langsung login pakai abilities sesuai role
        $abilities = $this->abilitiesForRole($user->role);
        $token = $user->createToken('api', $abilities)->plainTextToken;

        return response()->json([
            'token'     => $token,
            'user'      => $user,
            'role'      => $user->role,
            'abilities' => $abilities,
        ], 201);
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

        return response()->json([
            'token'     => $token,
            'user'      => $user,
            'role'      => $user->role,
            'abilities' => $abilities,
        ], 200);
    }

    public function logout(Request $request)
    {
        // user() mengembalikan instance App\Models\User untuk token yang dipakai
        $request->user()->currentAccessToken()?->delete();

        // Kalau mau logout dari SEMUA device/token:
        // $request->user()->tokens()->delete();

        return response()->json(['message' => 'Successfully logged out'], 200);
    }

    // tentukan abilities berdasarkan role
    private function abilitiesForRole(?string $role): array
    {
        $map = config('sanctum-abilities');              // ambil dari config
        return $map[$role] ?? $map['default'];           // fallback aman
    }
}
