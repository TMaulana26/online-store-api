<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Controllers\OrderController;
use Modules\Store\Http\Controllers\ProductController;

// Public product catalog
Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

// Authenticated orders
Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('orders', [OrderController::class, 'store'])
        ->middleware('throttle:checkout')
        ->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});
