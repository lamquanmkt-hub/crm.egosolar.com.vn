<?php

use App\Http\Controllers\Technical\SolarMaintenanceIssueController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'role:management|ky_thuat|technical|technician|technical_staff|technical_leader|technical_manager|accounting|admin|manager|warehouse|kho|sales|sales_manager|cskh',
])
    ->prefix('ky-thuat/bao-tri-bao-hanh')
    ->name('ky-thuat.maintenance.')
    ->controller(SolarMaintenanceIssueController::class)
    ->group(function (): void {
        Route::get('/su-co', 'index')->name('issues');
        Route::post('/su-co', 'store')->name('issues.store');
        Route::put('/su-co/{claim}', 'update')->whereNumber('claim')->name('issues.update');
    });
