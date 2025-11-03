<?php

use App\Http\Controllers\Api\ApiBakeryController;
use App\Http\Controllers\Api\ApiBakeryWalletController;
use App\Http\Controllers\Api\ApiDiscountEventController;
use App\Http\Controllers\Api\ApiOperatingHourController;
use App\Http\Controllers\Api\ApiOrderController;
use App\Http\Controllers\Api\ApiProductController;
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
Route::post('/sessions/{sessionId}/cancel', [XenditPaymentController::class, 'cancelSession'])->name('payment.session.cancel');


Route::get('/payments/success/{id}', [XenditPaymentController::class, 'success'])->name('payments.success');
Route::get('/payments/failed/{id}',  [XenditPaymentController::class, 'failed'])->name('payments.failed');

Route::prefix('/xendit')->group(function () {
    Route::post('/webhook', [XenditWebhookController::class, 'handle'])->name('xendit.webhook');
});

Route::prefix('v1')->group(function () {
    // auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login',    [AuthController::class, 'login']);

    // public read bakery
    Route::get('/bakeries', [ApiBakeryController::class, 'index']);
    Route::get('/bakeries/{id}', [ApiBakeryController::class, 'show'])->whereNumber('id');
    // public read operating hours
    Route::get('/bakeries/{id}/hours', [ApiOperatingHourController::class, 'index'])->whereNumber('id');
    Route::get('/operating-hours/{id}', [ApiOperatingHourController::class, 'show'])->whereNumber('id');
    // public read products
    Route::get('/products', [ApiProductController::class, 'index']);
    Route::get('/products/{id}', [ApiProductController::class, 'show'])->whereNumber('id');
    // public read discounts
    Route::get('/discount-events', [ApiDiscountEventController::class, 'index']);
    Route::get('/discount-events/{id}', [ApiDiscountEventController::class, 'show'])->whereNumber('id');

    // read: login + ability wallet
    Route::middleware(['auth:sanctum', 'abilities:wallet:read'])->group(function () {
        Route::get('/bakeries/{id}/wallet', [ApiBakeryWalletController::class, 'show'])->whereNumber('id');
        Route::get('/bakeries/{id}/wallet/transactions', [ApiBakeryWalletController::class, 'transactions'])->whereNumber('id');
    });

    // write: login + ability bakeries
    Route::middleware(['auth:sanctum', 'abilities:bakeries:write'])->group(function () {
        Route::post('/bakeries', [ApiBakeryController::class, 'store']);
        Route::put('/bakeries/{id}', [ApiBakeryController::class, 'update'])->whereNumber('id');
        Route::delete('/bakeries/{id}', [ApiBakeryController::class, 'destroy'])->whereNumber('id');
    });
    // write: login + ability operating hours
    Route::middleware(['auth:sanctum', 'abilities:operating-hours:write'])->group(function () {
        Route::post('/bakeries/{id}/hours', [ApiOperatingHourController::class, 'store'])->whereNumber('id');
        Route::put('/operating-hours/{id}', [ApiOperatingHourController::class, 'update'])->whereNumber('id');
        Route::delete('/operating-hours/{id}', [ApiOperatingHourController::class, 'destroy'])->whereNumber('id');
    });
    // write: login + ability products
    Route::middleware(['auth:sanctum', 'abilities:products:write'])->group(function () {
        Route::post('/products', [ApiProductController::class, 'store']);
        Route::put('/products/{id}', [ApiProductController::class, 'update'])->whereNumber('id');
        Route::delete('/products/{id}', [ApiProductController::class, 'destroy'])->whereNumber('id');
    });
    // write: login + ability discounts
    Route::middleware(['auth:sanctum', 'abilities:discounts:write'])->group(function () {
        Route::post('/discount-events', [ApiDiscountEventController::class, 'store']);
        Route::put('/discount-events/{id}', [ApiDiscountEventController::class, 'update'])->whereNumber('id');
        Route::delete('/discount-events/{id}', [ApiDiscountEventController::class, 'destroy'])->whereNumber('id');
        // additional routes to attach/detach products to/from discount event
        Route::post('/discount-events/{id}/products', [ApiDiscountEventController::class, 'attachProducts'])->whereNumber('id');
        Route::delete('/discount-events/{id}/products', [ApiDiscountEventController::class, 'detachProducts'])->whereNumber('id');
    });

    Route::middleware(['auth:sanctum', 'abilities:orders:create,orders:read'])->group(function () {
        Route::post('/order', [ApiOrderController::class, 'store']);
        Route::get('/order', [ApiOrderController::class, 'index']);
    });
    Route::middleware(['auth:sanctum', 'abilities:orders:update'])->group(function () {
        Route::patch('/order/{id}', [ApiOrderController::class, 'confirmation']);
        Route::patch('/order/{id}/pickup', [ApiOrderController::class, 'update']);
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
        Route::put('/auth/user', [AuthController::class, 'update']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/user', fn(Request $r) => $r->user());
    });
});
