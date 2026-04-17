<?php

use App\Http\Controllers\BuyerOrderStatusController;
use App\Http\Controllers\P2PPaymentController;
use App\Http\Controllers\P2PSellerPaymentController;
use App\Http\Controllers\SellerOrderStatusController;
use App\Http\Controllers\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::post('/wallet/withdraw', [WithdrawController::class, 'requestWithdraw']);

Route::middleware(['role:consumer'])->group(function () {
    Route::post('/orders/{orderId}/transfer-proof', [P2PPaymentController::class, 'uploadProof']);
    Route::post('/orders/{orderId}/p2p/upload-proof', [P2PPaymentController::class, 'uploadProof']);
    Route::post('/buyer/orders/{orderId}/confirm-received', [BuyerOrderStatusController::class, 'confirmReceived']);
});

Route::middleware(['can:access-seller-api'])->group(function () {
    Route::post('/seller/orders/{orderId}/p2p/confirm-cash', [P2PSellerPaymentController::class, 'confirmCash']);
    Route::post('/seller/orders/{orderId}/mark-packed', [SellerOrderStatusController::class, 'markPacked']);
    Route::post('/seller/orders/{orderId}/mark-shipped', [SellerOrderStatusController::class, 'markShipped']);
});
