<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bakery;
use App\Models\BakeryWallet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class ApiBakeryController extends Controller
{
    /**
     * GET /bakeries
     */
    public function index()
    {
        $bakeries = Bakery::with('user:id,name,email')
            ->orderByDesc('id')
            ->get();

        // return response()->json($bakeries, 200);
        return response()->json([
            'success' => true,
            'message' => 'Bakery list retrieved successfully',
            'data'    => $bakeries
        ], 200);
    }

    /**
     * POST /bakeries  (butuh login + ability)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string'],
            'logo_url'     => ['nullable', 'url'],
            'banner_url'   => ['nullable', 'url'],
            'address'      => ['nullable', 'string', 'max:255'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'contact_info' => ['nullable', 'string', 'max:100'],
            'discount_status' => ['nullable', 'string', 'in:active,inactive'],
            'is_active'    => ['boolean'],
        ]);

        $data['user_id'] = $request->user()->id;

        try {
            $bakery = Bakery::create($data);
            $wallet = BakeryWallet::create([
                'bakery_id' => $bakery->id,
                'total_wallet' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'no_rekening' => null,
            ]);
            $bakery->load('user:id,name,email');
            return response()->json([
                'success' => true,
                'message' => 'Bakery & wallet created successfully',
                'data' => [$bakery, $wallet],
            ], 201);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Failed to create bakery'], 500);
        }
    }

    /**
     * GET /bakeries/{id}
     */
    public function show(int $id)
    {
        try {
            $bakery = Bakery::with('user:id,name,email')->findOrFail($id);
            return response()->json($bakery);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Bakery not found'], 404);
        }
    }

    public function getBakeryByUserId(Request $request)
    {
        $user = $request->user(); // Sanctum

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $bakery = Bakery::where('user_id', $user->id)->with('orders')->first();

        return response()->json($bakery, 200);
    }

    // app/Http/Controllers/Api/ApiBakeryController.php
    public function activate(Request $request, $id)
    {
        $bakery = Bakery::find($id);
        if (! $bakery) return response()->json(['message' => 'Bakery not found'], 404);

        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $bakery->update(['is_active' => true]);
        return response()->json([
            'success' => true,
            'message' => 'Bakery activated by vendor',
            'data'    => $bakery->fresh(['products'])
        ]);
    }

    public function deactivate(Request $request, $id)
    {
        $bakery = Bakery::find($id);
        if (! $bakery) return response()->json(['message' => 'Bakery not found'], 404);

        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $bakery->update(['is_active' => false]);
        return response()->json([
            'success' => true,
            'message' => 'Bakery deactivated by vendor',
            'data'    => $bakery->fresh(['products'])
        ]);
    }

    /**
     * PUT /bakeries/{id}  (butuh login + ability)
     */
    public function update(Request $request, $id)
    {
        $bakery = Bakery::find($id);
        if (! $bakery) {
            return response()->json(['message' => 'Bakery not found'], 404);
        }
        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $data = $request->validate([
            'name'              => ['sometimes', 'string', 'max:150'],
            'description'       => ['sometimes', 'nullable', 'string'],
            // ganti dari url → file image
            'logo_url'          => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'banner_url'        => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'address'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude'          => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'contact_info'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active'         => ['sometimes', 'boolean'],
        ]);

        // handle logo baru
        if ($request->hasFile('logo')) {
            if (!empty($bakery->logo_url)) {
                $old = ltrim(str_replace('/storage/', '', parse_url($bakery->logo_url, PHP_URL_PATH) ?? ''), '/');
                if ($old !== '') Storage::disk('public')->delete($old);
            }
            $path = $request->file('logo')->store('bakery_logos', 'public');
            $data['logo_url'] = url(Storage::url($path));
        }

        // handle banner baru
        if ($request->hasFile('banner')) {
            if (!empty($bakery->banner_url)) {
                $old = ltrim(str_replace('/storage/', '', parse_url($bakery->banner_url, PHP_URL_PATH) ?? ''), '/');
                if ($old !== '') Storage::disk('public')->delete($old);
            }
            $path = $request->file('banner')->store('bakery_banners', 'public');
            $data['banner_url'] = url(Storage::url($path));
        }

        // mapping field lain (yang optional)
        $bakery->update([
            'name'            => $data['name']            ?? $bakery->name,
            'description'     => array_key_exists('description', $data) ? $data['description'] : $bakery->description,
            'logo_url'        => array_key_exists('logo_url', $data)    ? $data['logo_url']    : $bakery->logo_url,
            'banner_url'      => array_key_exists('banner_url', $data)  ? $data['banner_url']  : $bakery->banner_url,
            'address'         => array_key_exists('address', $data)     ? $data['address']     : $bakery->address,
            'latitude'        => array_key_exists('latitude', $data)    ? $data['latitude']    : $bakery->latitude,
            'longitude'       => array_key_exists('longitude', $data)   ? $data['longitude']   : $bakery->longitude,
            'contact_info'    => array_key_exists('contact_info', $data) ? $data['contact_info'] : $bakery->contact_info,
            'discount_status' => array_key_exists('discount_status', $data) ? $data['discount_status'] : $bakery->discount_status,
            'is_active'       => array_key_exists('is_active', $data)   ? (bool)$data['is_active'] : $bakery->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bakery updated successfully',
            'data'    => $bakery->fresh(),
        ], 200);
    }

    /**
     * DELETE /bakeries/{id}  (butuh login + ability)
     */
    public function destroy(Request $request, $id)
    {
        $bakery = Bakery::findOrFail($id); // auto 404
        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $bakery->delete();

        // 200 dengan message, atau 204 tanpa body (pilih salah satu)
        return response()->json(['message' => 'Bakery deleted']);
        // return response()->noContent(); // <- alternatif 204
    }

    /** Helper: hanya admin atau owner bakery yang boleh write */
    private function authorizeOwnerOrAdmin(User $user, Bakery $bakery): void
    {
        if (! ($user->role === 'admin' || $bakery->user_id === $user->id)) {
            abort(403, 'Forbidden');
        }
    }
}
