<?php

use App\Http\Controllers\Hr\BusinessTripController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('lich-cong-tac')
    ->name('business-trips.')
    ->controller(BusinessTripController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{businessTrip}/phe-duyet', 'approve')->whereNumber('businessTrip')->name('approve');
        Route::post('/{businessTrip}/tu-choi', 'reject')->whereNumber('businessTrip')->name('reject');
        Route::post('/{businessTrip}/hoan-tat', 'complete')->whereNumber('businessTrip')->name('complete');
        Route::post('/{businessTrip}/huy', 'cancel')->whereNumber('businessTrip')->name('cancel');
    });
