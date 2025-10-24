<?php
use App\Http\Controllers\XenditPaymentController;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\PaymentController;
use App\Http\Controllers\XenditWebhookController;


Route::post('/refunds', [XenditPaymentController::class,'paymentRefund'])->name('payments.refund');
Route::get('/balance', [XenditPaymentController::class, 'checkBalance'])->name('payments.balance');
Route::post('/payouts', [XenditPaymentController::class, 'payout'])->name('payments.payout');


Route::post('/sessions', [XenditPaymentController::class, 'createSession'])->name('payment.session.create');
Route::get('/sessions/{sessionId}', [XenditPaymentController::class, 'checkSession'])->name('payment.session.check');
Route::post('/sessions/{sessionId}/cancel', [XenditPaymentController::class, 'cancelSession'])->name('payment.session.check');


Route::get('/payments/success/{id}', [XenditPaymentController::class,'success'])->name('payments.success');
Route::get('/payments/failed/{id}',  [XenditPaymentController::class,'failed'])->name('payments.failed');

Route::prefix('/xendit')->group(function () {
    Route::post('/webhook', [XenditWebhookController::class, 'handle'])->name('xendit.webhook');
});
