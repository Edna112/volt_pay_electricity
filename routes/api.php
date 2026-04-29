<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FapshiWebhookController;
use App\Http\Controllers\Api\GatewayWebhookController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TransactionController;

Route::middleware('api')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::post('/meters/verify', [MeterController::class, 'verify'])->middleware('auth:sanctum');

    Route::post('/payments', [PaymentController::class, 'store'])->middleware('auth:sanctum');
    Route::post('/payments/direct-pay', [PaymentController::class, 'directPay'])->middleware('auth:sanctum');
    Route::get('/transactions/{transId}', [TransactionController::class, 'showByTransId'])->middleware('auth:sanctum');

    // Dummy webhook endpoint for the mock gateway
    Route::post('/webhooks/gateway', [GatewayWebhookController::class, 'handle']);

    // Fapshi webhook (secure via URL secret)
    Route::post('/webhooks/fapshi/{secret}', [FapshiWebhookController::class, 'handle']);
});

