<?php

use App\Http\Controllers\Technical\TechnicalWorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace Kỹ thuật V15.1 — route bổ sung an toàn
|--------------------------------------------------------------------------
| Chỉ đăng ký route còn thiếu để không trùng với technical_workspace.php
| đang có trên các máy chủ cũ.
*/
Route::middleware([
    'auth',
    'role:ky_thuat|technical|technician|technical_staff|technical_leader|technical_manager|truong_phong_ky_thuat|admin|management|manager',
])->group(function (): void {
    if (! Route::has('technical-workspace.overview')) {
        Route::get('/ky-thuat', [TechnicalWorkspaceController::class, 'overview'])
            ->name('technical-workspace.overview');
    }

    if (! Route::has('technical-workspace.operations.daily-report')) {
        Route::get('/ky-thuat/dieu-hanh/bao-cao-ngay', [TechnicalWorkspaceController::class, 'dailyReport'])
            ->name('technical-workspace.operations.daily-report');
    }
});
