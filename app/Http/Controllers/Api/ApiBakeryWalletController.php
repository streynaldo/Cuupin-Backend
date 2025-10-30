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
            'wallet' => $wallet->only(['id', 'total_wallet', 'total_earned', 'total_withdrawn','no_rekening']),
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
            ->paginate($request->integer('per_page', 10));

        return response()->json($rows);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // Helper method to authorize user
    private function authorizeOwnerOrAdmin(User $user, Bakery $bakery): void
    {
        if ($user->role === 'admin') return;
        if ($bakery->user_id === $user->id) return;
        abort(403, 'Forbidden');
    }
}
