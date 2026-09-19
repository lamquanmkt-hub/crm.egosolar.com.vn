<?php

/*
|---------------------------------------------------------------------------
| Route: Kỹ thuật, bảo hành, công cụ solar
|---------------------------------------------------------------------------
|
| Tách từ routes/web.php (2.654 dòng) ngày 2026-08-05. CHỈ DI CHUYỂN nguyên
| văn, KHÔNG đổi URL, tên route hay middleware — đã đối chiếu bảng route
| trước/sau bằng snapshot 1.053 dòng.
|
| Thứ tự trong file giữ đúng thứ tự khai báo cũ: có 2 cặp route trùng URI
| (PUT/DELETE payment-requests/{id}) mà Laravel chọn cái đăng ký TRƯỚC.
|
*/

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Module Kỹ thuật: Tổng quan → Kế hoạch → Báo cáo → Hoàn thiện
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('ky-thuat')
    ->name('ky-thuat.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Technical\TechnicalWorkController::class, 'overview'])
            ->name('tong-quan');

        Route::get('/ke-hoach', [\App\Http\Controllers\Technical\TechnicalWorkController::class, 'plan'])
            ->name('ke-hoach');
        Route::post('/ke-hoach', [\App\Http\Controllers\Technical\TechnicalWorkController::class, 'storePlan'])
            ->name('ke-hoach.store');

        /* EGO_WARRANTY_EXCHANGE_PROPOSAL_START */
        Route::prefix('de-xuat-doi-hang-bao-hanh')
            ->name('warranty-exchange.')
            ->controller(\App\Http\Controllers\Technical\TechnicalWarrantyExchangeController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/serial-info', 'serialInfo')->name('serial-info');
                Route::get('/order-serials', 'orderSerials')->name('order-serials');
                Route::post('/', 'store')->name('store');
                Route::post('/{claim}/minh-chung', 'uploadEvidence')->whereNumber('claim')->name('evidence.upload');
                Route::get('/{claim}/minh-chung/{attachment}', 'downloadEvidence')->whereNumber('claim')->whereNumber('attachment')->name('evidence.download');
                Route::delete('/{claim}/minh-chung/{attachment}', 'destroyEvidence')->whereNumber('claim')->whereNumber('attachment')->name('evidence.destroy');
                Route::get('/{claim}', 'show')->whereNumber('claim')->name('show');
            });
        /* EGO_WARRANTY_EXCHANGE_PROPOSAL_END */

        Route::get('/bao-cao', [\App\Http\Controllers\Technical\TechnicalWorkController::class, 'reports'])
            ->name('bao-cao');
        Route::post('/bao-cao/{record}', [\App\Http\Controllers\Technical\TechnicalWorkController::class, 'saveReport'])
            ->whereNumber('record')
            ->name('bao-cao.save');

        Route::get('/kpis', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'kpis'])
            ->middleware('role:ky_thuat|technical|technical_manager|accounting|admin|manager|management')
            ->name('kpis.index');

        Route::get('/kpis/cong-trinh/{site}', [\App\Http\Controllers\TechnicalKpi\TechnicalProjectKpiController::class, 'show'])
            ->whereNumber('site')
            ->middleware('role:ky_thuat|technical|technical_manager|accounting|admin|manager|management')
            ->name('kpis.project');

        Route::post('/kpis/cong-trinh/{site}', [\App\Http\Controllers\TechnicalKpi\TechnicalProjectKpiController::class, 'save'])
            ->whereNumber('site')
            ->middleware('role:technical_manager|admin|manager|management')
            ->name('kpis.project.save');

        // Giữ route Hoàn thiện để tương thích dữ liệu/link cũ, nhưng không còn hiển thị ở menu Kỹ thuật.
        Route::get('/hoan-thien', [\App\Http\Controllers\Technical\TechnicalWorkController::class, 'completion'])
            ->name('hoan-thien');
        Route::post('/hoan-thien/{record}', [\App\Http\Controllers\Technical\TechnicalWorkController::class, 'saveCompletion'])
            ->whereNumber('record')
            ->name('hoan-thien.save');
    });

/*
|--------------------------------------------------------------------------
| Kỹ thuật - Tính lương
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Kỹ thuật - Tính lương KPI
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Kỹ thuật - Tính lương KPI
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Kỹ thuật - Lương KPI
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:ky_thuat|technical|accounting|admin|manager'])
    ->prefix('ky-thuat/luong')
    ->name('ky-thuat.luong.')
    ->group(function () {

        Route::get('/', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'index'])
            ->name('index');

        Route::post('/', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'store'])
            ->name('store');

        Route::get('/settings', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'settings'])
            ->name('settings');

        Route::post('/settings', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'saveSettings'])
            ->name('settings.save');

        Route::get('/slip-settings', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'slipSettings'])
            ->middleware('role:admin')
            ->name('slip-settings');

        Route::post('/slip-settings', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'saveSlipSettings'])
            ->middleware('role:admin')
            ->name('slip-settings.save');

        Route::delete('/slip-settings/{id}', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'destroySlipField'])
            ->whereNumber('id')
            ->middleware('role:admin')
            ->name('slip-settings.destroy');

        Route::post('/{id}/slip-values', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'saveSlipValues'])
            ->whereNumber('id')
            ->middleware('role:admin')
            ->name('slip-values.save');

        Route::get('/{id}', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'show'])
            ->whereNumber('id')
            ->name('show');

        Route::get('/{id}/edit', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'edit'])
            ->whereNumber('id')
            ->name('edit');

        Route::put('/{id}', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'update'])
            ->whereNumber('id')
            ->name('update');

        Route::post('/{id}/approve', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'approve'])
            ->whereNumber('id')
            ->name('approve');

    });

Route::post('/ky-thuat/luong/settings/kpi-items', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'saveKpiItems'])
    ->middleware(['auth', 'role:admin|accounting|manager'])
    ->name('ky-thuat.luong.settings.kpi-items');

Route::delete('/ky-thuat/luong/settings/kpi-items/{id}', [\App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController::class, 'destroyKpiItem'])
    ->middleware(['auth', 'role:admin|accounting|manager'])
    ->name('ky-thuat.luong.settings.kpi-items.destroy');
