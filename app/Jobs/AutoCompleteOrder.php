<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\DeviceToken;
use App\Models\BakeryWallet;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Services\Firebase\FcmV1Service;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\User;

class AutoCompleteOrder implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $orderId;

    /**
     * Create a new job instance.
     */
    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::find($this->orderId);
        if (!$order || $order->status !== 'CONFIRMED') {
            return;
        }
        if ($order->status == 'CONFIRMED') {
            $order->status = 'COMPLETED';
            $order->save();

            $wallet = BakeryWallet::where('bakery_id', $order->bakery_id)->first();
            $wallet->total_earned = $order->total_purchased_price - $order->total_refunded_price;
            $wallet->total_wallet = $order->total_purchased_price - $order->total_refunded_price;
            $wallet->save();

            try {
                // Push notif ke user
                $tokens = DeviceToken::where('user_id', $order->user_id)
                    ->pluck('token')
                    ->toArray();

                if (!empty($tokens)) {
                    $fcm = app(FcmV1Service::class);
                    $fcm->sendToTokens(
                        $tokens,
                        ['title' => 'Your order has been automatically completed.', 'body' => "Your order #{$order->reference_id} wasn’t picked up in time and has been auto-processed."],
                        ['type' => 'order_expired', 'order_id' => (string)$order->id],
                        app('log')
                    );
                }
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
                        Log::info('No device tokens found for user');
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send confirmed order notification', ['error' => $e->getMessage()]);
            }

            Log::info('[Expire Order] Order expired via delayed job', ['order_id' => $order->id]);
        } else {
            Log::info("[Expire Order] Cant Change Order Status");
        }
    }
}
