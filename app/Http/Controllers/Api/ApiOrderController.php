<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\OrderItems;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Bakery;
use App\Models\Product;

class ApiOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user(); // Sanctum
        // dd($user);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $withItems = filter_var($request->query('with_items', true), FILTER_VALIDATE_BOOLEAN);

        $query = Order::where('user_id', $user->id)
            ->with(['items', 'bakery'])
            ->orderByDesc('created_at')
            ->get();

        // if ($withItems) {
        //     $query->with(['items' => fn($q) => $q->orderBy('id')]);
        // }

        return response()->json($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bakery_id' => 'required|exists:bakeries,id',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id', // sesuaikan table produk jika berbeda
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // $validated = $request->all();
        $user = $request->user(); // Sanctum

        // Hitung total dari items (subtotal_price sudah per-line)
        // $computedTotal = round(
        //     collect($validated['items'])->sum(fn($i) => (float) $i['subtotal_price']),
        //     2
        // );


        $order = Order::create([
            'user_id' => $user->id,
            'bakery_id' => $validated['bakery_id'],
            'total_purchased_price' => 0,
            'total_refunded_price' => 0,
        ]);

        $itemsPayload = collect($validated['items'])->map(fn($i) => [
            'product_id' => $i['product_id'],
            'quantity' => $i['quantity'],
            'status' => 'WAITING',
        ])->all();

        // $order->items()->createMany($itemsPayload);
        foreach ($itemsPayload as $item) {
            $product = Product::findOrFail($item['product_id']);
            $item = OrderItems::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'subtotal_price' => ($product->price * $item['quantity']),
                'status' => $item['status'],
            ]);
            $order->total_purchased_price += $item->subtotal_price;
        }

        return response()->json([
            'message' => 'Order created',
            'order' => $order,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $order = Order::findOrFail($id)->with('items');
        if ($order->user_id == $user->id) {
            return response()->json($order);
        } else {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $order = Order::with('items')->findOrFail($id);
        if ($order->bakery->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($order->status == 'CONFIRMED') {
            $order->status == 'COMPLETED';
            $order->save();

            return response()->json(['message' => 'Order berhasil diambil', 'data' => $order]);
        } else {
            return response()->json(['message' => 'Wrong Order Status'], 402);
        }
    }

    public function confirmation(Request $request, string $id)
    {
        $user = $request->user();
        $order = Order::with(['items'])->findOrFail($id);
        if ($order->bakery->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // dd($request->all());

        $validated = $request->validate([
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'required|integer|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        if ($order->status == 'PAID') {
            $canceled = true;
            foreach ($order->items as $item) {
                if ($item->status == 'WAITING') {
                    // TAMBAH CHECKER KALO PRODUK TERNYATA SUDAH HABIS
                    $product = Product::findOrFail($item->product_id);
                    foreach ($validated['items'] as $updatedItems) {
                        // dd($updatedItems['quantity']);
                        if ($item->id == $updatedItems['id']) {
                            // $updatedItems['id'];
                            if ($updatedItems['quantity'] < $item->quantity) {
                                // dd('HERE');
                                OrderItems::create([
                                    'order_id' => $item->order_id,
                                    'product_id' => $item->product_id,
                                    'quantity' => $item->quantity - $updatedItems['quantity'],
                                    'subtotal_price' => ($item->quantity - $updatedItems['quantity']) * $product->discount_price,
                                    'status' => 'REFUND',
                                ]);
                                $item->quantity = $updatedItems['quantity'];
                                $item->status = 'PURCHASED';
                                $item->subtotal_price = $item->quantity * $product->discount_price;
                                $item->save();

                                if ($item->quantity > 0) {
                                    $canceled = false;
                                }
                            }
                            if ($item->quantity == $updatedItems['quantity']) {
                                $item->status = 'PURCHASED';
                                $item->save();

                                if ($item->quantity > 0) {
                                    $canceled = false;
                                }
                            }
                        }
                    }
                }
            }

            if ($canceled) {
                $order->status = 'CANCELED';
            } else {
                $order->status = 'CONFIRMED';
            }
            $order->save();
        }

        return response()->json([
            'message' => 'Status Changed',
            'order' => $order
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
