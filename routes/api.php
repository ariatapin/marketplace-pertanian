<?php

use Illuminate\Support\Facades\Route;

require __DIR__ . '/api/public.php';

Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
    require __DIR__ . '/api/shared.php';
    require __DIR__ . '/api/admin.php';
    require __DIR__ . '/api/seller.php';
    require __DIR__ . '/api/affiliate.php';
    require __DIR__ . '/api/mitra.php';
    require __DIR__ . '/api/consumer.php';
});
