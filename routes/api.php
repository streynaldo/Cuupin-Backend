<?php
use App\Http\Controllers\XenditPaymentController;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\PaymentController;
use App\Http\Controllers\XenditWebhookController;

// Route::prefix('/ewallet')->group(function () {
//     Route::post('/charge', [PaymentController::class, 'create']);
//     Route::get('/payment-requests/{pr_id}', [PaymentController::class, 'status']);
//     Route::post('/payment-requests/{pr_id}/refund', [PaymentController::class, 'refund']);
// });

Route::post('/test/ewallet', [XenditPaymentController::class,'create']);
Route::get('/test/ewallet/{payment_id}', [XenditPaymentController::class,'checkPaymentStatus'])->name('payments.status');
Route::post('/test/ewallet/refunds', [XenditPaymentController::class,'paymentRefund'])->name('payments.refund');
Route::get('/test/balance', [XenditPaymentController::class, 'checkBalance'])->name('payments.balance');
Route::post('/test/payouts', [XenditPaymentController::class, 'payout'])->name('payments.payout');

Route::get('/payments/success/{id}', [XenditPaymentController::class,'success'])->name('payments.success');
Route::get('/payments/failed/{id}',  [XenditPaymentController::class,'failed'])->name('payments.failed');

Route::prefix('/xendit')->group(function () {
    Route::post('/webhook', [XenditWebhookController::class, 'handle'])->name('xendit.webhook');
});
