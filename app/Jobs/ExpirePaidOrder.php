<?php

namespace App\Jobs;

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
            return;
        }

        Log::info("SAMPAI DISINI DENGAN ORDER = " . $order->reference_id);

        $order->total_refunded_price = $order->total_purchased_price;
        $order->total_purchased_price = 0;
        $order->save();

        if ($order->total_refunded_price == 0) {
            Log::info("Order total null");
            return;
        }

        $payload = [
            'payment_request_id' => $order->payment_request_id,
            'amount'             => (int) $order->total_refunded_price,
            'currency'           => 'IDR',
            'reason'             => "CANCELLATION",
            'reference_id'       => $order->reference_id,
        ];

        try {
            $res = $pa->createRefund($payload);

            // Jika mengembalikan Illuminate\Http\Client\Response
            if ($res instanceof \Illuminate\Http\Client\Response) {
                Log::info('Refund HTTP response', [
                    'status' => $res->status(),
                    'body'   => $res->body(), // <-- akan menampilkan JSON lengkap dari API
                ]);
                $ok = $res->successful();
            } else {
                // Jika return bukan Response, log ringkas
                Log::info('Refund result (non-Response)', ['type' => is_object($res) ? get_class($res) : gettype($res)]);
                $ok = (bool) $res;
            }

            if ($ok) {
                // sukses: ubah status dan kirim notifikasi seperti sebelumnya
                $order->status = 'CANCELLED';
                $order->save();

                // notif bakery
                $tokens = DeviceToken::where('user_id', $order->bakery->user_id)->pluck('token')->toArray();
                if (!empty($tokens)) {
                    app(FcmV1Service::class)->sendToTokens(
                        $tokens,
                        ['title' => 'Order Cancelled', 'body' => "You failed to confirm order #{$order->id}."],
                        ['type' => 'order_cancelled', 'order_id' => (string)$order->id],
                        app('log')
                    );
                }

                // notif pemesan
                $tokens = DeviceToken::where('user_id', $order->user_id)->pluck('token')->toArray();
                if (!empty($tokens)) {
                    app(FcmV1Service::class)->sendToTokens(
                        $tokens,
                        [
                            'title' => 'Order Refunded',
                            'body' => "Refund processed!, Order #{$order->reference_id} has been fully refunded for Rp " . number_format($order->total_refunded_price, 0, ',', '.') . "."
                        ],
                        ['type' => 'order_cancelled', 'order_id' => (string)$order->id],
                        app('log')
                    );
                }

                Log::info('Order expired via delayed job', ['order_id' => $order->id]);
            } else {
                Log::warning('Refund returned non-success result', ['order_id' => $order->id]);
            }
        } catch (RequestException $e) {
            $resp = $e->response;
            Log::error('Refund RequestException', [
                'message' => $e->getMessage(),
                'status'  => $resp?->status(),
                'body'    => $resp?->body(), // <-- ini akan memuat JSON error lengkap untuk debugging
                'payload' => $payload,
            ]);

            // (opsional) simpan body ke file lokal jika log viewer truncate:
            // if ($resp) {
            //     file_put_contents(storage_path('logs/xendit-refund-body.log'), now()->toDateTimeString() . ' ' . $resp->body() . PHP_EOL, FILE_APPEND);
            // }
        } catch (\Throwable $e) {
            Log::error('Refund unexpected error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $payload,
            ]);
        }
    }
}
