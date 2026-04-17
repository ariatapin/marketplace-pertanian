<?php

use App\Http\Controllers\Api\Affiliate\AffiliateDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:access-affiliate-api'])->prefix('affiliate')->group(function () {
    Route::get('/dashboard', [AffiliateDashboardController::class, 'summary']);
    Route::get('/commissions', [AffiliateDashboardController::class, 'commissions']);
    Route::post('/withdraw', [AffiliateDashboardController::class, 'withdraw']);
});
