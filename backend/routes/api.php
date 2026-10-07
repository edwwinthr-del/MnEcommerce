<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);
    Route::middleware('throttle:catalog')->group(function () {
        Route::get('products', [CatalogController::class, 'index']);
        Route::get('products/{slug}', [CatalogController::class, 'show']);
        Route::get('categories', [CatalogController::class, 'categories']);
        Route::get('banners', [CatalogController::class, 'banners']);
    });
    Route::post('checkout/preview', [CheckoutController::class, 'preview'])->middleware('throttle:checkout');
    Route::post('orders', [CheckoutController::class, 'store'])->middleware('throttle:orders');
    require __DIR__.'/auth.php';
});
