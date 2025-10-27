<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApiOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // $validated = $request->validate([
        //     'user_id' => 'required|exists:users,id',
        //     'bakery_id' => 'required|exists:bakeries,id',

        //     'items' => 'required|array|min:1',
        //     'items.*.product_id' => 'required|exists:products,id', // sesuaikan table produk jika berbeda
        //     'items.*.quantity' => 'required|integer|min:1',
        //     'items.*.subtotal_price' => 'required|numeric|min:0',
        // ]);

        $validated = $request->all();

        // Hitung total dari items (subtotal_price sudah per-line)
        $computedTotal = round(
            collect($validated['items'])->sum(fn($i) => (float) $i['subtotal_price']),
            2
        );


        $order = Order::create([
            'user_id' => $validated['user_id'],
            'bakery_id' => $validated['bakery_id'],
            'total_purchased_price' => $computedTotal,
            'total_refunded_price' => 0,
        ]);

        $itemsPayload = collect($validated['items'])->map(fn($i) => [
            'product_id' => $i['product_id'],
            'quantity' => $i['quantity'],
            'subtotal_price' => $i['subtotal_price'],
            'status' => 'purchased',
        ])->all();

        $order->items()->createMany($itemsPayload);

        return response()->json([
            'message' => 'Order created',
            'data' => $order,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
}
