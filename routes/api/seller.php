<?php

use App\Http\Controllers\Api\Seller\SellerDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:access-seller-api'])->prefix('seller')->group(function () {
    Route::get('/dashboard', [SellerDashboardController::class, 'summary']);
    Route::get('/orders', [SellerDashboardController::class, 'orders']);
    Route::post('/orders/{orderId}/mark-packed', [SellerDashboardController::class, 'markPacked']);
    Route::post('/orders/{orderId}/mark-shipped', [SellerDashboardController::class, 'markShipped']);
    Route::post('/orders/{orderId}/p2p/confirm-cash', [SellerDashboardController::class, 'confirmCash']);
});
