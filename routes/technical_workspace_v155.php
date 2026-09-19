<?php

use App\Http\Controllers\Technical\TechnicalWorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace Kỹ thuật V15.5 — Excel, lịch sử báo cáo và thông báo
|--------------------------------------------------------------------------
| Route bổ sung có chốt chống trùng để cài an toàn trên hệ thống hiện tại.
*/
Route::middleware([
    'auth',
    'role:ky_thuat|technical|technician|technical_staff|technical_leader|technical_manager|truong_phong_ky_thuat|admin|management|manager',
])->group(function (): void {
    if (! Route::has('technical-workspace.reports.export')) {
        Route::get('/ky-thuat/dieu-hanh/bao-cao-ky-thuat/xuat-excel', [TechnicalWorkspaceController::class, 'exportReport'])
            ->name('technical-workspace.reports.export');
    }

    if (! Route::has('technical-workspace.reports.snapshot.store')) {
        Route::post('/ky-thuat/dieu-hanh/bao-cao-ky-thuat/luu-lich-su', [TechnicalWorkspaceController::class, 'storeReportSnapshot'])
            ->name('technical-workspace.reports.snapshot.store');
    }

    if (! Route::has('technical-workspace.reports.history')) {
        Route::get('/ky-thuat/dieu-hanh/bao-cao-ky-thuat/lich-su', [TechnicalWorkspaceController::class, 'reportHistory'])
            ->name('technical-workspace.reports.history');
    }

    if (! Route::has('technical-workspace.reports.history.show')) {
        Route::get('/ky-thuat/dieu-hanh/bao-cao-ky-thuat/lich-su/{snapshot}', [TechnicalWorkspaceController::class, 'showReportSnapshot'])
            ->whereNumber('snapshot')
            ->name('technical-workspace.reports.history.show');
    }

    if (! Route::has('technical-workspace.reports.history.export')) {
        Route::get('/ky-thuat/dieu-hanh/bao-cao-ky-thuat/lich-su/{snapshot}/excel', [TechnicalWorkspaceController::class, 'exportReportSnapshot'])
            ->whereNumber('snapshot')
            ->name('technical-workspace.reports.history.export');
    }
});
