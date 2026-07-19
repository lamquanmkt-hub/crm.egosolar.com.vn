<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Hr\DashboardController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\DepartmentController;
use App\Http\Controllers\Hr\PositionController;
use App\Http\Controllers\Hr\LeaveRequestController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\AttendanceSettingController;

Route::middleware(['auth'])->prefix('nhan-su')->name('hr.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware(['role:admin|accounting'])->group(function () {
        Route::resource('departments', DepartmentController::class)
            ->except(['show']);

        Route::resource('positions', PositionController::class)
            ->except(['show']);

        Route::resource('employees', EmployeeController::class);
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

    Route::get('cham-cong-cua-toi', [AttendanceController::class, 'myAttendance'])
        ->name('attendance.my');

    Route::post('cham-cong/check-in', [AttendanceController::class, 'checkIn'])
        ->name('attendance.checkin');

    Route::post('cham-cong/check-out', [AttendanceController::class, 'checkOut'])
        ->name('attendance.checkout');

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
        ->middleware(['role:admin'])
        ->name('attendance.settings');

    Route::post('cham-cong/settings', [AttendanceSettingController::class, 'update'])
        ->middleware(['role:admin'])
        ->name('attendance.settings.update');
});