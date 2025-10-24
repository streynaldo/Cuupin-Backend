<?php
// app/Actions/Xendit/CreateEwalletPayment.php
namespace App\Actions\Xendit;

use App\Services\Payments\Xendit;

class PaymentAction
{
    public function createPaymentSession(array $order){
        $payload = [
            'reference_id'           => 'INV-' . $order['id'],
            'session_type'          => "PAY",
            'amount'                => (int) round($order['amount']),
            'currency'              => 'IDR',
            'mode'                  => "PAYMENT_LINK",
            "allowed_payment_channels" => ["OVO", "SHOPEEPAY", "DANA"],
            'country'               => "ID",
            'description'           => $order['title'] ?? 'Pembayaran Order #' . $order['id'],
        ];

        return app(Xendit::class)->post('/sessions', $payload);
    }

    public function checkPaymentSession($sessionId){
        return app(Xendit::class)->get('/sessions/' . $sessionId);
    }
    public function cancelPaymentSession($sessionId){
        $data = [];
        return app(Xendit::class)->post('/sessions/' . $sessionId . '/cancel', $data);
    }

    public function createRefund(array $payload)
    {
        return app(Xendit::class)->refund($payload);
    }


    public function checkBalance(){
        return app(Xendit::class)->balance();
    }

    public function createPayout(array $payout){
        $data = [
            'reference_id' => 'myref-' . time(), // Generate unique reference ID
            'channel_code' => $payout['channel_code'], // Example channel code
            'channel_properties' => [
                'account_number' => $payout['account_number'],
                'account_holder_name' => $payout['account_holder_name'],
            ],
            'amount' => $payout['amount'], // Example amount
            'description' => 'Penarikan Dana',
            'currency' => 'IDR',
        ];
    
        return app(Xendit::class)->post('/v2/payouts', $data);
    }
}
