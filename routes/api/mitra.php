<?php

use App\Http\Controllers\Api\Mitra\MitraDashboardController;
use App\Http\Controllers\Api\Mitra\MitraOrderApiController;
use App\Http\Controllers\Api\Mitra\MitraProductApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:mitra'])->prefix('mitra')->group(function () {
    Route::get('/dashboard', [MitraDashboardController::class, 'summary']);
    Route::get('/admin-products', [MitraDashboardController::class, 'adminProducts']);
    Route::get('/orders/{orderId}', [MitraOrderApiController::class, 'show']);
    Route::get('/store-products', [MitraProductApiController::class, 'index']);
    Route::post('/store-products/{product}/stock-adjust', [MitraProductApiController::class, 'adjustStock']);
    Route::get('/store-products/{product}/stock-mutations', [MitraProductApiController::class, 'mutations']);
    Route::post('/procurement/orders', [MitraDashboardController::class, 'createProcurementOrder']);
    Route::get('/procurement/orders', [MitraDashboardController::class, 'procurementOrders']);
    Route::get('/procurement/orders/{orderId}', [MitraDashboardController::class, 'procurementOrderDetail']);
    Route::post('/procurement/orders/{orderId}/cancel', [MitraDashboardController::class, 'cancelProcurementOrder']);
    Route::post('/procurement/orders/{orderId}/submit-payment', [MitraDashboardController::class, 'submitProcurementPayment']);
    Route::post('/procurement/orders/{orderId}/confirm-received', [MitraDashboardController::class, 'confirmProcurementReceived']);
    Route::get('/orders', [MitraOrderApiController::class, 'index']);
    Route::post('/orders/{orderId}/mark-paid', [MitraOrderApiController::class, 'markPaid']);
    Route::post('/orders/{orderId}/mark-packed', [MitraOrderApiController::class, 'markPacked']);
    Route::post('/orders/{orderId}/mark-shipped', [MitraOrderApiController::class, 'markShipped']);
});
