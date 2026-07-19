<?php

use App\Http\Controllers\Admin\RolePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])
    ->prefix('cai-dat/phan-quyen')
    ->name('admin.role-permissions.')
    ->controller(RolePermissionController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');

        Route::post('/roles', 'storeRole')->name('roles.store');
        Route::put('/roles/{role}', 'updateRole')->whereNumber('role')->name('roles.update');
        Route::put('/roles/{role}/permissions', 'syncRolePermissions')->whereNumber('role')->name('roles.permissions');
        Route::post('/roles/{role}/clone', 'cloneRole')->whereNumber('role')->name('roles.clone');
        Route::delete('/roles/{role}', 'destroyRole')->whereNumber('role')->name('roles.destroy');

        Route::put('/users/{user}/roles', 'syncUserRoles')->whereNumber('user')->name('users.roles');
        Route::put('/users/{user}/permissions', 'syncUserPermissions')->whereNumber('user')->name('users.permissions');
    });
