<?php

use App\Http\Controllers\api\ApiBakeryController;
use App\Http\Controllers\Api\ApiOrderController;
use App\Http\Controllers\api\ApiProductController;
use App\Http\Controllers\XenditPaymentController;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\PaymentController;
use App\Http\Controllers\XenditWebhookController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BakeryController;
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


Route::post('/refunds', [XenditPaymentController::class, 'paymentRefund'])->name('payments.refund');
Route::get('/balance', [XenditPaymentController::class, 'checkBalance'])->name('payments.balance');
Route::post('/payouts', [XenditPaymentController::class, 'payout'])->name('payments.payout');


Route::post('/sessions', [XenditPaymentController::class, 'createSession'])->name('payment.session.create');
Route::get('/sessions/{sessionId}', [XenditPaymentController::class, 'checkSession'])->name('payment.session.check');
Route::post('/sessions/{sessionId}/cancel', [XenditPaymentController::class, 'cancelSession'])->name('payment.session.check');


Route::get('/payments/success/{id}', [XenditPaymentController::class, 'success'])->name('payments.success');
Route::get('/payments/failed/{id}',  [XenditPaymentController::class, 'failed'])->name('payments.failed');

Route::prefix('/xendit')->group(function () {
    Route::post('/webhook', [XenditWebhookController::class, 'handle'])->name('xendit.webhook');
});

Route::prefix('v1')->group(function () {
    // auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login',    [AuthController::class, 'login']);

    // public read
    Route::get('/bakeries', [ApiBakeryController::class, 'index']);
    Route::get('/bakeries/{id}', [ApiBakeryController::class, 'show']);

    Route::get('/products', [ApiProductController::class, 'index']);
    Route::get('/products/{id}', [ApiProductController::class, 'show']);

    // write: login + ability
    Route::middleware(['auth:sanctum', 'abilities:bakeries:write'])->group(function () {
        Route::post('/bakeries', [ApiBakeryController::class, 'store']);
        Route::put('/bakeries/{id}', [ApiBakeryController::class, 'update']);
        Route::delete('/bakeries/{id}', [ApiBakeryController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'abilities:products:write'])->group(function () {
        Route::post('/products', [ApiProductController::class, 'store']);
        Route::put('/products/{id}', [ApiProductController::class, 'update']);
        Route::delete('/products/{id}', [ApiProductController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'abilities:orders:create,orders:read'])->group(function () {
        Route::post('/order', [ApiOrderController::class, 'store']);
        Route::get('/order', [ApiOrderController::class, 'index']);
    });
    Route::middleware(['auth:sanctum', 'abilities:orders:update'])->group(function () {
        Route::patch('/order/{id}', [ApiOrderController::class, 'confirmation']);
        Route::patch('/order/{id}/pickup', [ApiOrderController::class, 'update']);
    });

    // other protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', fn(Request $r) => $r->user());
    });
});
