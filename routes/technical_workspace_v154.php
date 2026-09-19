<?php

use App\Http\Controllers\Technical\TechnicalWorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace Kỹ thuật V15.3 + V15.4
|--------------------------------------------------------------------------
| Tách lịch thi công / bảo trì và mở trung tâm đề xuất vật tư. Mỗi route
| đều có chốt chống trùng để tương thích máy chủ đã có route cũ.
*/
Route::middleware([
    'auth',
    'role:ky_thuat|technical|technician|technical_staff|technical_leader|technical_manager|truong_phong_ky_thuat|admin|management|manager|warehouse|kho',
])->group(function (): void {
    if (! Route::has('technical-workspace.operations.installation-calendar')) {
        Route::get('/ky-thuat/dieu-hanh/lich-thi-cong', [TechnicalWorkspaceController::class, 'installationCalendar'])
            ->name('technical-workspace.operations.installation-calendar');
    }

    if (! Route::has('technical-workspace.operations.maintenance-calendar')) {
        Route::get('/ky-thuat/dieu-hanh/lich-bao-tri', [TechnicalWorkspaceController::class, 'maintenanceCalendar'])
            ->name('technical-workspace.operations.maintenance-calendar');
    }

    if (! Route::has('technical-workspace.materials.index')) {
        Route::get('/ky-thuat/vat-tu-thi-cong', [TechnicalWorkspaceController::class, 'materialRequests'])
            ->name('technical-workspace.materials.index');
    }
});
