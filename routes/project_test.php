<?php

use App\Http\Controllers\Projects\LegacySiteRedirectController;
use App\Http\Controllers\Projects\ProjectTestController;
use App\Http\Controllers\Projects\ProjectPaymentController;
use App\Http\Controllers\Projects\ProjectExpenseController;
use App\Http\Controllers\Projects\ProjectMaterialAftercareController;
use App\Http\Controllers\Projects\ProjectTestWarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', \App\Http\Middleware\RetireLegacyProjectModule::class])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | ĐIỂM TẠO CÔNG TRÌNH TÁCH RIÊNG THEO PHÒNG BAN
    |--------------------------------------------------------------------------
    | Sales tạo và theo dõi hồ sơ thương mại của Sales.
    | Phòng Kỹ thuật tự tạo hồ sơ kỹ thuật/nội bộ không cần Sales.
    */
    Route::middleware(['role:admin|sales|sales_staff|sales_manager'])
        ->prefix('sales/cong-trinh')
        ->name('sales-projects.')
        ->controller(ProjectTestController::class)
        ->group(function (): void {
            Route::get('/', 'salesIndex')->name('index');
            Route::get('/tao', 'createSales')->name('create');
            Route::post('/', 'storeSales')->name('store');
        });

    Route::middleware(['role:admin|management|manager|technical_manager|technical_leader|technical|technical_staff|technician|ky_thuat'])
        ->prefix('ky-thuat/cong-trinh')
        ->name('technical-projects.')
        ->controller(ProjectTestController::class)
        ->group(function (): void {
            Route::get('/tu-sales', 'technicalFromSalesIndex')->name('from-sales');
            Route::get('/ky-thuat-tao', 'technicalCreatedIndex')->name('created');
            Route::get('/danh-sach-tong', 'technicalAllIndex')->name('all');
            Route::get('/ke-hoach-trien-khai', 'deploymentPlanIndex')->name('deployment');
            Route::get('/nghiem-thu-ban-giao', 'acceptanceHandoverIndex')->name('handover');
        });

    Route::middleware(['role:admin|technical_manager|technical_leader'])
        ->prefix('ky-thuat/cong-trinh')
        ->name('technical-projects.')
        ->controller(ProjectTestController::class)
        ->group(function (): void {
            Route::get('/tao', 'createTechnical')->name('create');
            Route::post('/', 'storeTechnical')->name('store');
        });

    /*
    |--------------------------------------------------------------------------
    | CÔNG TRÌNH CHÍNH
    |--------------------------------------------------------------------------
    | Module workflow mới chính thức thay thế toàn bộ giao diện Công trình cũ.
    | Tên route project-test.* được giữ nguyên để không phá form và permission.
    */
    Route::prefix('cong-trinh')->name('project-test.')->controller(ProjectTestController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/tao', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::delete('/{project}', 'destroy')->whereNumber('project')->name('destroy');
        Route::get('/{project}', 'show')->whereNumber('project')->name('show');
        Route::post('/{project}/chinh-sua-thong-tin', 'updateBasic')->whereNumber('project')->name('update-basic');
        Route::post('/{project}/tai-chinh', 'updateFinancials')->whereNumber('project')->name('finance.update');
        Route::post('/{project}/thanh-toan/dot', [ProjectPaymentController::class, 'storeMilestone'])->whereNumber('project')->name('payments.milestones.store');
        Route::put('/{project}/thanh-toan/dot/{milestone}', [ProjectPaymentController::class, 'updateMilestone'])->whereNumber('project')->whereNumber('milestone')->name('payments.milestones.update');
        Route::delete('/{project}/thanh-toan/dot/{milestone}', [ProjectPaymentController::class, 'destroyMilestone'])->whereNumber('project')->whereNumber('milestone')->name('payments.milestones.destroy');
        Route::post('/{project}/thanh-toan/giao-dich', [ProjectPaymentController::class, 'storeTransaction'])->whereNumber('project')->name('payments.transactions.store');        Route::put('/{project}/thanh-toan/giao-dich/{transaction}/so-tien', [ProjectPaymentController::class, 'updateTransactionAmount'])->whereNumber('project')->whereNumber('transaction')->name('payments.transactions.update-amount');
        Route::post('/{project}/thanh-toan/giao-dich/{transaction}/xac-nhan', [ProjectPaymentController::class, 'confirmTransaction'])->whereNumber('project')->whereNumber('transaction')->name('payments.transactions.confirm');
        Route::post('/{project}/thanh-toan/giao-dich/{transaction}/tu-choi', [ProjectPaymentController::class, 'rejectTransaction'])->whereNumber('project')->whereNumber('transaction')->name('payments.transactions.reject');
        Route::post('/{project}/thanh-toan/giao-dich/{transaction}/huy', [ProjectPaymentController::class, 'cancelTransaction'])->whereNumber('project')->whereNumber('transaction')->name('payments.transactions.cancel');
        Route::get('/{project}/thanh-toan/giao-dich/{transaction}/chung-tu', [ProjectPaymentController::class, 'proof'])->whereNumber('project')->whereNumber('transaction')->name('payments.transactions.proof');
        Route::post('/{project}/thanh-toan/dieu-chinh', [ProjectPaymentController::class, 'storeAdjustment'])->whereNumber('project')->name('payments.adjustments.store');
        Route::post('/{project}/thanh-toan/dieu-chinh/{adjustment}/duyet', [ProjectPaymentController::class, 'approveAdjustment'])->whereNumber('project')->whereNumber('adjustment')->name('payments.adjustments.approve');
        Route::post('/{project}/thanh-toan/dieu-chinh/{adjustment}/tu-choi', [ProjectPaymentController::class, 'rejectAdjustment'])->whereNumber('project')->whereNumber('adjustment')->name('payments.adjustments.reject');

        // Sổ chi phí công trình: Sales/Kho nhập, Admin/Kế toán xác nhận.
        Route::post('/{project}/chi-phi', [ProjectExpenseController::class, 'store'])->whereNumber('project')->name('expenses.store');
        Route::put('/{project}/chi-phi/{expense}', [ProjectExpenseController::class, 'update'])->whereNumber('project')->whereNumber('expense')->name('expenses.update');
        Route::post('/{project}/chi-phi/{expense}/xac-nhan', [ProjectExpenseController::class, 'confirm'])->whereNumber('project')->whereNumber('expense')->name('expenses.confirm');
        Route::post('/{project}/chi-phi/{expense}/tu-choi', [ProjectExpenseController::class, 'reject'])->whereNumber('project')->whereNumber('expense')->name('expenses.reject');
        Route::post('/{project}/chi-phi/{expense}/huy', [ProjectExpenseController::class, 'cancel'])->whereNumber('project')->whereNumber('expense')->name('expenses.cancel');
        Route::get('/{project}/chi-phi/{expense}/chung-tu', [ProjectExpenseController::class, 'proof'])->whereNumber('project')->whereNumber('expense')->name('expenses.proof');

        Route::post('/{project}/nhan-su-phu-trach', 'updatePeople')->whereNumber('project')->name('people.update');
        Route::post('/{project}/khao-sat/chinh-sua', 'updateSurveyData')->whereNumber('project')->name('survey.update');
        Route::post('/{project}/vat-tu/{materialRequest}/chinh-sua', 'updateMaterialRequest')->whereNumber('project')->whereNumber('materialRequest')->name('materials.update');
        Route::post('/{project}/nhat-ky/{dailyLog}/chinh-sua', 'updateDailyLog')->whereNumber('project')->whereNumber('dailyLog')->name('logs.update');
        Route::post('/{project}/nghiem-thu/chinh-sua', 'updateAcceptanceData')->whereNumber('project')->name('acceptance.update');
        Route::post('/{project}/tiep-nhan', 'acceptRequest')->whereNumber('project')->name('request.accept');
        Route::post('/{project}/lich-khao-sat/duyet', 'reviewSurveySchedule')->whereNumber('project')->name('survey.review');
        Route::post('/{project}/lich-khao-sat/cap-nhat', 'salesRescheduleSurvey')->whereNumber('project')->name('survey.reschedule');
        Route::post('/{project}/lich-khao-sat/sales-doi', 'salesRescheduleSurvey')->whereNumber('project')->name('survey.sales-reschedule');
        Route::post('/{project}/khao-sat/check-in', 'surveyCheckIn')->whereNumber('project')->name('survey.check-in');
        Route::post('/{project}/khao-sat/check-out', 'surveyCheckOut')->whereNumber('project')->name('survey.check-out');
        Route::post('/{project}/khao-sat/hoan-tat', 'submitSurvey')->whereNumber('project')->name('survey.submit');
        Route::post('/{project}/phuong-an/xac-nhan', 'salesReviewProposal')->whereNumber('project')->name('proposal.confirm');
        Route::post('/{project}/phuong-an/sales-duyet', 'salesReviewProposal')->whereNumber('project')->name('proposal.sales-review');
        Route::post('/{project}/lich-thi-cong/duyet', 'reviewInstallationSchedule')->whereNumber('project')->name('installation.review');
        Route::post('/{project}/lich-thi-cong/cap-nhat', 'salesRescheduleInstallation')->whereNumber('project')->name('installation.reschedule');
        Route::post('/{project}/lich-thi-cong/sales-doi', 'salesRescheduleInstallation')->whereNumber('project')->name('installation.sales-reschedule');
        Route::post('/{project}/vat-tu/nhap-excel', 'importMaterialExcel')->whereNumber('project')->name('materials.import-excel');
        Route::post('/{project}/vat-tu', 'submitMaterials')->whereNumber('project')->name('materials.submit');
        Route::post('/{project}/vat-tu/{materialRequest}/admin-duyet', 'reviewMaterials')->whereNumber('project')->whereNumber('materialRequest')->name('materials.review');
        Route::post('/{project}/vat-tu/{materialRequest}/sau-xuat-kho', [ProjectMaterialAftercareController::class, 'store'])->whereNumber('project')->whereNumber('materialRequest')->name('materials.aftercare.store');
        Route::post('/{project}/vat-tu/sau-xuat-kho/{aftercare}/cap-nhat', [ProjectMaterialAftercareController::class, 'update'])->whereNumber('project')->whereNumber('aftercare')->name('materials.aftercare.update');
        Route::post('/{project}/vat-tu/sau-xuat-kho/{aftercare}/kho-kiem-tra', [ProjectMaterialAftercareController::class, 'warehouseReview'])->whereNumber('project')->whereNumber('aftercare')->name('materials.aftercare.warehouse-review');
        Route::post('/{project}/vat-tu/sau-xuat-kho/{aftercare}/quan-ly-duyet', [ProjectMaterialAftercareController::class, 'managerReview'])->whereNumber('project')->whereNumber('aftercare')->name('materials.aftercare.manager-review');
        Route::post('/{project}/vat-tu/sau-xuat-kho/{aftercare}/xu-ly-thu-hoi', [ProjectMaterialAftercareController::class, 'processReturn'])->whereNumber('project')->whereNumber('aftercare')->name('materials.aftercare.process-return');
        Route::post('/{project}/phan-cong', 'assignTeam')->whereNumber('project')->name('assign');
        Route::post('/{project}/bat-dau-thi-cong', 'startInstallation')->whereNumber('project')->name('installation.start');
        Route::post('/{project}/nhat-ky', 'storeDailyLog')->whereNumber('project')->name('logs.store');
        Route::post('/{project}/nghiem-thu', 'accept')->whereNumber('project')->name('accept');
        Route::get('/{project}/file/{kind}', 'download')->whereNumber('project')->whereIn('kind', ['design-3d', 'survey', 'acceptance'])->name('download');
    });

    Route::prefix('san-pham-kho/xuat-cong-trinh')->name('project-test.warehouse.')->controller(ProjectTestWarehouseController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{materialRequest}', 'show')->whereNumber('materialRequest')->name('show');
        Route::get('/{materialRequest}/san-pham', 'products')->whereNumber('materialRequest')->name('products');
        Route::get('/{materialRequest}/kho-ton', 'warehouses')->whereNumber('materialRequest')->name('warehouses');
        Route::get('/{materialRequest}/serial', 'serials')->whereNumber('materialRequest')->name('serials');
        Route::post('/{materialRequest}/kiem-tra-ton', 'saveMapping')->whereNumber('materialRequest')->name('mapping.save');
        Route::post('/{materialRequest}/dong-bo-ton', 'syncStock')->whereNumber('materialRequest')->name('stock.sync');
        Route::post('/{materialRequest}/nhap-bo-sung', 'quickReceive')->whereNumber('materialRequest')->name('stock.quick-receive');
        Route::post('/{materialRequest}/tra-ky-thuat', 'returnToTechnical')->whereNumber('materialRequest')->name('technical.return');
        Route::post('/{materialRequest}/gui-quan-ly-duyet', 'submitForManager')->whereNumber('materialRequest')->name('manager.submit');
        Route::post('/{materialRequest}/giu-hang', 'reserve')->whereNumber('materialRequest')->name('reserve');
        Route::post('/{materialRequest}/bo-giu-hang', 'release')->whereNumber('materialRequest')->name('release');
        Route::post('/{materialRequest}/xuat', 'issue')->whereNumber('materialRequest')->name('issue');
    });
    /* EGO_PROJECT_WAREHOUSE_360_V1_ROUTES */
    Route::prefix('kho/cong-trinh')->name('warehouse-projects.')->controller(ProjectTestWarehouseController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{materialRequest}', 'show')->whereNumber('materialRequest')->name('show');
    });

    /* URL Test cũ: chỉ chuyển hướng, không còn module riêng. */
    Route::get('/cong-trinh-test-new', fn () => redirect()->route('project-test.index'))->name('project-test-old-url.index');
    Route::get('/cong-trinh-test-new/tao', fn () => redirect()->route('project-test.create'))->name('project-test-old-url.create');
    Route::get('/cong-trinh-test-new/{project}', fn ($project) => redirect()->route('project-test.show', $project))->whereNumber('project')->name('project-test-old-url.show');
    Route::get('/san-pham-kho/xuat-cong-trinh-test', fn () => redirect()->route('project-test.warehouse.index'))->name('project-test-old-warehouse.index');

    /*
    |--------------------------------------------------------------------------
    | TƯƠNG THÍCH ROUTE CŨ
    |--------------------------------------------------------------------------
    | Các module khác từng gọi sites.* vẫn chạy, nhưng chỉ chuyển sang hồ sơ mới.
    | Không kích hoạt lại controller/view Công trình cũ.
    */
    Route::prefix('cong-trinh-cu-da-chuyen')->name('sites.')->controller(LegacySiteRedirectController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/tao', 'create')->name('create');
        Route::post('/', 'create')->name('store');
        Route::get('/file/{file}', 'index')->whereNumber('file')->name('file');
        Route::get('/{id}/sua', 'show')->whereNumber('id')->name('edit');
        Route::match(['post', 'put', 'patch'], '/{id}/cap-nhat', 'post')->whereNumber('id')->name('update');
        Route::post('/{id}/ghi-nhan-thanh-toan', 'post')->whereNumber('id')->name('record-payment');
        Route::match(['put', 'patch'], '/{id}/thanh-toan/{receipt}/cap-nhat', 'post')->whereNumber('id')->whereNumber('receipt')->name('payments.update');
        Route::match(['post', 'delete'], '/{id}/thanh-toan/{receipt}/xoa', 'post')->whereNumber('id')->whereNumber('receipt')->name('payments.destroy');
        Route::match(['post', 'delete'], '/{id}/xoa', 'post')->whereNumber('id')->name('destroy');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
    });
});

