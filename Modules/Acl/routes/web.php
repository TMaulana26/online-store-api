<?php

use Illuminate\Support\Facades\Route;
use Modules\Acl\Http\Controllers\AclController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('acls', AclController::class)->names('acl');
});
