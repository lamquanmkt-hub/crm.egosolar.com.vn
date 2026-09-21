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
        /*
         * Tổng quan Kỹ thuật (giai đoạn 1, 2026-09).
         *
         * Giữ NGUYÊN tên route `ky-thuat.tong-quan` để sidebar và mọi link cũ
         * không gãy, nhưng chuyển sang controller mới đọc dữ liệu từ 3 nguồn
         * công việc THẬT (quy trình Công trình / Task / Bảo trì) thay vì bảng
         * `technical_work_records` vốn chỉ được ghi bởi chính form kế hoạch
         * bên dưới — đây là nguyên nhân trang cũ luôn hiển thị toàn số 0.
         *
         * Các route Kế hoạch / Báo cáo / Hoàn thiện cũ được giữ nguyên để dữ
         * liệu đã nhập trước đây vẫn truy cập được.
         */
        Route::get('/', [\App\Http\Controllers\Technical\TechnicalWorkboardController::class, 'overview'])
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
                Route::get('/viec-kho', 'warehouseQueue')->name('warehouse-queue');
                Route::get('/{claim}', 'show')->whereNumber('claim')->name('show');

                // Hành động quy trình Đổi hàng bảo hành (whitelist theo từng action, KHÔNG nhận status tuỳ ý)
                Route::controller(\App\Http\Controllers\Technical\WarrantyExchangeWorkflowController::class)
                    ->prefix('/{claim}')->whereNumber('claim')->group(function (): void {
                        Route::post('/duyet', 'approve')->name('approve');
                        Route::post('/yeu-cau-bo-sung', 'requestInfo')->name('request-info');
                        Route::post('/tu-choi', 'reject')->name('reject');
                        Route::post('/gui-lai', 'resubmit')->name('resubmit');
                        Route::post('/mo-lai', 'reopen')->name('reopen');
                        Route::post('/huy', 'cancel')->name('cancel');
                        Route::post('/kho/giu-hang', 'reserve')->name('reserve');
                        Route::post('/kho/nha-hang', 'release')->name('release');
                        Route::post('/kho/xuat', 'issue')->name('issue');
                        Route::post('/kho/thu-hoi', 'faultyReturn')->name('faulty-return');
                        Route::post('/ky-thuat/nhan-hang', 'techReceive')->name('tech-receive');
                        Route::post('/ky-thuat/xac-nhan-thay', 'confirmReplaced')->name('confirm-replaced');
                        Route::post('/hoan-thu-hoi', 'deferReturn')->name('defer-return');
                        Route::post('/hoan-tat', 'complete')->name('complete');
                    });
            });
        /* EGO_WARRANTY_EXCHANGE_PROPOSAL_END */

        /* EGO_WARRANTY_REPAIR_START — Sửa chữa sản phẩm tính phí */
        Route::prefix('sua-chua-tinh-phi')
            ->name('repair.')
            ->controller(\App\Http\Controllers\Technical\WarrantyRepairController::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/tra-serial', 'lookupSerial')->name('lookup-serial');
                Route::get('/khach-hang', 'searchCustomers')->name('customers');
                Route::get('/{claim}', 'show')->whereNumber('claim')->name('show');
                Route::prefix('/{claim}')->whereNumber('claim')->group(function (): void {
                    Route::post('/chan-doan', 'diagnosis')->name('diagnosis');
                    Route::post('/bao-gia', 'quotation')->name('quotation');
                    Route::post('/bao-gia/gui', 'sendQuotation')->name('quotation.send');
                    Route::post('/khach-xac-nhan', 'decision')->name('decision');
                    Route::post('/linh-kien/giu', 'reserveParts')->name('parts.reserve');
                    Route::post('/linh-kien/xuat', 'issueParts')->name('parts.issue');
                    Route::post('/linh-kien/hoan', 'returnParts')->name('parts.return');
                    Route::post('/bat-dau-sua', 'start')->name('start');
                    Route::post('/cap-nhat-sua', 'progress')->name('progress');
                    Route::post('/chuyen-kiem-tra', 'qaSubmit')->name('qa.submit');
                    Route::post('/kiem-tra', 'qa')->name('qa');
                    Route::post('/ban-giao', 'handover')->name('handover');
                    Route::post('/hoan-tat', 'complete')->name('complete');
                    Route::post('/huy', 'cancel')->name('cancel');
                });
            });
        /* EGO_WARRANTY_REPAIR_END */

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

/*
|--------------------------------------------------------------------------
| Bàn làm việc Kỹ thuật + Báo cáo ngày (giai đoạn 1, 2026-09)
|--------------------------------------------------------------------------
| Đặt ở CUỐI file để không thay đổi thứ tự khớp URL của bất kỳ route nào
| đang chạy. Toàn bộ tên route dùng tiền tố mới `technical.*`.
*/
require __DIR__.'/technical_work.php';
