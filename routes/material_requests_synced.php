<?php

use App\Http\Controllers\Projects\MaterialRequestController;
use App\Http\Controllers\Projects\MaterialRequestDeletionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vật tư công trình - đồng bộ từ crm.egosolar.vn
|--------------------------------------------------------------------------
| Giữ cả tên vai trò mới/cũ của hai hệ thống để không làm mất quyền.
*/
Route::middleware(['auth', 'role:technical|ky_thuat|accounting|admin|warehouse|kho|sales'])
    ->prefix('don-vat-tu')
    ->name('material-requests.')
    ->group(function (): void {
        Route::controller(MaterialRequestController::class)->group(function (): void {
            Route::get('/site-info', 'siteInfo')->name('siteInfo');
            Route::get('/san-pham-kho/tim-kiem', 'searchWarehouseProducts')
                ->name('warehouse-products.search')
                ->middleware('role:warehouse|kho|admin');

            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')
                ->name('create')
                ->middleware('role:technical|ky_thuat|admin|sales|warehouse|kho');
            Route::get('/tao', fn () => redirect()->route('material-requests.create'))
                ->name('create.legacy')
                ->middleware('role:technical|ky_thuat|admin|sales|warehouse|kho');
            Route::post('/', 'store')
                ->name('store')
                ->middleware('role:technical|ky_thuat|admin|sales|warehouse|kho');

            Route::get('/{materialRequest}/edit', 'edit')
                ->whereNumber('materialRequest')
                ->name('edit')
                ->middleware('role:technical|ky_thuat|admin|warehouse|kho|sales');
            Route::get('/{materialRequest}/sua', fn ($materialRequest) => redirect()->route('material-requests.edit', ['materialRequest' => $materialRequest]))
                ->whereNumber('materialRequest')
                ->name('edit.legacy')
                ->middleware('role:technical|ky_thuat|admin|warehouse|kho|sales');
            Route::match(['post', 'put', 'patch'], '/{materialRequest}/cap-nhat', 'update')
                ->whereNumber('materialRequest')
                ->name('update')
                ->middleware('role:technical|ky_thuat|admin|warehouse|kho|sales');

            Route::post('/{materialRequest}/gui-duyet', 'submit')
                ->whereNumber('materialRequest')
                ->name('submit')
                ->middleware('role:technical|ky_thuat|admin');
            Route::post('/{materialRequest}/admin-duyet', 'adminApprove')
                ->whereNumber('materialRequest')
                ->name('admin-approve')
                ->middleware('role:admin');
            Route::post('/{materialRequest}/phan-bo-kho', 'saveWarehouseAllocation')
                ->whereNumber('materialRequest')
                ->name('warehouse-allocation')
                ->middleware('role:warehouse|kho|admin');
            Route::post('/{materialRequest}/kho-duyet', 'warehouseApprove')
                ->whereNumber('materialRequest')
                ->name('warehouse-approve')
                ->middleware('role:warehouse|kho|admin');

            Route::get('/{materialRequest}/exports/excel', 'exportExcel')
                ->whereNumber('materialRequest')
                ->name('export.excel');
            Route::get('/{materialRequest}/phieu-xuat/pdf', 'exportDispatchPdf')
                ->whereNumber('materialRequest')
                ->name('dispatch.pdf')
                ->middleware('role:warehouse|kho|admin');
            Route::get('/{materialRequest}/phieu-xuat/excel', 'exportDispatchExcel')
                ->whereNumber('materialRequest')
                ->name('dispatch.excel')
                ->middleware('role:warehouse|kho|admin');

            Route::match(['post', 'delete'], '/{materialRequest}/xoa', 'destroy')
                ->whereNumber('materialRequest')
                ->name('destroy.legacy')
                ->middleware('role:technical|ky_thuat|admin');
            Route::get('/{materialRequest}', 'show')
                ->whereNumber('materialRequest')
                ->name('show');
        });

        Route::delete('/{materialRequest}', MaterialRequestDeletionController::class)
            ->whereNumber('materialRequest')
            ->name('destroy')
            ->middleware('role:technical|ky_thuat|admin');
    });

Route::get('/theo-doi-trang-thai', fn () => 'Theo dõi trạng thái - OK')
    ->middleware(['auth', 'role:technical|ky_thuat|accounting|admin|warehouse|kho|sales'])
    ->name('status-tracking.index');
