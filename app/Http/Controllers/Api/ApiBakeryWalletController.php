<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bakery;
use App\Models\BakeryWallet;
use App\Models\User;
use Illuminate\Http\Request;

class ApiBakeryWalletController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $bakery = Bakery::select('id', 'name', 'user_id')->find($id);
        if (! $bakery) return response()->json(['message' => 'Bakery not found'], 404);

        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $wallet = BakeryWallet::firstOrCreate(
            ['bakery_id' => $bakery->id],
            ['total_wallet' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
        );

        return response()->json([
            'bakery' => $bakery->only(['id', 'name']),
            'wallet' => $wallet,
        ]);
    }

    /**
     * GET /bakeries/{id}/wallet/transactions
     * List riwayat penarikan.
     */
    public function transactions(Request $request, $id)
    {
        $bakery = Bakery::select('id', 'user_id')->find($id);
        if (! $bakery) return response()->json(['message' => 'Bakery not found'], 404);

        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        $wallet = BakeryWallet::firstOrCreate(
            ['bakery_id' => $bakery->id],
            ['total_wallet' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
        );

        $rows = $wallet->transactions()->orderByDesc('id')
            ->get();

        return response()->json($rows);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // 1. Ambil bakery dulu
        $bakery = Bakery::select('id', 'name', 'user_id')->find($id);
        if (! $bakery) {
            return response()->json(['message' => 'Bakery not found'], 404);
        }

        // 2. Pastikan yang update itu owner bakery atau admin
        $this->authorizeOwnerOrAdmin($request->user(), $bakery);

        // 3. Pastikan wallet-nya ada (kalau belum, auto-bikin)
        $wallet = BakeryWallet::firstOrCreate(
            ['bakery_id' => $bakery->id],
            ['total_wallet' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
        );

        // 4. Validasi HANYA kolom bank info
        $data = $request->validate([
            'no_rekening' => ['sometimes', 'nullable', 'string', 'max:100'],
            'nama_bank'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'nama_pemilik' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);

        // 5. Update cuma field yang dikirim
        $wallet->update([
            'no_rekening' => array_key_exists('no_rekening', $data) ? $data['no_rekening'] : $wallet->no_rekening,
            'nama_bank'   => array_key_exists('nama_bank', $data)   ? $data['nama_bank']   : $wallet->nama_bank,
            'nama_pemilik' => array_key_exists('nama_pemilik', $data) ? $data['nama_pemilik'] : $wallet->nama_pemilik,
        ]);

        // 6. Return response
        return response()->json([
            'success' => true,
            'message' => 'Wallet bank info updated successfully',
            'data'    => [
                'bakery' => $bakery->only(['id', 'name']),
                'wallet' => $wallet->fresh(),
            ],
        ], 200);
    }

    // Helper method to authorize user
    private function authorizeOwnerOrAdmin(User $user, Bakery $bakery): void
    {
        if ($user->role === 'admin') return;
        if ($bakery->user_id === $user->id) return;
        abort(403, 'Forbidden');
    }
}
