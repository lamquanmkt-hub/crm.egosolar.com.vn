<?php

use App\Http\Controllers\Admin\RolePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])
    ->prefix('cai-dat')
    ->name('admin.settings.')
    ->controller(RolePermissionController::class)
    ->group(function () {
        Route::get('/', 'overview')->name('index');
        /* EGO_APPEARANCE_SETTINGS_V2_START */
        Route::get(
            '/giao-dien-thuong-hieu',
            [\App\Http\Controllers\Admin\AppearanceSettingsController::class, 'index']
        )->name('appearance');

        Route::post(
            '/giao-dien-thuong-hieu',
            [\App\Http\Controllers\Admin\AppearanceSettingsController::class, 'update']
        )->name('appearance.update');

        Route::delete(
            '/giao-dien-thuong-hieu/reset',
            [\App\Http\Controllers\Admin\AppearanceSettingsController::class, 'reset']
        )->name('appearance.reset');
        /* EGO_APPEARANCE_SETTINGS_V2_END */
        Route::get('/vai-tro', 'roles')->name('roles');
        Route::get('/phan-quyen-trang', 'pages')->name('pages');
        Route::get('/phan-quyen-menu', 'menus')->name('menus');
        Route::get('/quyen-thao-tac', 'actions')->name('actions');
        Route::get('/nhat-ky', 'audit')->name('audit');

        Route::post('/roles', 'storeRole')->name('roles.store');
        Route::put('/roles/{role}', 'updateRole')->whereNumber('role')->name('roles.update');
        Route::put('/roles/{role}/pages', 'syncRolePagePermissions')->whereNumber('role')->name('roles.pages');
        Route::put('/roles/{role}/menus', 'syncRoleMenuPermissions')->whereNumber('role')->name('roles.menus');
        Route::put('/roles/{role}/actions', 'syncRoleBusinessPermissions')->whereNumber('role')->name('roles.actions');
        Route::post('/roles/{role}/clone', 'cloneRole')->whereNumber('role')->name('roles.clone');
        Route::delete('/roles/{role}', 'destroyRole')->whereNumber('role')->name('roles.destroy');

        Route::put('/users/{user}/roles', 'syncUserRoles')->whereNumber('user')->name('users.roles');
        Route::put('/users/{user}/permissions', 'syncUserPermissions')->whereNumber('user')->name('users.permissions');
    });

/* URL cũ: giữ bookmark và link cũ không bị 404. */
Route::middleware(['auth', 'role:admin'])
    ->get('/cai-dat/phan-quyen', fn () => redirect()->route('admin.settings.roles'))
    ->name('admin.role-permissions.index');

/* EGO_WORKSPACE_MATRIX_SETTINGS_ROUTES_START */
Route::middleware(['auth', 'role:admin|management|manager|director|ceo'])
    ->prefix('cai-dat')
    ->name('admin.settings.')
    ->controller(\App\Http\Controllers\Admin\WorkspaceSettingsController::class)
    ->group(function (): void {
        Route::get('/ung-dung-theo-vai-tro', 'index')->name('workspace');
        Route::put('/ung-dung-theo-vai-tro', 'update')->name('workspace.update');
        Route::delete('/ung-dung-theo-vai-tro/reset', 'reset')->name('workspace.reset');
    });
/* EGO_WORKSPACE_MATRIX_SETTINGS_ROUTES_END */
