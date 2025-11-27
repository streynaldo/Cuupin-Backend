<?php

namespace App\Jobs;

use Throwable;
use App\Models\Order;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\Cast\Double;
use App\Actions\Xendit\PaymentAction;
use Illuminate\Queue\SerializesModels;
use App\Services\Firebase\FcmV1Service;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;

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
            $payload = [
                'payment_request_id' => $order->payment_request_id,
                'amount'             => (int) $order->total_refunded_price,
                'currency'           => 'IDR',
                'reason'             => "CANCELLATION",
                'reference_id'       => $order->reference_id,
            ];
        
            try {
                // panggil refund
                $res = $pa->createRefund($payload);
        
                // Log respons sukses — gunakan structured log, jangan konkatenasi string
                Log::info('[RESULT REFUND] success', ['response' => $res, 'payload' => $payload]);
        
                Log::info("SAMPAI DISINI DENGAN ORDER BERHASIL DI REFUND = " . $order->reference_id);
        
                $order->status = 'CANCELLED';
                $order->save();
            } catch (RequestException $e) {
                // Jika menggunakan Http::throw(), exception ini biasanya berisi response di $e->response
                $resp = null;
                $body = null;
                $json = null;
        
                try {
                    $resp = $e->response;
                    $body = $resp ? $resp->body() : null;
                } catch (Throwable $ex) {
                    $body = null;
                }
        
                try {
                    $json = $resp ? $resp->json() : null;
                } catch (Throwable $ex) {
                    $json = null;
                }
        
                // Log lengkap: status, raw body, parsed json errors (jika ada), payload
                Log::error('[XENDIT REFUND] RequestException', [
                    'message' => $e->getMessage(),
                    'status' => $resp ? $resp->status() : null,
                    'body_string' => $body,
                    'body_json' => $json,
                    'payload' => $payload,
                ]);
        
                // Jangan lupa rollback perubahan yang sudah dilakukan pada order jika perlu
                // Kita telah memindahkan total_refunded_price & total_purchased_price sebelumnya;
                // jika ingin batalkan perubahan saat refund gagal, kembalikan nilainya:
                $order->total_purchased_price = $order->total_refunded_price; // restore
                $order->total_refunded_price = 0;
                $order->save();
        
                // rethrow atau return, tergantung kebijakan. Di sini kita rethrow untuk membuat job retryable
                throw $e;
            } catch (Throwable $e) {
                // Tangkap error lain agar juga di-log
                Log::error('[XENDIT REFUND] Unexpected error', [
                    'message' => $e->getMessage(),
                    'payload' => $payload,
                ]);
        
                // restore nilai seperti di atas
                $order->total_purchased_price = $order->total_refunded_price;
                $order->total_refunded_price = 0;
                $order->save();
        
                throw $e;
            }
        
            // Jika refund sukses, lanjutkan push notifications dan set status seperti semula
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
