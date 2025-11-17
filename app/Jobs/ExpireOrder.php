<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Services\Firebase\FcmV1Service;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ExpireOrder implements ShouldQueue
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
        if (!$order || $order->status !== 'ONPROGRESS') {
            return; // sudah dibayar / expired
        }

        $order->status = 'CANCELLED';
        $order->save();

        // Push notif ke user
        $tokens = DeviceToken::where('user_id', $order->user_id)
            ->pluck('token')
            ->toArray();

        if (!empty($tokens)) {
            $fcm = app(FcmV1Service::class);
            $fcm->sendToTokens(
                $tokens,
                ['title' => 'Order Expired', 'body' => "Your order {$order->id} has expired."],
                ['type' => 'order_expired', 'order_id' => (string)$order->id],
                app('log')
            );
        }

        Log::info('Order expired via delayed job', ['order_id' => $order->id]);
    }
}
