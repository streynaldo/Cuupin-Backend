<?php

namespace App\Jobs;

use App\Actions\Xendit\PaymentAction;
use App\Models\Order;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Services\Firebase\FcmV1Service;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ExpirePaidOrder implements ShouldQueue
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
    public function handle(PaymentAction $pa): void
    {
        $order = Order::with('bakery')->find($this->orderId);
        if (!$order || $order->status !== 'PAID') {
            return; // sudah dibayar / expired
        }

        $order->total_refunded_price = $order->total_purchased_price;
        $order->total_purchased_price = 0;
        $order->save();

        $res = $pa->createRefund([
            'payment_request_id' => $order->payment_request_id,
            'amount'             => $order->total_refunded_price,
            'currency'           => 'IDR',
            'reason'             => "Bakery Failed To Confirm",
            'reference_id'       => $order->reference_id,
        ]);
        if ($res) {
            $order->status = 'CANCELLED';
            $order->save();

            // Push notif ke user bakery
            $tokens = DeviceToken::where('user_id', $order->bakery->user_id)
                ->pluck('token')
                ->toArray();

            if (!empty($tokens)) {
                $fcm = app(FcmV1Service::class);
                $fcm->sendToTokens(
                    $tokens,
                    ['title' => 'Order Cancelled', 'body' => "You failed to confirm order #{$order->id}."],
                    ['type' => 'order_cancelled', 'order_id' => (string)$order->id],
                    app('log')
                );
            }
            // Push notif ke user pemesan
            $tokens = DeviceToken::where('user_id', $order->user_id)
                ->pluck('token')
                ->toArray();

            if (!empty($tokens)) {
                $fcm = app(FcmV1Service::class);
                $fcm->sendToTokens(
                    $tokens,
                    ['title' => 'Order Refunded', 'body' => "Refund processed!, Order #" . $order->reference_id .  " has been fully refunded for Rp " . number_format($order->total_refunded_price, 0, ',', '.') . "."],
                    ['type' => 'order_cancelled', 'order_id' => (string)$order->id],
                    app('log')
                );
            }

            Log::info('Order expired via delayed job', ['order_id' => $order->id]);
        }
    }
}
