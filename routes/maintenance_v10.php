<?php

use App\Http\Controllers\Technical\SolarMaintenanceWorkflowController;
use App\Services\Technical\MaintenanceProjectHandoffService;
use Illuminate\Support\Facades\Route;

// Đăng ký observer trước khi request chạy controller. Khi Sales hoàn thành công trình,
// hồ sơ tự được chuyển sang O&M mà không cần nhập lại.
app(MaintenanceProjectHandoffService::class)->bootObservers();

Route::middleware([
    'auth',
    'role:management|ky_thuat|technical|technician|technical_staff|technical_leader|technical_manager|accounting|admin|manager|warehouse|kho|sales|sales_manager|cskh',
])
    ->prefix('ky-thuat/bao-tri-bao-hanh')
    ->name('ky-thuat.maintenance.')
    ->controller(SolarMaintenanceWorkflowController::class)
    ->group(function (): void {
        // URL sự cố cũ vẫn hoạt động nhưng quay về đúng trung tâm O&M thống nhất.
        Route::get('/su-co', 'issues')->name('issues');
        Route::post('/su-co', 'storeIncident')->name('issues.store');
        Route::delete(
            '/ho-so/{profile}/xoa-oam',
            [\App\Http\Controllers\Technical\SolarMaintenanceController::class, 'destroyMaintenanceProfile']
        )
            ->whereNumber('profile')
            ->name('cards.destroy');

        Route::post(
            '/quick-project',
            'quickCreateProject'
        )
            ->name('quick-project.store');


        Route::post('/{schedule}/phan-cong', 'assignTeam')->whereNumber('schedule')->name('team.assign');

        // V13.3: Admin được quay lại Bước 1 để chỉnh kế hoạch mà không lùi trạng thái workflow.
        Route::post('/{schedule}/admin/ke-hoach', 'adminUpdatePlan')
            ->whereNumber('schedule')
            ->name('admin-plan.update');

        // V13: cổng duyệt phân công nằm trong Bước 2, không thay đổi thứ tự 6 bước.
        Route::post('/{schedule}/phan-cong/gui-duyet', [\App\Http\Controllers\Technical\SolarMaintenanceAssignmentApprovalController::class, 'submit'])
            ->whereNumber('schedule')->name('assignment-approval.submit');
        Route::post('/{schedule}/phan-cong/phe-duyet', [\App\Http\Controllers\Technical\SolarMaintenanceAssignmentApprovalController::class, 'approve'])
            ->whereNumber('schedule')->name('assignment-approval.approve');
        Route::post('/{schedule}/phan-cong/yeu-cau-sua', [\App\Http\Controllers\Technical\SolarMaintenanceAssignmentApprovalController::class, 'requestRevision'])
            ->whereNumber('schedule')->name('assignment-approval.revision');
        Route::post('/{schedule}/phan-cong/tu-choi', [\App\Http\Controllers\Technical\SolarMaintenanceAssignmentApprovalController::class, 'reject'])
            ->whereNumber('schedule')->name('assignment-approval.reject');

        Route::post(
            '/{schedule}/cap-nhat-so-dot',
            'updateRounds'
        )
            ->whereNumber('schedule')
            ->name('rounds.update');
        Route::post('/{schedule}/bat-dau', 'start')->whereNumber('schedule')->name('work.start');
        Route::post('/{schedule}/checklist', 'saveChecklist')->whereNumber('schedule')->name('checklist.save');
        Route::post('/{schedule}/hoan-tat-thuc-hien', 'finishExecution')->whereNumber('schedule')->name('work.finish');
        Route::post('/{schedule}/bao-cao', 'saveReport')->whereNumber('schedule')->name('report.save');
    });
