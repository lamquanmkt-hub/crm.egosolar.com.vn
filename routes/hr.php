<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Hr\DashboardController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\EmployeeExtraController;
use App\Http\Controllers\Hr\DepartmentController;
use App\Http\Controllers\Hr\PositionController;
use App\Http\Controllers\Hr\LeaveRequestController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\AttendanceSettingController;
use App\Http\Controllers\Hr\OvertimeRequestController;
use App\Http\Controllers\Hr\OfficeExpenseController;
use App\Http\Controllers\Hr\HrDocumentController;
use App\Http\Controllers\Hr\HcOperationController;
use App\Http\Controllers\Hr\RecruitmentController;
use App\Http\Controllers\Hr\CandidateProcessController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::middleware(['auth'])->prefix('nhan-su')->name('hr.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Quy trình tuyển dụng ứng viên
    |--------------------------------------------------------------------------
    */
    Route::prefix('tuyen-dung')->name('recruitment.')->group(function () {
        Route::get('/', [RecruitmentController::class, 'index'])->name('index');

        Route::get('yeu-cau', [RecruitmentController::class, 'requests'])->name('requests');
        Route::post('yeu-cau', [RecruitmentController::class, 'storeRequest'])->name('requests.store');
        Route::put('yeu-cau/{id}', [RecruitmentController::class, 'updateRequest'])->whereNumber('id')->name('requests.update');
        Route::delete('yeu-cau/{id}', [RecruitmentController::class, 'destroyRequest'])->whereNumber('id')->name('requests.destroy');

        Route::get('sang-loc', [RecruitmentController::class, 'screening'])->name('screening');
        Route::get('kho-ung-vien', [RecruitmentController::class, 'candidates'])->name('candidates');
        Route::post('kho-ung-vien', [RecruitmentController::class, 'storeCandidate'])->name('candidates.store');
        Route::put('kho-ung-vien/{id}', [RecruitmentController::class, 'updateCandidate'])->whereNumber('id')->name('candidates.update');
        Route::delete('kho-ung-vien/{id}', [RecruitmentController::class, 'destroyCandidate'])->whereNumber('id')->name('candidates.destroy');

        Route::get('lich-phong-van', [RecruitmentController::class, 'interviews'])->name('interviews');
        Route::post('lich-phong-van', [RecruitmentController::class, 'storeInterview'])->name('interviews.store');
        Route::put('lich-phong-van/{id}', [RecruitmentController::class, 'updateInterview'])->whereNumber('id')->name('interviews.update');
        Route::delete('lich-phong-van/{id}', [RecruitmentController::class, 'destroyInterview'])->whereNumber('id')->name('interviews.destroy');

        Route::get('danh-gia', [RecruitmentController::class, 'evaluations'])->name('evaluations');

        Route::get('de-nghi-nhan-viec', [RecruitmentController::class, 'offers'])->name('offers');
        Route::post('de-nghi-nhan-viec', [RecruitmentController::class, 'storeOffer'])->name('offers.store');
        Route::put('de-nghi-nhan-viec/{id}', [RecruitmentController::class, 'updateOffer'])->whereNumber('id')->name('offers.update');
        Route::delete('de-nghi-nhan-viec/{id}', [RecruitmentController::class, 'destroyOffer'])->whereNumber('id')->name('offers.destroy');

        Route::get('tiep-nhan', [RecruitmentController::class, 'onboarding'])->name('onboarding');
        Route::get('luu-ho-so', [RecruitmentController::class, 'archives'])->name('archives');
        Route::get('bao-cao', [RecruitmentController::class, 'reports'])->name('reports');
    });

    Route::get('quy-trinh-ung-vien', function () {
        return redirect()->route('hr.recruitment.index');
    })->name('candidate-processes.redirect');

    Route::get('quy-trinh-nhan-su', function () {
        return redirect()->route('hr.recruitment.index');
    })->name('processes.redirect');



    Route::get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Chi phí văn phòng
    |--------------------------------------------------------------------------
    */
    Route::get('chi-phi-vp', [OfficeExpenseController::class, 'index'])
        ->name('office-expenses.index');

    Route::post('chi-phi-vp', [OfficeExpenseController::class, 'store'])
        ->name('office-expenses.store');

    Route::delete('chi-phi-vp/{id}', [OfficeExpenseController::class, 'destroy'])
        ->whereNumber('id')
        ->name('office-expenses.destroy');

    Route::post('chi-phi-vp/categories', [OfficeExpenseController::class, 'storeCategory'])
        ->name('office-expenses.categories.store');

    Route::delete('chi-phi-vp/categories/{id}', [OfficeExpenseController::class, 'destroyCategory'])
        ->whereNumber('id')
        ->name('office-expenses.categories.destroy');



    /*
    |--------------------------------------------------------------------------
    | HC & Vận Hành / Hồ sơ nhân sự
    |--------------------------------------------------------------------------
    */
    Route::get('hc-van-hanh', [HcOperationController::class, 'index'])
        ->name('operations.index');

    

    /*
    |--------------------------------------------------------------------------
    | HC & Vận hành - Chi phí / Tài sản / NCC / Việc phát sinh
    |--------------------------------------------------------------------------
    */
    Route::post('hc-van-hanh/expenses', [HcOperationController::class, 'storeExpense'])
        ->name('operations.expenses.store');
    Route::put('hc-van-hanh/expenses/{id}', [HcOperationController::class, 'updateExpense'])
        ->whereNumber('id')
        ->name('operations.expenses.update');
    Route::delete('hc-van-hanh/expenses/{id}', [HcOperationController::class, 'destroyExpense'])
        ->whereNumber('id')
        ->name('operations.expenses.destroy');

    Route::post('hc-van-hanh/assets', [HcOperationController::class, 'storeAsset'])
        ->name('operations.assets.store');
    Route::put('hc-van-hanh/assets/{id}', [HcOperationController::class, 'updateAsset'])
        ->whereNumber('id')
        ->name('operations.assets.update');
    Route::delete('hc-van-hanh/assets/{id}', [HcOperationController::class, 'destroyAsset'])
        ->whereNumber('id')
        ->name('operations.assets.destroy');

    Route::post('hc-van-hanh/suppliers', [HcOperationController::class, 'storeSupplier'])
        ->name('operations.suppliers.store');
    Route::put('hc-van-hanh/suppliers/{id}', [HcOperationController::class, 'updateSupplier'])
        ->whereNumber('id')
        ->name('operations.suppliers.update');
    Route::delete('hc-van-hanh/suppliers/{id}', [HcOperationController::class, 'destroySupplier'])
        ->whereNumber('id')
        ->name('operations.suppliers.destroy');

    Route::post('hc-van-hanh/tasks', [HcOperationController::class, 'storeTask'])
        ->name('operations.tasks.store');
    Route::put('hc-van-hanh/tasks/{id}', [HcOperationController::class, 'updateTask'])
        ->whereNumber('id')
        ->name('operations.tasks.update');
    Route::delete('hc-van-hanh/tasks/{id}', [HcOperationController::class, 'destroyTask'])
        ->whereNumber('id')
        ->name('operations.tasks.destroy');


    Route::post('hc-van-hanh/maintenance', [HcOperationController::class, 'storeMaintenance'])
        ->name('operations.maintenance.store');
    Route::put('hc-van-hanh/maintenance/{id}', [HcOperationController::class, 'updateMaintenance'])
        ->whereNumber('id')
        ->name('operations.maintenance.update');
    Route::delete('hc-van-hanh/maintenance/{id}', [HcOperationController::class, 'destroyMaintenance'])
        ->whereNumber('id')
        ->name('operations.maintenance.destroy');

Route::post('hc-van-hanh/items', [HrDocumentController::class, 'storeOperationItem'])
        ->name('operations.items.store');

    Route::put('hc-van-hanh/items/{item}', [HrDocumentController::class, 'updateOperationItem'])
        ->whereNumber('item')
        ->name('operations.items.update');

    Route::delete('hc-van-hanh/items/{item}', [HrDocumentController::class, 'deleteOperationItem'])
        ->whereNumber('item')
        ->name('operations.items.delete');

    Route::post('hc-van-hanh/groups', [HrDocumentController::class, 'storeOperationGroup'])
        ->name('operations.groups.store');

    Route::put('hc-van-hanh/groups/{group}', [HrDocumentController::class, 'updateOperationGroup'])
        ->whereNumber('group')
        ->name('operations.groups.update');

    Route::delete('hc-van-hanh/groups/{group}', [HrDocumentController::class, 'deleteOperationGroup'])
        ->whereNumber('group')
        ->name('operations.groups.delete');

    Route::post('hc-van-hanh/statuses', [HrDocumentController::class, 'storeOperationStatus'])
        ->name('operations.statuses.store');

    Route::put('hc-van-hanh/statuses/{status}', [HrDocumentController::class, 'updateOperationStatus'])
        ->whereNumber('status')
        ->name('operations.statuses.update');

    Route::delete('hc-van-hanh/statuses/{status}', [HrDocumentController::class, 'deleteOperationStatus'])
        ->whereNumber('status')
        ->name('operations.statuses.delete');

    // Redirect link cũ về module đúng
    Route::get('quy-trinh-nhan-su', function () {
        return redirect()->route('hr.candidate-processes.index');
    })->name('processes.old-redirect');

    /*
    |--------------------------------------------------------------------------
    | Quy trình ứng viên
    |--------------------------------------------------------------------------
    */
    Route::get('quy-trinh-ung-vien', [CandidateProcessController::class, 'index'])
        ->name('candidate-processes.index');

    Route::post('quy-trinh-ung-vien', [CandidateProcessController::class, 'store'])
        ->name('candidate-processes.store');

    Route::put('quy-trinh-ung-vien/{item}', [CandidateProcessController::class, 'update'])
        ->whereNumber('item')
        ->name('candidate-processes.update');

    Route::delete('quy-trinh-ung-vien/{item}', [CandidateProcessController::class, 'destroy'])
        ->whereNumber('item')
        ->name('candidate-processes.destroy');

    Route::post('quy-trinh-ung-vien/rounds', [CandidateProcessController::class, 'storeRound'])
        ->name('candidate-processes.rounds.store');

    Route::put('quy-trinh-ung-vien/rounds/{round}', [CandidateProcessController::class, 'updateRound'])
        ->whereNumber('round')
        ->name('candidate-processes.rounds.update');

    Route::delete('quy-trinh-ung-vien/rounds/{round}', [CandidateProcessController::class, 'destroyRound'])
        ->whereNumber('round')
        ->name('candidate-processes.rounds.destroy');

    Route::get('ho-so-nhan-su', [HrDocumentController::class, 'records'])
        ->name('records.index');

    Route::post('documents/{category}/folders', [HrDocumentController::class, 'storeFolder'])
        ->whereIn('category', ['operations', 'records'])
        ->name('documents.folders.store');

    Route::post('documents/{category}/files', [HrDocumentController::class, 'uploadFile'])
        ->whereIn('category', ['operations', 'records'])
        ->name('documents.files.store');

    Route::get('documents/files/{file}/download', [HrDocumentController::class, 'downloadFile'])
        ->whereNumber('file')
        ->name('documents.files.download');

    Route::delete('documents/files/{file}', [HrDocumentController::class, 'deleteFile'])
        ->whereNumber('file')
        ->name('documents.files.delete');

    Route::delete('documents/folders/{folder}', [HrDocumentController::class, 'deleteFolder'])
        ->whereNumber('folder')
        ->name('documents.folders.delete');


    Route::middleware(['role:admin|accounting|hr|hr'])->group(function () {
        Route::resource('departments', DepartmentController::class)
            ->except(['show']);

        Route::resource('positions', PositionController::class)
            ->except(['show']);

        Route::resource('employees', EmployeeController::class)
            ->middleware(['role:admin|hr|accounting|ketoan|ke_toan']);

        Route::put('employees/{employee}/extras', [EmployeeExtraController::class, 'update'])
            ->whereNumber('employee')
            ->middleware(['role:admin|hr|accounting|ketoan|ke_toan'])
            ->name('employees.extras.update');

        Route::post('employees/{employee}/files', [EmployeeExtraController::class, 'uploadFile'])
            ->whereNumber('employee')
            ->middleware(['role:admin|hr|accounting|ketoan|ke_toan'])
            ->name('employees.files.store');

        Route::get('employees/files/{file}/download', [EmployeeExtraController::class, 'downloadFile'])
            ->whereNumber('file')
            ->middleware(['role:admin|hr|accounting|ketoan|ke_toan'])
            ->name('employees.files.download');

        Route::delete('employees/files/{file}', [EmployeeExtraController::class, 'deleteFile'])
            ->whereNumber('file')
            ->middleware(['role:admin|hr|accounting|ketoan|ke_toan'])
            ->name('employees.files.delete');


    });

    Route::get('org-chart', [EmployeeController::class, 'orgChart'])
        ->name('org-chart');

    /*
    |--------------------------------------------------------------------------
    | Đơn nghỉ phép
    |--------------------------------------------------------------------------
    */
    Route::get('leave-requests', [LeaveRequestController::class, 'index'])
        ->name('leave.index');

    Route::get('leave-requests/create', [LeaveRequestController::class, 'create'])
        ->name('leave.create');

    Route::post('leave-requests', [LeaveRequestController::class, 'store'])
        ->name('leave.store');

    Route::post('leave-requests/{leave}/approve', [LeaveRequestController::class, 'approve'])
        ->name('leave.approve');

    Route::post('leave-requests/{leave}/reject', [LeaveRequestController::class, 'reject'])
        ->name('leave.reject');

    /*
    |--------------------------------------------------------------------------
    | Đơn xin làm online
    |--------------------------------------------------------------------------
    */
    Route::get('online-work/create', function () {
        return view('hr.online-work.create');
    })->name('online-work.create');

    /*
    |--------------------------------------------------------------------------
    | Chấm công
    |--------------------------------------------------------------------------
    */
    Route::get('cham-cong', [AttendanceController::class, 'index'])
        ->name('attendance.index');

    Route::get('cham-cong/export-excel', [AttendanceController::class, 'exportExcel'])
        ->name('attendance.export');

    Route::get('cham-cong/export-pdf', [AttendanceController::class, 'exportPdf'])
        ->name('attendance.export-pdf');

    Route::get('cham-cong-cua-toi', [AttendanceController::class, 'myAttendance'])
        ->name('attendance.my');

    Route::post('cham-cong/check-in', [AttendanceController::class, 'checkIn'])
        ->name('attendance.checkin');

    Route::post('cham-cong/check-out', [AttendanceController::class, 'checkOut'])
        ->name('attendance.checkout');

    /*
    |--------------------------------------------------------------------------
    | Đăng ký tăng ca
    |--------------------------------------------------------------------------
    */
    Route::get('tang-ca', [OvertimeRequestController::class, 'index'])
        ->name('overtime.index');

    Route::get('tang-ca/create', [OvertimeRequestController::class, 'create'])
        ->name('overtime.create');

    Route::post('tang-ca', [OvertimeRequestController::class, 'store'])
        ->name('overtime.store');

    Route::post('tang-ca/{overtime}/approve', [OvertimeRequestController::class, 'approve'])
        ->whereNumber('overtime')
        ->name('overtime.approve');

    Route::post('tang-ca/{overtime}/reject', [OvertimeRequestController::class, 'reject'])
        ->whereNumber('overtime')
        ->name('overtime.reject');

    /*
    |--------------------------------------------------------------------------
    | Hướng dẫn chấm công
    |--------------------------------------------------------------------------
    */
    Route::get('huong-dan-cham-cong/dien-thoai', function () {
        return view('hr.attendance.guides.mobile');
    })->name('attendance.guide.mobile');

    Route::get('huong-dan-cham-cong/may-tinh', function () {
        return view('hr.attendance.guides.desktop');
    })->name('attendance.guide.desktop');

    /*
    |--------------------------------------------------------------------------
    | Cài đặt chấm công
    |--------------------------------------------------------------------------
    */
    Route::get('cham-cong/settings', [AttendanceSettingController::class, 'edit'])
        ->middleware(['role:admin|hr'])
        ->name('attendance.settings');

    Route::post('cham-cong/settings', [AttendanceSettingController::class, 'update'])
        ->middleware(['role:admin|hr'])
        ->name('attendance.settings.update');
    Route::get('quy-trinh-phan-bo-vpp', [\App\Http\Controllers\Hr\OfficeSupplyProcessController::class, 'index'])->name('office-supply-process.index');

    Route::prefix('quy-trinh-giao-nhan-ho-so')
        ->name('document-handovers.')
        ->controller(\App\Http\Controllers\Hr\DocumentHandoverController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::put('/{id}', 'update')->whereNumber('id')->name('update');
            Route::post('/{id}/status', 'changeStatus')->whereNumber('id')->name('status');
            Route::post('/{id}/files', 'uploadFiles')->whereNumber('id')->name('files.upload');
            Route::get('/{id}/files/{fileId}/preview', 'previewFile')->whereNumber('id')->whereNumber('fileId')->name('files.preview');
            Route::get('/{id}/files/{fileId}/download', 'downloadFile')->whereNumber('id')->whereNumber('fileId')->name('files.download');
            Route::delete('/{id}/files/{fileId}', 'deleteFile')->whereNumber('id')->whereNumber('fileId')->name('files.delete');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        });

});