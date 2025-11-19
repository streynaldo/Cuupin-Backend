<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Order;
use App\Models\Bakery;
use App\Models\Product;
use App\Jobs\ExpireOrder;
use App\Models\OrderItems;
use App\Models\DeviceToken;
use Illuminate\Support\Str;
use App\Models\BakeryWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\Firebase\FcmV1Service;

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
                'subtotal_price' => ($product->discount_price * $item['quantity']),
                'status' => $item['status'],
            ]);
            $data->total_purchased_price += $item->subtotal_price;
        }
        $data->save();

        ExpireOrder::dispatch($order->id)
            ->delay($order->expired_at);
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
        $order = Order::with(['items', 'user'])->where('reference_id', $id)->first();

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

            try {
                $owner = User::find($order->bakery->user_id);
                if ($owner) {
                    $tokens = DeviceToken::where('user_id', $owner->id)
                        ->pluck('token')
                        ->filter(fn($t) => !empty($t))
                        ->unique()
                        ->toArray();
                    if (!empty($tokens)) {
                        $fcm = app(FcmV1Service::class);

                        $title = "Order Completed";
                        $body  = "Order #" . $order->reference_id . " is complete. " . $order->user->name . " has picked up the order.";

                        $notification = ['title' => $title, 'body' => $body];
                        $payloadData = [
                            'type' => 'completed_order',
                            'user_id' => (string) $owner->id,
                            'amount' => (string) $order->total_purchased_price,
                            'transaction_ref' => (string) $order->reference_id,
                        ];

                        $results = $fcm->sendToTokens($tokens, $notification, $payloadData, app('log'));

                        // cleanup invalid tokens basic heuristic
                        foreach ($results as $token => $res) {
                            if (isset($res['status']) && in_array($res['status'], [400, 404, 410])) {
                                // try to detect not found / unregistered
                                $reason = $res['body']['error']['message'] ?? $res['body']['error']['status'] ?? null;
                                DeviceToken::where('token', $token)->delete();
                                Log::info('Deleted invalid device token', ['token' => $token, 'reason' => $reason]);
                            } elseif (isset($res['error']) && !isset($res['status'])) {
                                // network/other error - keep token and log
                                Log::warning('FCM send error (no status)', ['token' => $token, 'error' => $res['error']]);
                            }
                        }
                    } else {
                        Log::info('No device tokens found for user', ['user_id' => $user->id]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send confirmed order notification', ['error' => $e->getMessage()]);
            }

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
                                    'subtotal_price' => ($item->quantity - $updatedItems['quantity']) * ($item->subtotal_price/$item->quantity) ,
                                    'status' => 'REFUND',
                                ]);
                                $totalRefundedPrice += ($item->quantity - $updatedItems['quantity']) * ($item->subtotal_price/$item->quantity);
                                $item->quantity = $updatedItems['quantity'];
                                $item->status = 'PURCHASED';
                                $item->subtotal_price = $item->quantity * ($item->subtotal_price/$item->quantity);
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
                try {
                $user = User::find($order->user_id);
                if ($user) {
                    $tokens = DeviceToken::where('user_id', $user->id)
                        ->pluck('token')
                        ->filter(fn($t) => !empty($t))
                        ->unique()
                        ->toArray();
                    if (!empty($tokens)) {
                        $fcm = app(FcmV1Service::class);

                        $title = "Order Refunded";
                        $body  = "Your order is not available. Order #" . $order->reference_id . " has been refunded for Rp " . $order->total_refunded_price . ".";

                        $notification = ['title' => $title, 'body' => $body];
                        $payloadData = [
                            'type' => 'cancelled_order',
                            'user_id' => (string) $user->id,
                            'amount' => (string) $order->total_purchased_price,
                            'transaction_ref' => (string) $order->reference_id,
                        ];

                        $results = $fcm->sendToTokens($tokens, $notification, $payloadData, app('log'));

                        // cleanup invalid tokens basic heuristic
                        foreach ($results as $token => $res) {
                            if (isset($res['status']) && in_array($res['status'], [400, 404, 410])) {
                                // try to detect not found / unregistered
                                $reason = $res['body']['error']['message'] ?? $res['body']['error']['status'] ?? null;
                                DeviceToken::where('token', $token)->delete();
                                Log::info('Deleted invalid device token', ['token' => $token, 'reason' => $reason]);
                            } elseif (isset($res['error']) && !isset($res['status'])) {
                                // network/other error - keep token and log
                                Log::warning('FCM send error (no status)', ['token' => $token, 'error' => $res['error']]);
                            }
                        }
                    } else {
                        Log::info('No device tokens found for user', ['user_id' => $user->id]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send cancelled order notification', ['error' => $e->getMessage()]);
            }
            } else {
                $order->status = 'CONFIRMED';
                try {
                $user = User::find($order->user_id);
                if ($user) {
                    $tokens = DeviceToken::where('user_id', $user->id)
                        ->pluck('token')
                        ->filter(fn($t) => !empty($t))
                        ->unique()
                        ->toArray();
                    if (!empty($tokens)) {
                        $fcm = app(FcmV1Service::class);

                        $title = "Order Confirmed";
                        $body  = "Your order #" . $order->reference_id . "has been confirmed. We’re preparing it for you!";

                        $notification = ['title' => $title, 'body' => $body];
                        $payloadData = [
                            'type' => 'confirmed_order',
                            'user_id' => (string) $user->id,
                            'amount' => (string) $order->total_purchased_price,
                            'transaction_ref' => (string) $order->reference_id,
                        ];

                        $results = $fcm->sendToTokens($tokens, $notification, $payloadData, app('log'));

                        // cleanup invalid tokens basic heuristic
                        foreach ($results as $token => $res) {
                            if (isset($res['status']) && in_array($res['status'], [400, 404, 410])) {
                                // try to detect not found / unregistered
                                $reason = $res['body']['error']['message'] ?? $res['body']['error']['status'] ?? null;
                                DeviceToken::where('token', $token)->delete();
                                Log::info('Deleted invalid device token', ['token' => $token, 'reason' => $reason]);
                            } elseif (isset($res['error']) && !isset($res['status'])) {
                                // network/other error - keep token and log
                                Log::warning('FCM send error (no status)', ['token' => $token, 'error' => $res['error']]);
                            }
                        }
                    } else {
                        Log::info('No device tokens found for user', ['user_id' => $user->id]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send confirmed order notification', ['error' => $e->getMessage()]);
            }
            }
            $order->save();

            $waitingOrder = Order::where('status', 'WAITING')
                ->oldest()   // default: created_at ASC
                ->first();
            
                if($waitingOrder){
                    $waitingOrder->status = 'ONPROGRESS';
                    $waitingOrder->save();
                }

            try {
                $user = User::find($waitingOrder->user_id);
                if ($user) {
                    $tokens = DeviceToken::where('user_id', $user->id)
                        ->pluck('token')
                        ->filter(fn($t) => !empty($t))
                        ->unique()
                        ->toArray();
                    if (!empty($tokens)) {
                        $fcm = app(FcmV1Service::class);

                        $title = "⁠It’s Your Turn to Pay";
                        $body  = "It’s your turn to pay for order #". $order->reference_id .". Please proceed to payment.";

                        $notification = ['title' => $title, 'body' => $body];
                        $payloadData = [
                            'type' => 'inqueue_order',
                            'user_id' => (string) $user->id,
                            'amount' => (string) $order->total_purchased_price,
                            'transaction_ref' => (string) $order->reference_id,
                        ];

                        $results = $fcm->sendToTokens($tokens, $notification, $payloadData, app('log'));

                        // cleanup invalid tokens basic heuristic
                        foreach ($results as $token => $res) {
                            if (isset($res['status']) && in_array($res['status'], [400, 404, 410])) {
                                // try to detect not found / unregistered
                                $reason = $res['body']['error']['message'] ?? $res['body']['error']['status'] ?? null;
                                DeviceToken::where('token', $token)->delete();
                                Log::info('Deleted invalid device token', ['token' => $token, 'reason' => $reason]);
                            } elseif (isset($res['error']) && !isset($res['status'])) {
                                // network/other error - keep token and log
                                Log::warning('FCM send error (no status)', ['token' => $token, 'error' => $res['error']]);
                            }
                        }
                    } else {
                        Log::info('No device tokens found for user', ['user_id' => $user->id]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send in queue order notification', ['error' => $e->getMessage()]);
            }
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
            try {
                // $user = User::find($waitingOrder->user_id);
                if ($user) {
                    $tokens = DeviceToken::where('user_id', $user->id)
                        ->pluck('token')
                        ->filter(fn($t) => !empty($t))
                        ->unique()
                        ->toArray();
                    if (!empty($tokens)) {
                        $fcm = app(FcmV1Service::class);

                        $title = "Order Cancelled";
                        $body  = "Sorry, your payment time expired !";

                        $notification = ['title' => $title, 'body' => $body];
                        $payloadData = [
                            'type' => 'expired_order',
                            'user_id' => (string) $user->id,
                            'amount' => (string) $order->total_purchased_price,
                            'transaction_ref' => (string) $order->reference_id,
                        ];

                        $results = $fcm->sendToTokens($tokens, $notification, $payloadData, app('log'));

                        // cleanup invalid tokens basic heuristic
                        foreach ($results as $token => $res) {
                            if (isset($res['status']) && in_array($res['status'], [400, 404, 410])) {
                                // try to detect not found / unregistered
                                $reason = $res['body']['error']['message'] ?? $res['body']['error']['status'] ?? null;
                                DeviceToken::where('token', $token)->delete();
                                Log::info('Deleted invalid device token', ['token' => $token, 'reason' => $reason]);
                            } elseif (isset($res['error']) && !isset($res['status'])) {
                                // network/other error - keep token and log
                                Log::warning('FCM send error (no status)', ['token' => $token, 'error' => $res['error']]);
                            }
                        }
                    } else {
                        Log::info('No device tokens found for user', ['user_id' => $user->id]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send expired order notification', ['error' => $e->getMessage()]);
            }
            
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
