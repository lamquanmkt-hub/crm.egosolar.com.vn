<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Hợp đồng Nghiệp vụ công nợ nhà cung cấp: trạng thái đợt chi, đối chiếu ĐNTT, đồng bộ tổng đã trả.
 *
 * Sinh từ implementation SupplierDebtService của chính dự án này (KHÔNG copy từ crm-shop —
 * signature hai codebase đã phân kỳ).
 */
interface SupplierDebtServiceInterface
{
    public function companyOptions(): array;

    public function paidRoundStatuses(): array;

    public function pendingRoundStatuses(): array;

    public function paymentRequestRow($paymentRequestId);

    public function paymentRequestIsCompleted($paymentRequest): bool;

    public function roundPaymentMeta($round): array;

    public function remainingRoundAlreadyExists($round, float $remainingAmount): bool;

    public function normalizeMoneyInput($value): string;

    public function paymentRoundIsLocked($round): bool;

    public function syncSupplierDebtTotals(int $debtId): void;

    public function supplierDebtMonth($debt);

    public function supplierDebtPaymentReason($debt, $round);

    public function paymentRequestExistsForSupplierDebt($paymentRequestId): bool;

    public function clearMissingSupplierDebtPaymentRequests(array $roundIds = []): int;
}
