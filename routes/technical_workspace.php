<?php

use App\Http\Controllers\Technical\TechnicalWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'role:admin|management|manager|technical_manager|technical_leader|technical|technical_staff|technician|ky_thuat',
])
    ->prefix('ky-thuat')
    ->name('technical-workspace.')
    ->controller(TechnicalWorkspaceController::class)
    ->group(function (): void {
        // 1. Tổng quan Kỹ thuật
        // Trang /ky-thuat nay do module dong bo tu crm.egosolar.vn phu trach.
        Route::get('/workspace-cu', 'overview')->name('overview');

        // 2. Công trình — giữ một danh sách tổng, các URL cũ vẫn hoạt động.
        Route::prefix('cong-trinh')->name('projects.')->group(function (): void {
            Route::get('/', 'projectsAll')->name('all');
            Route::get('/cho-khao-sat', 'projectsWaitingSurvey')->name('waiting-survey');
            Route::get('/cho-phuong-an-ky-thuat', 'projectsWaitingDesign')->name('waiting-design');
            Route::get('/cho-vat-tu', 'projectsWaitingMaterials')->name('waiting-materials');
            Route::get('/cho-thi-cong', 'projectsWaitingInstallation')->name('waiting-installation');
            Route::get('/dang-thi-cong', 'projectsInstalling')->name('installing');
            Route::get('/cho-nghiem-thu', 'projectsWaitingAcceptance')->name('waiting-acceptance');
            Route::get('/dang-bao-hanh', 'projectsWarranty')->name('warranty');
        });

        // 3. Điều hành Kỹ thuật — dữ liệu liên kết từ Công trình, Bảo trì, Chấm công và Nghỉ phép.
        Route::prefix('dieu-hanh')->name('operations.')->group(function (): void {
            Route::get('/bao-cao-ngay', 'dailyReport')->name('daily-report');
            Route::get('/ke-hoach-tuan', 'weeklyPlan')->name('weekly-plan');
            Route::get('/lich-thi-cong', 'installationCalendar')->name('installation-calendar');
            Route::get('/lich-bao-tri', 'maintenanceCalendar')->name('maintenance-calendar');
        });

        // Alias Điều phối cũ để bookmark không lỗi.
        Route::prefix('dieu-phoi')->name('coordination.')->group(function (): void {
            Route::get('/lich-khao-sat', 'weeklyPlan')->name('survey-schedule');
            Route::get('/lich-thi-cong', 'installationCalendar')->name('installation-schedule');
            Route::get('/phan-cong-nhan-su', 'weeklyPlan')->name('assignments');
            Route::get('/viec-cua-phong', 'teamTasks')->name('team-tasks');
            Route::get('/viec-cua-toi', 'myTasks')->name('my-tasks');
        });

        // 4. Vật tư thi công
        Route::get('/vat-tu-thi-cong', 'materialRequests')->name('materials.index');

        // 5. Hồ sơ & Bảo hành
        Route::prefix('ho-so')->name('documents.')->group(function (): void {
            Route::get('/khao-sat-hinh-anh', 'surveyDocuments')->name('survey');
            Route::get('/file-3d-cad', 'designDocuments')->name('design');
            Route::get('/de-xuat-vat-tu', 'materialDocuments')->name('materials');
            Route::get('/nhat-ky-thi-cong', 'dailyLogDocuments')->name('daily-logs');
            Route::get('/nghiem-thu', 'acceptanceDocuments')->name('acceptance');
            Route::get('/ho-so-bien-ban', 'documents')->name('all');
        });

        Route::prefix('bao-tri-bao-hanh')->name('warranty.')->group(function (): void {
            Route::get('/lich-om', 'maintenanceSchedule')->name('maintenance');
            Route::get('/phieu-su-co', 'warrantyIssues')->name('issues');
            Route::get('/thiet-bi-can-doi', 'replacementDevices')->name('replacements');
            Route::get('/lich-su-xu-ly', 'warrantyHistory')->name('history');
        });

        // Báo cáo cũ vẫn giữ để không mất chức năng, nhưng được truy cập từ Dashboard thay vì sidebar dài.
        Route::prefix('bao-cao')->name('reports.')->group(function (): void {
            Route::get('/tien-do-cong-trinh', 'projectProgressReport')->name('progress');
            Route::get('/hieu-suat-nhan-su', 'performanceReport')->name('performance');
            Route::get('/cong-viec-qua-han', 'overdueTasksReport')->name('overdue');
            Route::get('/phat-sinh', 'incidentReport')->name('incidents');
        });

        Route::get('/cong-viec', 'tasks')->name('tasks');
        Route::get('/bao-cao-cu', 'report')->name('report');
        Route::get('/bao-hanh', 'maintenanceSchedule')->name('warranty');
        Route::get('/ho-so-bien-ban', 'documents')->name('documents');
    });
