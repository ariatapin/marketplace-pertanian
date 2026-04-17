<?php

use App\Http\Controllers\Api\RegionController;
use Illuminate\Support\Facades\Route;

Route::prefix('regions')->group(function () {
    Route::get('/provinces', [RegionController::class, 'provinces']);
    Route::get('/cities/{province}', [RegionController::class, 'cities']);
    Route::get('/districts/{city}', [RegionController::class, 'districts']);
});
