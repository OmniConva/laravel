<?php

use Illuminate\Support\Facades\Route;
use OmniConva\Laravel\Http\Controllers\HealthController;
use OmniConva\Laravel\Http\Controllers\OrdersController;
use OmniConva\Laravel\Http\Controllers\ProductsController;
use OmniConva\Laravel\Http\Middleware\VerifyOmniConvaSignature;

Route::middleware(VerifyOmniConvaSignature::class)->group(function () {
    Route::get('products', [ProductsController::class, 'index'])->name('omniconva.products.index');
    Route::get('products/{id}', [ProductsController::class, 'show'])->name('omniconva.products.show')->where('id', '.+');
    Route::post('orders', [OrdersController::class, 'store'])->name('omniconva.orders.store');
    Route::get('health', HealthController::class)->name('omniconva.health');
});
