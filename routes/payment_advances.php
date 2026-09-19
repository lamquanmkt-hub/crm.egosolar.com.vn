<?php

use App\Http\Controllers\Finance\PaymentAdvanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('payment-requests')->group(function () {
    Route::get('/tam-ung-hoan-ung', [PaymentAdvanceController::class, 'advanceIndex'])
        ->name('payment_advances.index');
    Route::post('/tam-ung-hoan-ung', [PaymentAdvanceController::class, 'advanceStore'])
        ->name('payment_advances.store');
    Route::post('/tam-ung-hoan-ung/tao-hoan-ung-tu-dntt', [PaymentAdvanceController::class, 'settlementImportFromPaymentRequest'])
        ->name('payment_advances.settlement.import_from_payment_request');
    Route::get('/tam-ung-hoan-ung/{id}', [PaymentAdvanceController::class, 'advanceShow'])
        ->whereNumber('id')
        ->name('payment_advances.show');

    // Nhân viên hoàn ứng ngay trong hồ sơ tạm ứng.
    Route::post('/tam-ung-hoan-ung/{id}/quyet-toan', [PaymentAdvanceController::class, 'settlementStore'])
        ->whereNumber('id')
        ->name('payment_advances.settlement.store');

    // Luồng hoàn ứng: Kế toán đối soát -> QL tài chính duyệt -> Hoàn tất.
    Route::post('/tam-ung-hoan-ung/{id}/ql-tai-chinh-duyet-hoan-ung', [PaymentAdvanceController::class, 'settlementManagementApprove'])
        ->whereNumber('id')
        ->name('payment_advances.settlement.management_approve');
    Route::post('/tam-ung-hoan-ung/{id}/ql-tai-chinh-tu-choi-hoan-ung', [PaymentAdvanceController::class, 'settlementManagementReject'])
        ->whereNumber('id')
        ->name('payment_advances.settlement.management_reject');
    Route::post('/tam-ung-hoan-ung/{id}/ke-toan-doi-soat', [PaymentAdvanceController::class, 'settlementAccountingCheck'])
        ->whereNumber('id')
        ->name('payment_advances.settlement.accounting_check');
    Route::post('/tam-ung-hoan-ung/{id}/ke-toan-tra-hoan-ung', [PaymentAdvanceController::class, 'settlementAccountingReturn'])
        ->whereNumber('id')
        ->name('payment_advances.settlement.accounting_return');

    // Giữ route cũ để link/bookmark cũ không hỏng.
    Route::post('/tam-ung-hoan-ung/{id}/ke-toan-xac-nhan', [PaymentAdvanceController::class, 'settlementCheck'])
        ->whereNumber('id')
        ->name('payment_advances.settlement.check');
    Route::post('/tam-ung-hoan-ung/{id}/tra-lai', [PaymentAdvanceController::class, 'settlementReturn'])
        ->whereNumber('id')
        ->name('payment_advances.settlement.return');
    Route::post('/tam-ung-hoan-ung/{id}/hoan-tat', [PaymentAdvanceController::class, 'settlementComplete'])
        ->whereNumber('id')
        ->name('payment_advances.settlement.complete');

    Route::get('/tam-ung-luong', [PaymentAdvanceController::class, 'salaryIndex'])
        ->name('salary_advances.index');
    Route::post('/tam-ung-luong', [PaymentAdvanceController::class, 'salaryStore'])
        ->name('salary_advances.store');
    Route::get('/tam-ung-luong/{id}', [PaymentAdvanceController::class, 'salaryShow'])
        ->whereNumber('id')
        ->name('salary_advances.show');
});
