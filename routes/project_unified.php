<?php

use App\Http\Controllers\Projects\ProjectWorkflowV2Controller;
use App\Http\Controllers\Projects\UnifiedProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', \App\Http\Middleware\EnsureUnifiedProjectAccess::class])
    ->prefix('du-an')
    ->name('projects-unified.')
    ->controller(UnifiedProjectController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::get('/san-pham/tim-kiem', 'searchMaterialProducts')->name('materials.products.search');
        Route::post('/', 'store')->name('store');
        Route::get('/{site}', 'show')->whereNumber('site')->name('show');
        Route::post('/{site}/giai-doan', 'updatePhase')->whereNumber('site')->name('phase.update');
        Route::post('/{site}/giai-doan/gui-duyet', 'submitPhaseForApproval')->whereNumber('site')->name('phase.submit');
        Route::post('/{site}/giai-doan/duyet', 'approvePhase')->whereNumber('site')->name('phase.approve');
        Route::post('/{site}/giai-doan/yeu-cau-bo-sung', 'requestPhaseRevision')->whereNumber('site')->name('phase.revision');
        Route::post('/{site}/phan-cong', 'assignEngineer')->whereNumber('site')->name('engineer.update');
        Route::post('/{site}/kich-hoat-bao-tri-bao-hanh', [ProjectWorkflowV2Controller::class, 'activateMaintenance'])
            ->whereNumber('site')->name('maintenance.activate');

        /* EGO_PROJECT_WORKFLOW_V2_START */
        Route::prefix('/{site}/quy-trinh/{step}')
            ->whereNumber('site')
            ->whereIn('step', ['survey', 'proposal', 'contract', 'legal', 'construction', 'acceptance', 'warranty'])
            ->name('workflow.')
            ->controller(ProjectWorkflowV2Controller::class)
            ->group(function (): void {
                Route::post('/phan-cong', 'assign')->name('assign');
                Route::post('/nhan-viec', 'accept')->name('accept');
                Route::post('/bat-dau', 'start')->name('start');
                Route::post('/tien-do', 'progress')->name('progress');
                Route::post('/xu-ly-tre', 'updateDelayPlan')->name('delay.update');
                Route::post('/ho-so', 'uploadDocument')->name('documents.upload');
                Route::post('/nop-ket-qua', 'submitAssignment')->name('assignment.submit');
                Route::post('/duyet', 'approve')->name('approve');
                Route::post('/tra-lai', 'revise')->name('revise');
                Route::post('/du-lieu', 'saveData')->name('data.save');
                Route::post('/ngoai-le', 'requestException')->name('exception');
            });

        Route::post('/{site}/vat-tu/de-xuat/{proposal}/admin-duyet', [ProjectWorkflowV2Controller::class, 'approveMaterialAdmin'])
            ->whereNumber('site')->whereNumber('proposal')->name('materials.proposal.admin-approve');
        Route::post('/{site}/vat-tu/de-xuat/{proposal}/xac-nhan-nhan', [ProjectWorkflowV2Controller::class, 'confirmMaterialReceipt'])
            ->whereNumber('site')->whereNumber('proposal')->name('materials.proposal.receipt-confirm');
        Route::post('/{site}/vat-tu/de-xuat/{proposal}/giam-sat-xac-nhan', [ProjectWorkflowV2Controller::class, 'confirmMaterialSupervisor'])
            ->whereNumber('site')->whereNumber('proposal')->name('materials.proposal.supervisor-confirm');
        /* EGO_PROJECT_WORKFLOW_V2_END */

        Route::get('/{site}/vat-tu/de-xuat/mau-excel', 'materialProposalExcelTemplate')->whereNumber('site')->name('materials.proposal.excel-template');
        Route::post('/{site}/vat-tu/de-xuat/xem-truoc-excel', 'previewMaterialProposalExcel')->whereNumber('site')->name('materials.proposal.excel-preview');
        Route::post('/{site}/vat-tu/de-xuat', 'storeMaterialProposal')->whereNumber('site')->name('materials.proposal.store');
        Route::post('/{site}/vat-tu/de-xuat/{proposal}/duyet', 'approveMaterialProposal')->whereNumber('site')->whereNumber('proposal')->name('materials.proposal.approve');
        Route::post('/{site}/vat-tu/de-xuat/{proposal}/tra-lai', 'returnMaterialProposal')->whereNumber('site')->whereNumber('proposal')->name('materials.proposal.return');
        Route::post('/{site}/vat-tu/de-xuat/{proposal}/chon-hang', 'allocateMaterialProposal')->whereNumber('site')->whereNumber('proposal')->name('materials.proposal.allocate');
        Route::post('/{site}/vat-tu/de-xuat/{proposal}/don-xuat', 'createMaterialRequestFromProposal')->whereNumber('site')->whereNumber('proposal')->name('materials.proposal.material-request');

        Route::post('/{site}/tai-chinh/admin-cap-nhat', 'updateAdminProjectFinance')->whereNumber('site')->name('finance.admin.update');
        Route::post('/{site}/tai-chinh/dot-thanh-toan', 'storeProjectPaymentTerm')->whereNumber('site')->name('finance.term.store');
        Route::post('/{site}/tai-chinh/ghi-nhan', 'recordProjectPayment')->whereNumber('site')->name('finance.payment.store');
        Route::put('/{site}/tai-chinh/thanh-toan/{source}/{payment}', 'updateProjectPayment')->whereNumber('site')->whereNumber('payment')->whereIn('source', ['record', 'receipt'])->name('finance.payment.update');
        Route::post('/{site}/tai-chinh/chi-phi-khac', 'storeProjectFinanceExpense')->whereNumber('site')->name('finance.expense.store');
        Route::put('/{site}/tai-chinh/chi-phi-khac/{expense}', 'updateProjectFinanceExpense')->whereNumber('site')->whereNumber('expense')->name('finance.expense.update');
        /* EGO_PROJECT_MAINTENANCE_CANONICAL_ROUTES_START */
        Route::middleware('role:ky_thuat|technical|technician|technical_staff|technical_leader|technical_manager|accounting|admin|manager|warehouse|kho|sales|sales_manager|cskh')
            ->prefix('bao-tri-bao-hanh')
            ->name('maintenance.')
            ->group(function (): void {
                Route::get('/', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceController::class, 'index'])->name('index');
                Route::get('/sites/search', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceController::class, 'sitesSearch'])->name('sites-search');
                Route::post('/', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceController::class, 'store'])->name('store');

                Route::get('/cong-trinh/{site}', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceDetailController::class, 'site'])
                    ->whereNumber('site')->name('site');
                Route::post('/cong-trinh/{site}/files', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceAttachmentController::class, 'storeSite'])
                    ->whereNumber('site')->name('site-files.store');

                Route::get('/site-files/{document}/preview', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceAttachmentController::class, 'previewSite'])
                    ->whereNumber('document')->name('site-files.preview');
                Route::get('/site-files/{document}/download', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceAttachmentController::class, 'downloadSite'])
                    ->whereNumber('document')->name('site-files.download');
                Route::delete('/site-files/{document}', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceAttachmentController::class, 'destroySite'])
                    ->whereNumber('document')->name('site-files.destroy');

                Route::post('/{schedule}/files', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceAttachmentController::class, 'storeSchedule'])
                    ->whereNumber('schedule')->name('schedule-files.store');
                Route::get('/schedule-files/{attachment}/preview', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceAttachmentController::class, 'previewSchedule'])
                    ->whereNumber('attachment')->name('schedule-files.preview');
                Route::get('/schedule-files/{attachment}/download', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceAttachmentController::class, 'downloadSchedule'])
                    ->whereNumber('attachment')->name('schedule-files.download');
                Route::delete('/schedule-files/{attachment}', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceAttachmentController::class, 'destroySchedule'])
                    ->whereNumber('attachment')->name('schedule-files.destroy');

                Route::post('/{schedule}/cong-viec', [\App\Http\Controllers\Technical\SolarMaintenanceWorkItemController::class, 'store'])
                    ->whereNumber('schedule')->name('work-items.store');
                Route::put('/{schedule}/cong-viec/{workItem}', [\App\Http\Controllers\Technical\SolarMaintenanceWorkItemController::class, 'update'])
                    ->whereNumber('schedule')->whereNumber('workItem')->name('work-items.update');
                Route::delete('/{schedule}/cong-viec/{workItem}', [\App\Http\Controllers\Technical\SolarMaintenanceWorkItemController::class, 'destroy'])
                    ->whereNumber('schedule')->whereNumber('workItem')->name('work-items.destroy');

                Route::post('/{schedule}/binh-luan', [\App\Http\Controllers\Technical\SolarMaintenanceCommentController::class, 'store'])
                    ->whereNumber('schedule')->name('comments.store');
                Route::delete('/{schedule}/binh-luan/{comment}', [\App\Http\Controllers\Technical\SolarMaintenanceCommentController::class, 'destroy'])
                    ->whereNumber('schedule')->whereNumber('comment')->name('comments.destroy');

                Route::post('/{schedule}/gui-duyet', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceApprovalController::class, 'submit'])
                    ->whereNumber('schedule')->name('approval.submit');
                Route::post('/{schedule}/phan-cong/duyet', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceController::class, 'approveAssignment'])
                    ->whereNumber('schedule')->name('assignment.approve');
                Route::post('/{schedule}/phan-cong/nhan-viec', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceController::class, 'acceptAssignment'])
                    ->whereNumber('schedule')->name('assignment.accept');
                Route::post('/{schedule}/phe-duyet', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceApprovalController::class, 'approve'])
                    ->whereNumber('schedule')->name('approval.approve');
                Route::post('/{schedule}/hoan-thanh', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceApprovalController::class, 'complete'])
                    ->whereNumber('schedule')->name('approval.complete');
                Route::post('/{schedule}/yeu-cau-chinh-sua', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceApprovalController::class, 'requestRevision'])
                    ->whereNumber('schedule')->name('approval.revision');
                Route::post('/{schedule}/tu-choi', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceApprovalController::class, 'reject'])
                    ->whereNumber('schedule')->name('approval.reject');
                Route::post('/{schedule}/mo-lai', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceApprovalController::class, 'reopen'])
                    ->whereNumber('schedule')->name('approval.reopen');

                Route::post('/phieu-bao-hanh', [\App\Http\Controllers\Technical\SolarWarrantyClaimController::class, 'store'])
                    ->name('claims.store');
                Route::post('/phieu-bao-hanh/{claim}/status', [\App\Http\Controllers\Technical\SolarWarrantyClaimController::class, 'updateStatus'])
                    ->whereNumber('claim')->name('claims.status');
                Route::post('/kho-bao-hanh', [\App\Http\Controllers\Technical\SolarWarrantyStockController::class, 'store'])
                    ->name('stock.store');
                Route::post('/kho-bao-hanh/{movement}/status', [\App\Http\Controllers\Technical\SolarWarrantyStockController::class, 'updateStatus'])
                    ->whereNumber('movement')->name('stock.status');

                Route::get('/{schedule}/json', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceController::class, 'showJson'])
                    ->whereNumber('schedule')->name('json');
                Route::put('/{schedule}', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceController::class, 'update'])
                    ->whereNumber('schedule')->name('update');
                Route::post('/{schedule}/status', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceController::class, 'updateStatus'])
                    ->whereNumber('schedule')->name('status');
                Route::delete('/{schedule}', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceController::class, 'destroy'])
                    ->whereNumber('schedule')->name('destroy');
                Route::get('/{schedule}', [\App\Http\Controllers\Synced\Technical\SolarMaintenanceDetailController::class, 'show'])
                    ->whereNumber('schedule')->name('show');
            });
        /* EGO_PROJECT_MAINTENANCE_CANONICAL_ROUTES_END */
    });
