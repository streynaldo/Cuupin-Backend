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
use PhpParser\Node\Expr\Cast\Double;

class ExpirePaidOrder implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $referenceId;

    /**
     * Create a new job instance.
     */
    public function __construct($referenceId)
    {
        $this->referenceId = $referenceId;
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentAction $pa): void
    {
        Log::info("MASUK HANDLE EXPIRE PAID ORDER");
        $order = Order::with('bakery')->where('reference_id', $this->referenceId)->first();
        if (!$order || $order->status !== 'PAID') {
            Log::info("Order not found or not PAID", ['reference_id' => $this->referenceId, 'order' => $order ? $order->id : null]);
            return; // sudah dibayar / expired
        }
        Log::info("SAMPAI DISINI DENGAN ORDER = " . $order->reference_id);

        $order->total_refunded_price = $order->total_purchased_price;
        $order->total_purchased_price = 0;
        $order->save();

        if ($order->total_refunded_price != 0) {
            $res = $pa->createRefund([
                'payment_request_id' => $order->payment_request_id,
                'amount'             => (int) $order->total_refunded_price,
                'currency'           => 'IDR',
                'reason'             => "CANCELLATION",
                'reference_id'       => $order->reference_id,
            ]);

            Log::info("[RESULT REFUND] = " . $res);

            Log::info("SAMPAI DISINI DENGAN ORDER BERHASIL DI REFUND = " . $order->reference_id);

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
        } else {
            Log::info("Order total null");
            return;
        }
    }
}
