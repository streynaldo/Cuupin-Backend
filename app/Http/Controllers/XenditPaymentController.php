<?php

namespace App\Http\Controllers;

use App\Actions\Xendit\CheckPaymentStatus;
use Illuminate\Http\Request;
use App\Actions\Xendit\CreateEwalletPayment;
use App\Actions\Xendit\PaymentAction;

class XenditPaymentController extends Controller
{
    public function createSession(Request $r, PaymentAction $pa)
    {

        $payload = $r->all();

        $order = [
            'id'      => (string) $payload['order_id'],
            'amount'  => (float) ($payload['amount'] ?? $payload['request_amount'] ?? $r->query('amount')),
            'title'   => $p['title'] ?? $r->query('title'),
            // items bisa dikirim via JSON body atau query (string JSON)
        ];
        $res = $pa->createPaymentSession($order);

        return response()->json($res);
    }
    public function cancelSession(PaymentAction $pa, $sessionId){
        $res = $pa->cancelPaymentSession($sessionId);
        return response()->json($res);
    }
    public function checkSession(PaymentAction $pa, $sessionId)
    {
        $res = $pa->checkPaymentSession($sessionId);
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

    public function payout(Request $r, PaymentAction $pa)
    {
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
