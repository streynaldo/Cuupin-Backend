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
                'data' => [
                    'bakery' => $bakery,
                    'wallet' => $wallet
                ],
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

        return response()->json([
            'success' => true,
            'message' => 'Bakery list retrieved successfully',
            'data'    => $bakery
        ], 200);
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
     * PUT /bakeries/{id}  (butuh login + ability + tanpa file)
     */

    public function update(Request $request, $id)
    {
        $bakery = Bakery::find($id);
        if (! $bakery) return response()->json(['message' => 'Bakery not found'], 404);
        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:150'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'address'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude'     => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'contact_info' => ['sometimes', 'nullable', 'string', 'max:100'],
            'discount_status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $bakery->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Bakery info updated',
            'data'    => $bakery->fresh(),
        ], 200);
    }

    // UPDATE LOGO (khusus file)
    public function updateLogo(Request $request, $id)
    {
        $bakery = Bakery::find($id);
        if (! $bakery) return response()->json(['message' => 'Bakery not found'], 404);
        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $request->validate([
            'logo_url' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2004'],
        ]);

        // hapus lama kalau ada
        if (!empty($bakery->logo_url)) {
            $old = ltrim(str_replace('/storage/', '', parse_url($bakery->logo_url, PHP_URL_PATH) ?? ''), '/');
            if ($old !== '') Storage::disk('public')->delete($old);
        }

        $path = $request->file('logo_url')->store('bakery_logos', 'public');
        $logoUrl = url(Storage::url($path));

        $bakery->update(['logo_url' => $logoUrl]);

        return response()->json([
            'success' => true,
            'message' => 'Bakery logo updated',
            'data'    => $bakery->fresh(),
        ], 200);
    }

    // UPDATE BANNER (khusus file)
    public function updateBanner(Request $request, $id)
    {
        $bakery = Bakery::find($id);
        if (! $bakery) return response()->json(['message' => 'Bakery not found'], 404);
        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $request->validate([
            'banner_url' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4144'],
        ]);

        // hapus lama kalau ada
        if (!empty($bakery->banner_url)) {
            $old = ltrim(str_replace('/storage/', '', parse_url($bakery->banner_url, PHP_URL_PATH) ?? ''), '/');
            if ($old !== '') Storage::disk('public')->delete($old);
        }

        $path = $request->file('banner_url')->store('bakery_banners', 'public');
        $bannerUrl = url(Storage::url($path));

        $bakery->update(['banner_url' => $bannerUrl]);

        return response()->json([
            'success' => true,
            'message' => 'Bakery banner updated',
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
