<?php

use Illuminate\Support\Facades\Route;
use Modules\Acl\Http\Controllers\PermissionController;
use Modules\Acl\Http\Controllers\RoleController;
use Modules\Acl\Http\Controllers\UserController;

Route::middleware('auth:sanctum')->group(function () {
    // Users
    Route::apiResource('users', UserController::class);
    Route::controller(UserController::class)->prefix('users')->group(function () {
        Route::prefix('bulk')->group(function () {
            Route::patch('toggle-status', 'bulkToggleStatus')->name('users.bulk-toggle-status');
            Route::post('delete', 'bulkDestroy')->name('users.bulk-delete');
            Route::patch('restore', 'bulkRestore')->name('users.bulk-restore');
            Route::post('force-delete', 'bulkForceDelete')->name('users.bulk-force-delete');
        });

        Route::patch('{user}/toggle-status', 'toggleStatus')->name('users.toggle-status');
        Route::patch('{id}/restore', 'restore')->name('users.restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('users.force-delete');

        Route::post('{user}/sync-roles', 'syncRoles')->name('users.roles.sync');
        Route::post('{user}/assign-roles', 'assignRoles')->name('users.roles.assign');
        Route::post('{user}/remove-roles', 'removeRoles')->name('users.roles.remove');
    });

    // Roles
    Route::apiResource('roles', RoleController::class);
    Route::controller(RoleController::class)->prefix('roles')->group(function () {
        Route::prefix('bulk')->group(function () {
            Route::patch('toggle-status', 'bulkToggleStatus')->name('roles.bulk-toggle-status');
            Route::post('delete', 'bulkDestroy')->name('roles.bulk-delete');
            Route::patch('restore', 'bulkRestore')->name('roles.bulk-restore');
            Route::post('force-delete', 'bulkForceDelete')->name('roles.bulk-force-delete');
        });

        Route::patch('{role}/toggle-status', 'toggleStatus')->name('roles.toggle-status');
        Route::patch('{id}/restore', 'restore')->name('roles.restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('roles.force-delete');

        Route::post('{role}/sync-permissions', 'syncPermissions')->name('roles.permissions.sync');
        Route::post('{role}/give-permissions', 'givePermissions')->name('roles.permissions.give');
        Route::post('{role}/revoke-permissions', 'revokePermissions')->name('roles.permissions.revoke');
    });

    // Permissions
    Route::apiResource('permissions', PermissionController::class);
    Route::controller(PermissionController::class)->prefix('permissions')->group(function () {
        Route::prefix('bulk')->group(function () {
            Route::patch('toggle-status', 'bulkToggleStatus')->name('permissions.bulk-toggle-status');
            Route::post('delete', 'bulkDestroy')->name('permissions.bulk-delete');
            Route::patch('restore', 'bulkRestore')->name('permissions.bulk-restore');
            Route::post('force-delete', 'bulkForceDelete')->name('permissions.bulk-force-delete');
        });

        Route::patch('{permission}/toggle-status', 'toggleStatus')->name('permissions.toggle-status');
        Route::patch('{id}/restore', 'restore')->name('permissions.restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('permissions.force-delete');

        Route::post('{permission}/sync-roles', 'syncRoles')->name('permissions.roles.sync');
        Route::post('{permission}/assign-roles', 'assignRoles')->name('permissions.roles.assign');
        Route::post('{permission}/remove-roles', 'removeRoles')->name('permissions.roles.remove');
    });
});