// EGO_MATERIAL_DECISION_V5: endpoint độc lập, không phụ thuộc JavaScript giao diện.
Route::middleware(['auth', \App\Http\Middleware\RetireLegacyProjectModule::class])->post(
    '/cong-trinh/{project}/vat-tu/{materialRequest}/quyet-dinh/{decision}',
    \App\Http\Controllers\Projects\ProjectMaterialDecisionController::class
)->whereNumber('project')
  ->whereNumber('materialRequest')
  ->whereIn('decision', ['approve', 'return_warehouse', 'return_technical'])
  ->name('project-test.materials.decision');
/* EGO_MATERIAL_WORKFLOW_V6_ROUTES_START */
Route::middleware(['auth', \App\Http\Middleware\RetireLegacyProjectModule::class])->group(function (): void {
    if (! Route::has('project-test.materials.submit')) {
        Route::post('/cong-trinh/{project}/vat-tu', [\App\Http\Controllers\Projects\ProjectTestController::class, 'submitMaterials'])
            ->whereNumber('project')
            ->name('project-test.materials.submit');
    }

    if (! Route::has('project-test.materials.update')) {
        Route::post('/cong-trinh/{project}/vat-tu/{materialRequest}/chinh-sua', [\App\Http\Controllers\Projects\ProjectTestController::class, 'updateMaterialRequest'])
            ->whereNumber('project')
            ->whereNumber('materialRequest')
            ->name('project-test.materials.update');
    }

    if (! Route::has('project-test.materials.review')) {
        Route::post('/cong-trinh/{project}/vat-tu/{materialRequest}/admin-duyet', [\App\Http\Controllers\Projects\ProjectTestController::class, 'reviewMaterials'])
            ->whereNumber('project')
            ->whereNumber('materialRequest')
            ->name('project-test.materials.review');
    }

    if (! Route::has('project-test.materials.decision')) {
        Route::post('/cong-trinh/{project}/vat-tu/{materialRequest}/quyet-dinh/{decision}', \App\Http\Controllers\Projects\ProjectMaterialDecisionController::class)
            ->whereNumber('project')
            ->whereNumber('materialRequest')
            ->whereIn('decision', ['approve', 'return_warehouse', 'return_technical'])
            ->name('project-test.materials.decision');
    }
});
/* EGO_MATERIAL_WORKFLOW_V6_ROUTES_END */