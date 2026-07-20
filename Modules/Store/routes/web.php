<?php

use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Controllers\StoreController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('stores', StoreController::class)->names('store');
});
