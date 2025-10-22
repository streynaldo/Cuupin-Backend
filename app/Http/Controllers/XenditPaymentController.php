<?php

namespace App\Http\Controllers;

use App\Actions\Xendit\CheckPaymentStatus;
use Illuminate\Http\Request;
use App\Actions\Xendit\CreateEwalletPayment;
use App\Actions\Xendit\PaymentAction;

class XenditPaymentController extends Controller
{
    // GET /test/ewallet?wallet=DANA&amount=15000&order_id=123&phone=0899...
    public function create(Request $r, PaymentAction $pa)
    {
        // ambil dari JSON body dulu, kalau kosong baru fallback ke query params
        $payload = $r->all();
        // dd($payload);

        $wallet = strtoupper($payload['wallet'] ?? $r->query('wallet', 'DANA'));
        $order = [
            'id'     => $payload['order_id'] ?? $r->query('order_id', now()->timestamp),
            'amount' => (float) ($payload['request_amount'] ?? $payload['amount'] ?? $r->query('amount', 10000)),
            'phone'  => $payload['channel_properties']['mobile_number'] ?? $payload['phone'] ?? $r->query('phone'),
        ];

        $res = $pa->createEwalletPayment($order, $wallet);

        $url = data_get($res, 'actions.mobile_web_checkout_url')
            ?? data_get($res, 'actions.desktop_web_checkout_url');

        return $url ? redirect()->away($url) : response()->json($res);
    }

    public function checkPaymentStatus(PaymentAction $pa, $paymentId)
    {
        $res = $pa->checkPaymentStatus($paymentId);
        return response()->json($res);
    }

    public function paymentRefund(Request $r, PaymentAction $pa)
    {
        $paymentRequestId = $r->input('payment_request_id'); // pr-xxxx
        $amount = (int) $r->input('amount', 0);
        $reason = $r->input('reason', 'CANCELLATION');

        $res = $pa->createRefund([
            'payment_request_id' => $paymentRequestId,
            'amount'             => $amount,
            'currency'           => 'IDR',
            'reason'             => $reason,
            'reference_id'       => 'REFUND-' . ($r->input('order_id') ?? uniqid()),
        ]);

        return response()->json($res);
    }

    public function checkBalance(PaymentAction $pa)
    {
        $res = $pa->checkBalance();

        dd($res);

        return response()->json($res);
    }

    public function payout(Request $r, PaymentAction $pa){
        $validated = $r->validate(
            [
                'channel_code' => 'required|string',
                'account_holder_name' => 'required|string',
                'account_number' => 'required|numeric',
                'amount' => 'required|numeric'
            ]
        );
        $res = $pa->createPayout([
            'channel_code' => $validated['channel_code'],
            'account_holder_name' => $validated['account_holder_name'],
            'account_number' => $validated['account_number'],
            'amount' => $validated['amount']
        ]);

        return response()->json($res);
    }

    public function success($id)
    {
        return "Payment success for order {$id}";
    }
    public function failed($id)
    {
        return "Payment failed for order {$id}";
    }
}
