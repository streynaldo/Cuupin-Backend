<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Actions\Xendit\PaymentAction;
use App\Models\BakeryWallet;

class XenditPaymentController extends Controller
{
    public function createSession(Request $r, PaymentAction $pa)
    {
        // dd($r);
        $validated = $r->validate([
            'reference_id' => 'required|string',
            'amount' => 'numeric|min:0',
        ]);

        $payload = $validated;
        // dd($payload);

        $order = [
            'reference_id'      => (string) $payload['reference_id'],
            'amount'  => (float) ($payload['amount'] ?? $payload['request_amount'] ?? $r->query('amount')),
            'title'   => 'Pembayaran #' . $payload['reference_id'],
            // items bisa dikirim via JSON body atau query (string JSON)
        ];

        $res = $pa->createPaymentSession($order);

        return response()->json($res);
    }
    public function cancelSession(PaymentAction $pa, $sessionId)
    {
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
            'reference_id'       => $r->input('reference_id'),
        ]);

        return response()->json($res);
    }

    public function checkBalance(PaymentAction $pa)
    {
        $res = $pa->checkBalance();

        return response()->json($res);
    }

    public function payout(Request $r, PaymentAction $pa)
    {
        $validated = $r->validate(
            [
                'channel_code' => 'required|string',
                'account_holder_name' => 'required|string',
                'account_number' => 'required|numeric',
                'amount' => 'required|numeric',
                'bakery_id' => 'required|numeric'
            ]
        );
        $bakeryWallet = BakeryWallet::where('bakery_id', $validated['bakery_id'])->first();

        if ($validated['amount'] > $bakeryWallet->total_wallet - 5000) {
            return response()->json(['status' => 'Failed amount more than total wallet amount', 'wallet' => $bakeryWallet->total_wallet - 5000], 400);
        }

        $res = $pa->createPayout([
            'channel_code' => $validated['channel_code'],
            'account_holder_name' => $validated['account_holder_name'],
            'account_number' => $validated['account_number'],
            'amount' => $validated['amount'],
            'bakery_id' => $validated['bakery_id'],
        ]);

        return response()->json(['status' => 'Payout Success', 'data' => $res], 200);
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
