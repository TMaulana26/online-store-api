<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Controllers\OrderController;
use Modules\Store\Http\Controllers\ProductController;

// Public product catalog
Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

// Authenticated endpoints
Route::middleware('auth:sanctum')->group(function () {
    // Orders CRUD
    Route::apiResource('orders', OrderController::class);
    Route::controller(OrderController::class)->prefix('orders')->group(function () {
        Route::prefix('bulk')->group(function () {
            Route::patch('toggle-status', 'bulkToggleStatus')->name('orders.bulk-toggle-status');
            Route::post('delete', 'bulkDestroy')->name('orders.bulk-delete');
            Route::patch('restore', 'bulkRestore')->name('orders.bulk-restore');
            Route::post('force-delete', 'bulkForceDelete')->name('orders.bulk-force-delete');
        });

        Route::patch('{order}/toggle-status', 'toggleStatus')->name('orders.toggle-status');
        Route::patch('{id}/restore', 'restore')->name('orders.restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('orders.force-delete');
    });

    // Products CRUD (index and show remain public, but can also be accessed under auth)
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::prefix('bulk')->group(function () {
            Route::patch('toggle-status', 'bulkToggleStatus')->name('products.bulk-toggle-status');
            Route::post('delete', 'bulkDestroy')->name('products.bulk-delete');
            Route::patch('restore', 'bulkRestore')->name('products.bulk-restore');
            Route::post('force-delete', 'bulkForceDelete')->name('products.bulk-force-delete');
        });

        Route::patch('{product}/toggle-status', 'toggleStatus')->name('products.toggle-status');
        Route::patch('{id}/restore', 'restore')->name('products.restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('products.force-delete');
    });
});
