<?php
// app/Actions/Xendit/CreateEwalletPayment.php
namespace App\Actions\Xendit;

use App\Services\Payments\Xendit;

class PaymentAction
{
    public function createEwalletPayment(array $order, string $wallet)
    { // wallet: DANA|LINKAJA|SHOPEEPAY|OVO
        $payload = [
            'reference_id'   => 'INV-' . $order['id'],   // unik
            'type'           => 'PAY',
            'country'        => 'ID',
            'currency'       => 'IDR',
            'request_amount' => (float) $order['amount'],
            'channel_code'   => match (strtoupper($wallet)) {
                'OVO'        => 'OVO',
                'DANA'       => 'DANA',
                'LINKAJA'    => 'LINKAJA',
                'SHOPEEPAY'  => 'SHOPEEPAY',
                default      => throw new \InvalidArgumentException('Unsupported wallet'),
            },
            'channel_properties' => $this->propsFor($wallet, $order),
        ];

        return app(Xendit::class)->post('/v3/payment_requests', $payload);
    }

    public function createRefund(array $payload)
    {
        return app(Xendit::class)->refund($payload);
    }

    public function checkPaymentStatus(string $paymentId)
    { // wallet: DANA|LINKAJA|SHOPEEPAY|OVO
        return app(Xendit::class)->get('/v3/payment_requests/' . $paymentId);
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

    private function propsFor(string $wallet, array $order): array
    {
        $success = route('payments.success', $order['id']);
        $failed  = route('payments.failed',  $order['id']);

        return match (strtoupper($wallet)) {
            'OVO'       => ['account_mobile_number' => $order['phone']], // wajib utk OVO
            'DANA', 'LINKAJA', 'SHOPEEPAY'
            => ['success_return_url' => $success, 'failure_return_url' => $failed],
            default     => [],
        };
    }
}
