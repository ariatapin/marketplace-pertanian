<?php

use App\Http\Controllers\Api\Admin\AdminApprovalController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminDisputeApiController;
use App\Http\Controllers\Api\Admin\AdminProcurementApiController;
use App\Http\Controllers\Api\Admin\AdminWithdrawApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'summary']);

    Route::get('/approvals/pending', [AdminApprovalController::class, 'pending']);
    Route::post('/approvals/{userId}/approve', [AdminApprovalController::class, 'approve']);
    Route::post('/approvals/{userId}/reject', [AdminApprovalController::class, 'reject']);

    Route::get('/withdraws/pending', [AdminWithdrawApiController::class, 'pending']);
    Route::post('/withdraws/{withdrawId}/approve', [AdminWithdrawApiController::class, 'approve'])
        ->whereNumber('withdrawId');
    Route::post('/withdraws/{withdrawId}/paid', [AdminWithdrawApiController::class, 'paid'])
        ->whereNumber('withdrawId');
    Route::post('/withdraws/{withdrawId}/reject', [AdminWithdrawApiController::class, 'reject'])
        ->whereNumber('withdrawId');
    Route::post('/reports/{reportId}/review', [AdminDisputeApiController::class, 'review']);
    Route::post('/refunds/{refundId}/paid', [AdminDisputeApiController::class, 'markRefundPaid']);

    Route::get('/admin-products', [AdminProcurementApiController::class, 'products']);
    Route::post('/admin-products', [AdminProcurementApiController::class, 'createProduct']);

    Route::get('/admin-orders', [AdminProcurementApiController::class, 'orders']);
    Route::post('/admin-orders/{adminOrderId}/set-status', [AdminProcurementApiController::class, 'setOrderStatus']);
    Route::post('/admin-orders/{adminOrderId}/set-payment-status', [AdminProcurementApiController::class, 'setPaymentStatus']);
});
