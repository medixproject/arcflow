<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LateTransferReviewController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ApiKeyAuth;

Route::get('/health', HealthController::class);

Route::middleware(ApiKeyAuth::class)->prefix('v1')->group(function () {
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::get('reviews/late-transfers', [LateTransferReviewController::class, 'index']);
});
