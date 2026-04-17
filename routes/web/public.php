<?php

use App\Http\Controllers\DevController;
use App\Http\Controllers\AppPageController;
use App\Http\Controllers\MarketplaceProductController;
use App\Http\Controllers\RegionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppPageController::class, 'landing'])
    ->middleware('block.mitra.marketplace')
    ->name('landing');

Route::get('/produk/{productType}/{productId}', [MarketplaceProductController::class, 'show'])
    ->middleware('block.mitra.marketplace')
    ->whereIn('productType', ['store', 'farmer', 'mitra', 'seller', 'petani'])
    ->whereNumber('productId')
    ->name('marketplace.product.show');

Route::get('/toko/{sellerType}/{sellerId}', [MarketplaceProductController::class, 'showStore'])
    ->middleware('block.mitra.marketplace')
    ->whereIn('sellerType', ['mitra', 'seller', 'store', 'farmer', 'petani'])
    ->whereNumber('sellerId')
    ->name('marketplace.store.show');

Route::get('/regions/provinces', [RegionController::class, 'provinces']);
Route::get('/regions/cities/{province}', [RegionController::class, 'cities']);
Route::get('/regions/districts/{city}', [RegionController::class, 'districts']);

if (app()->environment('local')) {
    Route::get('/dev/openweather', [DevController::class, 'openWeather']);
    Route::get('/dev/weather-cache', [DevController::class, 'weatherCache']);
    Route::get('/dev/weather-widget', [DevController::class, 'weatherWidget']);
}
