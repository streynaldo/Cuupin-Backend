<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class XenditWebhookController extends Controller
{
    public function handle(Request $r)
    {
        if ($r->header('x-callback-token') !== config('services.xendit.callback_token')) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $event = (string) $r->input('event', '');
        $data  = (array) $r->input('data', []);

        // bikin key idempotensi sederhana: event + id unik dari payload
        $keyParts = [
            'xendit',
            $event,
            $data['id'] ?? $data['payment_request_id'] ?? $data['reference_id'] ?? sha1($r->getContent()),
        ];
        $key = implode(':', $keyParts);

        // Cache::add -> hanya true jika key belum ada
        if (!Cache::add($key, 1, now()->addMinutes(30))) {
            return response()->json(['ok' => true]); // duplikat, langsung ACK
        }

        // … lanjut proses langsung (tanpa job)
        if (str_starts_with($event, 'payment.')) {
            // update order…
        } elseif (str_starts_with($event, 'refund.')) {
            // update refund…
        } elseif (str_starts_with($event, 'payout.') || str_starts_with($event, 'disbursement.')) {
            // update payout…
        }

        return response()->json(['ok' => true]);
    }
}
