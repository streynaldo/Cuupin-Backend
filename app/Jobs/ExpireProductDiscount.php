<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\DeviceToken;
use App\Models\DiscountEvent;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Services\Firebase\FcmV1Service;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ExpireProductDiscount implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public $eventId;

    /**
     * Create a new job instance.
     */
    public function __construct($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $event = DiscountEvent::find($this->eventId);

        if (!$event) {
            Log::info("[DISCOUNT EVENT EXPIRE] TIDAK ADA DATA EVENT");
        }

        if ($event->discount_end_time <= now()) {
            $products = Product::where('discount_id', $event->id)->get();
            foreach ($products as $product) {
                $product->discount_id = null;
                $product->discount_price = null;
                $product->save();
            }
            // $tokens = DeviceToken::join('users', 'device_tokens.user_id', '=', 'users.id')
            //     ->where('users.role', 'customer')
            //     ->pluck('device_tokens.token')
            //     ->toArray();

            // if (!empty($tokens)) {
            //     $fcm = app(FcmV1Service::class);

            //     $fcm->sendToTokens(
            //         $tokens,
            //         [], // kosongkan notification jika silent
            //         [
            //             'type' => 'refresh_discount',
            //             'event_id' => (string)$event->id,
            //         ]
            //     );
            // }

            Log::info("[DISCOUNT EVENT EXPIRE] SUKSES MENGGANTI STATUS");
        } else {
            Log::info("[DISCOUNT EVENT EXPIRE] GAGAL MENGGANTI STATUS PRODUK DALAM EVENT");
        }
    }
}
