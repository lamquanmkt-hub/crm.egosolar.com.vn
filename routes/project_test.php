<?php

use App\Http\Controllers\Projects\ProjectTestController;
use App\Http\Controllers\Projects\ProjectTestWarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::prefix('cong-trinh-test-new')->name('project-test.')->controller(ProjectTestController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/tao', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{project}', 'show')->whereNumber('project')->name('show');
        Route::post('/{project}/chinh-sua-thong-tin', 'updateBasic')->whereNumber('project')->name('update-basic');
        Route::post('/{project}/nhan-su-phu-trach', 'updatePeople')->whereNumber('project')->name('people.update');
        Route::post('/{project}/khao-sat/chinh-sua', 'updateSurveyData')->whereNumber('project')->name('survey.update');
        Route::post('/{project}/vat-tu/{materialRequest}/chinh-sua', 'updateMaterialRequest')->whereNumber('project')->whereNumber('materialRequest')->name('materials.update');
        Route::post('/{project}/nhat-ky/{dailyLog}/chinh-sua', 'updateDailyLog')->whereNumber('project')->whereNumber('dailyLog')->name('logs.update');
        Route::post('/{project}/nghiem-thu/chinh-sua', 'updateAcceptanceData')->whereNumber('project')->name('acceptance.update');
        Route::post('/{project}/lich-khao-sat/duyet', 'reviewSurveySchedule')->whereNumber('project')->name('survey.review');
        Route::post('/{project}/lich-khao-sat/sales-doi', 'salesRescheduleSurvey')->whereNumber('project')->name('survey.sales-reschedule');
        Route::post('/{project}/khao-sat/hoan-tat', 'submitSurvey')->whereNumber('project')->name('survey.submit');
        Route::post('/{project}/phuong-an/sales-duyet', 'salesReviewProposal')->whereNumber('project')->name('proposal.sales-review');
        Route::post('/{project}/lich-thi-cong/duyet', 'reviewInstallationSchedule')->whereNumber('project')->name('installation.review');
        Route::post('/{project}/lich-thi-cong/sales-doi', 'salesRescheduleInstallation')->whereNumber('project')->name('installation.sales-reschedule');
        Route::post('/{project}/vat-tu', 'submitMaterials')->whereNumber('project')->name('materials.submit');
        Route::post('/{project}/vat-tu/{materialRequest}/admin-duyet', 'reviewMaterials')->whereNumber('project')->whereNumber('materialRequest')->name('materials.review');
        Route::post('/{project}/phan-cong', 'assignTeam')->whereNumber('project')->name('assign');
        Route::post('/{project}/bat-dau-thi-cong', 'startInstallation')->whereNumber('project')->name('installation.start');
        Route::post('/{project}/nhat-ky', 'storeDailyLog')->whereNumber('project')->name('logs.store');
        Route::post('/{project}/nghiem-thu', 'accept')->whereNumber('project')->name('accept');
        Route::get('/{project}/file/{kind}', 'download')->whereNumber('project')->whereIn('kind', ['design-3d', 'survey', 'acceptance'])->name('download');
    });

    Route::prefix('san-pham-kho/xuat-cong-trinh-test')->name('project-test.warehouse.')->controller(ProjectTestWarehouseController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{materialRequest}', 'show')->whereNumber('materialRequest')->name('show');
        Route::get('/{materialRequest}/san-pham', 'products')->whereNumber('materialRequest')->name('products');
        Route::get('/{materialRequest}/kho-ton', 'warehouses')->whereNumber('materialRequest')->name('warehouses');
        Route::get('/{materialRequest}/serial', 'serials')->whereNumber('materialRequest')->name('serials');
        Route::post('/{materialRequest}/ghep-hang', 'saveMapping')->whereNumber('materialRequest')->name('mapping.save');
        Route::post('/{materialRequest}/giu-hang', 'reserve')->whereNumber('materialRequest')->name('reserve');
        Route::post('/{materialRequest}/bo-giu-hang', 'release')->whereNumber('materialRequest')->name('release');
        Route::post('/{materialRequest}/xuat', 'issue')->whereNumber('materialRequest')->name('issue');
    });
});
