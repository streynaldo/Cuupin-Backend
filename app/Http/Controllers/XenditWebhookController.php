<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Bakery;
use App\Models\BakeryWallet;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
                Log::warning('No reference_id in payload', ['data' => $data]);
                return response()->json(['status' => 'no reference_id'], 402);
            }

            $order = Order::where('reference_id', $referenceId)->first();
            if (!$order) {
                Log::warning('Order not found', ['reference_id' => $referenceId]);
                return response()->json(['status' => 'order not found'], 402);
            }

            if (in_array($status, ['COMPLETED', 'SUCCEEDED', 'PAID', 'CAPTURED'])) {
                $order->status = 'PAID';
                $order->expired_at = null;
                $order->save();
                Log::info('Order marked PAID', ['order_id' => $order->id, 'reference_id' => $referenceId]);
            } else {
                Log::info('Payment completed event with non-success status', ['status' => $status]);
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
                Log::warning('No reference_id in payload', ['data' => $data]);
                return response()->json(['status' => 'no reference_id'], 402);
            }

            $order = Order::where('reference_id', $referenceId)->first();
            if (!$order) {
                Log::warning('Order not found', ['reference_id' => $referenceId]);
                return response()->json(['status' => 'order not found'], 402);
            }

            $order->status = 'CANCELLED';
            $order->save();

            return response()->json(['status' => 'Order Cancelled', 'order' => $order], 200);
        }

        // 5) Event lain (refund / payout / dsb) tinggal tambahkan disini
        if (str_starts_with($event, 'refund.succeeded')) {
            $referenceId = $data['reference_id'] ?? null;
            $status      = strtoupper((string) ($data['status'] ?? ''));

            $order = Order::where('reference_id', $referenceId)->first();
            if (!$order) {
                Log::warning('Order not found', ['reference_id' => $referenceId]);
                return response()->json(['status' => 'order not found'], 402);
            }

            if (in_array($status, ['COMPLETED', 'SUCCEEDED', 'PAID', 'CAPTURED'])) {
                if ($order->total_purchased_price == $order->total_refunded_price) {
                    $order->status = 'CANCELLED';
                } elseif ($order->total_refunded_price < $order->total_purchased_price) {
                    $order->status = 'CONFIRMED';
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
