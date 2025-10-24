<?php

use App\Http\Controllers\XenditPaymentController;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\PaymentController;
use App\Http\Controllers\XenditWebhookController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return response()->json(['message' => 'Welcome to Cuupin API']);
});

// Health check endpoint - untuk cek API nyala
Route::get('/health', function () {
    $dbStatus = 'OK';
    try {
        DB::connection()->getPdo();
    } catch (\Exception $e) {
        $dbStatus = 'ERROR: ' . $e->getMessage();
    }

    return response()->json([
        'status' => 'OK',
        'message' => 'API is running',
        'timestamp' => now()->toDateTimeString(),
        'environment' => app()->environment(),
        'database' => $dbStatus,
    ]);
});
// Route::get('/health', function () {
//     return response()->json([
//         'status' => 'OK',
//         'message' => 'API is running',
//         'timestamp' => now()->toDateTimeString(),
//         'environment' => app()->environment(),
//     ]);
// });

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

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', fn(Request $r) => $r->user());
        // … taruh semua endpoint API kamu yang butuh login di sini
    });
});
