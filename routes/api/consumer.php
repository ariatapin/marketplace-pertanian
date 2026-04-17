<?php

use App\Http\Controllers\Api\Consumer\ConsumerDashboardController;
use App\Http\Controllers\DisputeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:consumer'])->prefix('consumer')->group(function () {
    Route::get('/dashboard', [ConsumerDashboardController::class, 'summary']);
    Route::get('/orders', [ConsumerDashboardController::class, 'orders']);
    Route::post('/orders/{orderId}/transfer-proof', [ConsumerDashboardController::class, 'uploadP2PProof']);
    Route::post('/orders/{orderId}/p2p/upload-proof', [ConsumerDashboardController::class, 'uploadP2PProof']);
    Route::post('/orders/{orderId}/confirm-received', [ConsumerDashboardController::class, 'confirmReceived']);
    Route::post('/orders/{orderId}/disputes', [DisputeController::class, 'store']);
    Route::post('/mode/request-affiliate', [ConsumerDashboardController::class, 'requestAffiliate']);
    Route::post('/mode/request-farmer-seller', [ConsumerDashboardController::class, 'requestFarmerSeller']);
});
