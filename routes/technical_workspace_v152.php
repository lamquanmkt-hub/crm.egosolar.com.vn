<?php

use App\Http\Controllers\Technical\TechnicalWorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace Kỹ thuật V15.2 — Kế hoạch tuần liên kết
|--------------------------------------------------------------------------
| Chỉ đăng ký route còn thiếu; route hiện có trong technical_workspace.php
| vẫn được giữ nguyên.
*/
Route::middleware([
    'auth',
    'role:ky_thuat|technical|technician|technical_staff|technical_leader|technical_manager|truong_phong_ky_thuat|admin|management|manager',
])->group(function (): void {
    if (! Route::has('technical-workspace.operations.weekly-plan')) {
        Route::get('/ky-thuat/dieu-hanh/ke-hoach-tuan', [TechnicalWorkspaceController::class, 'weeklyPlan'])
            ->name('technical-workspace.operations.weekly-plan');
    }
});
