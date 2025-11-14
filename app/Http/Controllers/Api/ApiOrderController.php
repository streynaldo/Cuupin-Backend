<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Order;
use App\Models\Bakery;
use App\Models\Product;
use App\Models\OrderItems;
use Illuminate\Support\Str;
use App\Models\BakeryWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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

        $query = Order::where('user_id', $user->id)
            ->with(['items', 'bakery'])
            ->orderByDesc('created_at')
            ->get();

        // if ($withItems) {
        //     $query->with(['items' => fn($q) => $q->orderBy('id')]);
        // }

        return response()->json($query);
    }

    public function getAllOrderByBakeryId(Request $request, string $bakeryId){
        $user = $request->user(); // Sanctum

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $bakery = Bakery::findOrFail($bakeryId);

        $query = Order::where('bakery_id', $bakery->id)
            ->with(['items', 'bakery', 'user' => fn($q) => $q->select('id', 'name')])
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

        $queueCheck = Order::where('status', 'ONPROGRESS')->count();
        $waiting = $queueCheck >= 5;

        $referenceId = 'CPN-' . Str::upper(Str::random(5));
        while (Order::where('reference_id', $referenceId)->exists()) {
            $referenceId = 'CPN-' . Str::upper(Str::random(5));
        }

        $order = Order::create([
            'user_id' => $user->id,
            'bakery_id' => $validated['bakery_id'],
            'total_purchased_price' => 0,
            'total_refunded_price' => 0,
            'reference_id' => $referenceId,
            'expired_at' => now()->addMinutes(10),
            'status' => $waiting ? 'WAITING' : 'ONPROGRESS'
        ]);

        $itemsPayload = collect($validated['items'])->map(fn($i) => [
            'product_id' => $i['product_id'],
            'quantity' => $i['quantity'],
            'status' => 'WAITING',
        ])->all();

        // $order->items()->createMany($itemsPayload);
        $data = Order::findOrFail($order->id);
        // dd($data);
        foreach ($itemsPayload as $item) {
            $product = Product::findOrFail($item['product_id']);
            $item = OrderItems::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'subtotal_price' => ($product->price * $item['quantity']),
                'status' => $item['status'],
            ]);
            $data->total_purchased_price += $item->subtotal_price;
        }
        $data->save();
        // dd($data);


        return response()->json([
            'message' => 'Order created',
            'order' => $data,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $refId)
    {
        $user = $request->user();
        $order = Order::where('reference_id', $refId)->with('items')->first();
        if ($order->user_id == $user->id) {
            return response()->json($order);
        } else {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
    }

    public function showByBakeryId(Request $request, string $bakeryId, string $id){
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $order = Order::with(['items.product', 'user' => fn($q) => $q->select('id', 'name')])->findOrFail($id);
        if($order->bakery_id != $bakeryId){
            return response()->json(['message' => 'This bakery cant access the order'], 401);
        }

        return response()->json($order, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $order = Order::with('items')->where('reference_id', $id)->first();

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($order->status == 'CONFIRMED') {
            $order->status = 'COMPLETED';
            $order->save();

            $wallet = BakeryWallet::where('bakery_id', $order->bakery_id)->first();
            $wallet->total_earned = $order->total_purchased_price - $order->total_refunded_price;
            $wallet->total_wallet = $order->total_purchased_price - $order->total_refunded_price;
            $wallet->save();

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
            $totalRefundedPrice = 0;
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
                                $totalRefundedPrice += ($item->quantity - $updatedItems['quantity']) * $product->discount_price;
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
            $order->total_refunded_price = $totalRefundedPrice;

            if ($canceled) {
                $order->status = 'CANCELLED';
            } else {
                $order->status = 'CONFIRMED';
            }
            $order->save();

            $waitingOrder = Order::where('status', 'WAITING')
                ->oldest()   // default: created_at ASC
                ->first();
            
            $waitingOrder->status = 'ONPROGRESS';
            $waitingOrder->save();
        }

        return response()->json([
            'message' => 'Status Changed',
            'order' => $order
        ], 200);
    }

    public function cancellation(Request $request,string $id)
    {
        $user = $request->user();
        $order = Order::findOrFail($id);
        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if ($order->status == 'ONPROGRESS' && $order->expired_at > now()) {
            $order->status == 'CANCELLED';
            $order->save();
        } else {
            return response()->json([
                'message' => 'Cannot cancel the order'
            ], 402);
        }

        return response()->json([
            'message' => 'Order Cancelled',
            'order' => $order
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
