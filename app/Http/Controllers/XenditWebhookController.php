<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\Bakery;
use App\Models\Payment;
use App\Models\DeviceToken;
use App\Models\BakeryWallet;
use Illuminate\Http\Request;
use App\Jobs\ExpirePaidOrder;
use App\Models\WalletTransaction;
use function Laravel\Prompts\error;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Cache;
use App\Services\Firebase\FcmV1Service;

class XenditWebhookController extends Controller
{
    public function handle(Request $r)
    {
        // 1) Verifikasi token
        if ($r->header('x-callback-token') !== config('services.xendit.callback_token')) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        // 2) Ambil event & data
        $event = (string) ($r->input('event') ?? $r->input('type') ?? '');
        $data  = (array) $r->input('data', []);

        // (opsional) log sekilas
        Log::info('xendit.webhook.received', ['event' => $event, 'status' => $data['status'] ?? null]);

        // 3) Idempotensi sederhana
        $key = implode(':', [
            'xendit',
            $event ?: 'unknown',
            $data['payment_session_id'] ?? $data['payment_request_id'] ?? $data['id'] ?? sha1($r->getContent()),
        ]);
        if (!Cache::add($key, 1, now()->addMinutes(30))) {
            // return response()->json(['ok' => true]); // duplikat
            // dd('here');
        }

        // 4) Tangani event payment_session.completed (persis)
        if ($event === 'payment_session.completed') {
            $referenceId = $data['reference_id'] ?? null;
            $status      = strtoupper((string) ($data['status'] ?? ''));

            if (!$referenceId) {
                Log::warning('[PAYMENT SESSION COMPLETED] No reference_id in payload', ['data' => $data]);
                return response()->json(['status' => 'no reference_id'], 402);
            }

            $order = Order::with(['bakery', 'user'])->where('reference_id', $referenceId)->first();
            if (!$order) {
                Log::warning('[PAYMENT SESSION COMPLETED] Order not found', ['reference_id' => $referenceId]);
                return response()->json(['status' => 'order not found'], 402);
            }

            if (in_array($status, ['COMPLETED', 'SUCCEEDED', 'PAID', 'CAPTURED'])) {
                $order->status = 'PAID';
                $order->expired_at = now()->addMinutes(3);
                $order->payment_request_id = $data['payment_request_id'];
                $order->save();

                ExpirePaidOrder::dispatch($order->reference_id)
                    ->delay($order->expired_at);

                Log::info('[PAYMENT SESSION COMPLETED] Order marked PAID and ExpirePaidOrder job dispatched', [
                    'order_id' => $order->id,
                    'reference_id' => $referenceId,
                    'expired_at' => $order->expired_at->toDateTimeString()
                ]);
            } else {
                Log::info('[PAYMENT SESSION COMPLETED] Payment completed event with non-success status', ['status' => $status]);
            }

            try {
                $user = User::find($order->user_id);;
                if ($user) {
                    $tokens = DeviceToken::where('user_id', $user->id)
                        ->pluck('token')
                        ->filter(fn($t) => !empty($t))
                        ->unique()
                        ->toArray();
                    if (!empty($tokens)) {
                        $fcm = app(FcmV1Service::class);

                        $title = "Order Placed";
                        $body  = "Please wait for bakery to confirm!";

                        $notification = ['title' => $title, 'body' => $body];
                        $payloadData = [
                            'type' => 'new_order',
                            'user_id' => (string) $user->id,
                            'amount' => (string) $data['amount'],
                            'transaction_ref' => (string) $data['reference_id'],
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
                Log::error('Failed to send success payment notification', ['error' => $e->getMessage()]);
            }

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

                        $title = "Order received";
                        $body  = "New order received! Order #" . $order->reference_id . " from " . $order->user->name . " has been paid";

                        $notification = ['title' => $title, 'body' => $body];
                        $payloadData = [
                            'type' => 'new_order',
                            'user_id' => (string) $user->id,
                            'amount' => (string) $data['amount'],
                            'transaction_ref' => (string) $data['reference_id'],
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
                Log::error('Failed to send success payment notification', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'status' => $status,
                'order' => $order
            ]);
        }

        if ($event === 'payment_session.expired') {
            $referenceId = $data['reference_id'] ?? null;
            $status      = strtoupper((string) ($data['status'] ?? ''));

            if (!$referenceId) {
                Log::warning('[PAYMENT SESSION EXPIRED] No reference_id in payload', ['data' => $data]);
                return response()->json(['status' => 'no reference_id'], 402);
            }

            $order = Order::where('reference_id', $referenceId)->first();
            if (!$order) {
                Log::warning('[PAYMENT SESSION EXPIRED] Order not found', ['reference_id' => $referenceId]);
                return response()->json(['status' => 'order not found'], 402);
            }

            $order->status = 'CANCELLED';
            $order->save();

            try {
                $user = User::find( $order->user_id);
                if ($user) {
                    $tokens = DeviceToken::where('user_id', $user->id)
                        ->pluck('token')
                        ->filter(fn($t) => !empty($t))
                        ->unique()
                        ->toArray();
                    if (!empty($tokens)) {
                        $fcm = app(FcmV1Service::class);

                        $title = "Order Failed";
                        $body  = "Your order #" . $order->reference_id . " could not be processed. Please try again or contact support.";

                        $notification = ['title' => $title, 'body' => $body];
                        $payloadData = [
                            'type' => 'cancelled_order',
                            'user_id' => (string) $user->id,
                            'amount' => (string) $data['amount'],
                            'transaction_ref' => (string) $data['reference_id'],
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
                Log::error('Failed to send cancel notification', ['error' => $e->getMessage()]);
            }

            return response()->json(['status' => 'Order Cancelled', 'order' => $order], 200);
        }

        // 5) Event lain (refund / payout / dsb) tinggal tambahkan disini
        if (str_starts_with($event, 'refund.succeeded')) {
            $referenceId = $data['reference_id'] ?? null;
            $status      = strtoupper((string) ($data['status'] ?? ''));

            $order = Order::where('reference_id', $referenceId)->first();
            if (!$order) {
                Log::warning('[REFUND SUCCEEDED] Order not found', ['reference_id' => $referenceId]);
                return response()->json(['status' => 'order not found'], 402);
            }

            if (in_array($status, ['COMPLETED', 'SUCCEEDED', 'PAID', 'CAPTURED'])) {
                if ($order->total_purchased_price == $order->total_refunded_price) {
                    $order->status = 'CANCELLED';
                    try {
                        $user = User::find( $order->user_id);
                        if ($user) {
                            $tokens = DeviceToken::where('user_id', $user->id)
                                ->pluck('token')
                                ->filter(fn($t) => !empty($t))
                                ->unique()
                                ->toArray();
                            if (!empty($tokens)) {
                                $fcm = app(FcmV1Service::class);

                                $title = "Order Refunded";
                                $body  = "Refund processed!, Order #" . $order->reference_id .  " has been fully refunded for Rp " . number_format($data['amount'], 0, ',', '.') . ".";

                                $notification = ['title' => $title, 'body' => $body];
                                $payloadData = [
                                    'type' => 'refund_succeeded',
                                    'user_id' => (string) $user->id,
                                    'amount' => (string) $data['amount'],
                                    'transaction_ref' => (string) $data['reference_id'],
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
                        Log::error('Failed to refund notification', ['error' => $e->getMessage()]);
                    }
                } elseif ($order->total_refunded_price < $order->total_purchased_price) {
                    $order->status = 'CONFIRMED';

                    try {
                        $user = User::where('id', $order->user_id)->get();
                        if ($user) {
                            $tokens = DeviceToken::where('user_id', $user->id)
                                ->pluck('token')
                                ->filter(fn($t) => !empty($t))
                                ->unique()
                                ->toArray();
                            if (!empty($tokens)) {
                                $fcm = app(FcmV1Service::class);

                                $title = "Order Refunded";
                                $body  = "Refund processed!, Order #" . $order->reference_id .  " has been partialy refunded for Rp " . number_format($data['amount'], 0, ',', '.') . ".";

                                $notification = ['title' => $title, 'body' => $body];
                                $payloadData = [
                                    'type' => 'refund_succeeded',
                                    'user_id' => (string) $user->id,
                                    'amount' => (string) $data['amount'],
                                    'transaction_ref' => (string) $data['reference_id'],
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
                        Log::error('Failed to refund notification', ['error' => $e->getMessage()]);
                    }
                }
                $order->save();
                Log::info('Order marked PAID', ['order_id' => $order->id, 'reference_id' => $referenceId]);
            } else {
                Log::info('Payment completed event with non-success status', ['status' => $status]);
            }

            return response()->json(['status' => $order->status, 'order' => $order], 200);
        }
        if (str_starts_with($event, 'payout.succeeded')) {
            $bakeryId = $data['metadata']['bakery_id'] ?? null;
            $bakery = Bakery::where('id', $bakeryId)->first();
            $bakeryWallet = BakeryWallet::where('bakery_id', $bakery->id)->first();

            $bakeryWallet->total_wallet = 0;
            $bakeryWallet->total_withdrawn += $data['amount'];
            $bakeryWallet->save();

            $walletTransaction = WalletTransaction::create([
                'bakery_wallet_id' => $bakeryWallet->id,
                'amount' => $data['amount'],
                'reference_id' => $data['reference_id']
            ]);

            // --- send push notif to bakery owner devices ---
            try {
                // determine owner user
                $owner = null;
                if (method_exists($bakery, 'user') && $bakery->relationLoaded('user')) {
                    $owner = $bakery->user;
                } elseif (method_exists($bakery, 'user')) {
                    $owner = $bakery->user()->first();
                } elseif (!empty($bakery->user_id)) {
                    $owner = User::find($bakery->user_id);
                }

                if ($owner) {
                    // get tokens
                    $tokens = DeviceToken::where('user_id', $owner->id)
                        ->pluck('token')
                        ->filter(fn($t) => !empty($t))
                        ->unique()
                        ->toArray();

                    if (!empty($tokens)) {
                        $fcm = app(FcmV1Service::class);

                        $title = "Successful Withdrawal";
                        $body  = "Withdrawal successful! You’ve withdrawn Rp " . number_format($data['amount'], 0, ',', '.') . " to your bank account.";

                        $notification = ['title' => $title, 'body' => $body];
                        $payloadData = [
                            'type' => 'payout_succeeded',
                            'bakery_id' => (string) $bakery->id,
                            'amount' => (string) $data['amount'],
                            'transaction_ref' => (string) $data['reference_id'],
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
                        Log::info('No device tokens found for bakery owner', ['owner_id' => $owner->id]);
                    }
                } else {
                    Log::warning('Bakery owner not found to notify', ['bakery_id' => $bakery->id]);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send payout notification', ['error' => $e->getMessage()]);
            }
            return response()->json(['status' => 'Payout Success', 'data' => $walletTransaction], 200);
        }
        if (str_starts_with($event, 'payout.failed')) {
            $bakeryId = $data['metadata']['bakery_id'] ?? null;
            $bakery = Bakery::where('id', $bakeryId)->first();
            $bakeryWallet = BakeryWallet::where('bakery_id', $bakery->id)->first();

            $bakeryWallet->total_wallet = $data['amount'];
            // $bakeryWallet->total_withdrawn += $data['amount'];
            $bakeryWallet->save();

            // $walletTransaction = WalletTransaction::create([
            //     'bakery_wallet_id' => $bakeryWallet->id,
            //     'amount' => $data['amount'],
            //     'reference_id' => $data['reference_id']
            // ]);
            return response()->json(['status' => 'Payout Failed', 'data' => $bakeryWallet], 200);
        }

        Log::info('Unhandled Xendit event', ['event' => $event]);
        return response()->json(['status' => 'Walawee']);
    }
}
