<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nghiệp vụ công nợ nhà cung cấp: phân loại trạng thái đợt thanh toán
 * (đã chi / đang chờ), đối chiếu với đề nghị thanh toán (ĐNTT), đồng bộ
 * tổng đã trả + trạng thái của công nợ gốc và chuẩn hoá số tiền nhập tay.
 *
 * Quy ước quan trọng (giữ nguyên từ production): đợt ở trạng thái 'planned'
 * KHÔNG tính vào paid_amount — chỉ trạng thái đã chi mới cộng vào.
 *
 * Tách nguyên trạng từ Finance\SupplierDebtController (P1d refactor) —
 * hành vi chốt bằng SupplierDebtCharacterizationTest.
 */
class SupplierDebtService
{
    /**
     * Danh sách công ty được phép chọn khi nhập công nợ.
     */
    public function companyOptions(): array
    {
        return [
            'Công ty TNHH Ego Việt Nam',
            'Công ty TNHH TMKT Quốc Tế EGO',
        ];
    }

    /**
     * Các trạng thái đợt thanh toán được tính là đã chi.
     */
    public function paidRoundStatuses(): array
    {
        // payment_requests.status = accounting_approved nghĩa là kế toán đã chi.
        return ['paid', 'accounting_approved'];
    }

    /**
     * Các trạng thái đợt thanh toán đang chờ xử lý.
     */
    public function pendingRoundStatuses(): array
    {
        return ['planned', 'requested', 'draft', 'submitted', 'pending', 'admin_approved'];
    }

    /**
     * Các trạng thái ĐNTT được xem là đã hoàn tất chi.
     */
    public function completedPaymentRequestStatuses(): array
    {
        return ['accounting_approved', 'paid', 'completed', 'complete', 'done', 'closed'];
    }

    /**
     * Các trạng thái ĐNTT còn đang mở chờ chi.
     */
    public function payablePaymentRequestStatuses(): array
    {
        return ['submitted', 'admin_approved', 'requested', 'draft', 'pending'];
    }

    /**
     * Lấy bản ghi ĐNTT theo id, bỏ qua bản ghi đã xóa mềm.
     */
    public function paymentRequestRow($paymentRequestId)
    {
        if (! $paymentRequestId || ! Schema::hasTable('payment_requests')) {
            return null;
        }

        $query = DB::table('payment_requests')->where('id', (int) $paymentRequestId);

        if (Schema::hasColumn('payment_requests', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->first();
    }

    /**
     * Kiểm tra ĐNTT đã ở trạng thái kế toán đã chi hay chưa.
     */
    public function paymentRequestIsCompleted($paymentRequest): bool
    {
        if (! $paymentRequest) {
            return false;
        }

        return in_array(strtolower((string) ($paymentRequest->status ?? '')), $this->completedPaymentRequestStatuses(), true);
    }

    /**
     * Kiểm tra ĐNTT còn đang mở (chưa chi) hay không.
     */
    public function paymentRequestIsOpen($paymentRequest): bool
    {
        if (! $paymentRequest) {
            return false;
        }

        return in_array(strtolower((string) ($paymentRequest->status ?? '')), $this->payablePaymentRequestStatuses(), true);
    }

    /**
     * Tính số đã chi / đang chờ / còn lại của một đợt thanh toán theo ĐNTT liên kết.
     */
    public function roundPaymentMeta($round): array
    {
        $roundAmount = (float) ($round->amount ?? 0);
        $paymentRequest = null;
        $paymentRequestAmount = null;
        $paymentRequestStatus = null;
        $paidAmount = 0.0;
        $pendingAmount = 0.0;
        $isLocked = false;
        $isPartial = false;
        $missing = false;

        if (! empty($round->payment_request_id)) {
            $paymentRequest = $this->paymentRequestRow((int) $round->payment_request_id);

            if ($paymentRequest) {
                $paymentRequestAmount = (float) ($paymentRequest->amount ?? 0);
                $paymentRequestStatus = strtolower((string) ($paymentRequest->status ?? ''));
                $isLocked = in_array($paymentRequestStatus, array_merge($this->completedPaymentRequestStatuses(), ['submitted', 'admin_approved']), true);

                if ($this->paymentRequestIsCompleted($paymentRequest)) {
                    $paidAmount = $paymentRequestAmount > 0
                        ? min($roundAmount, $paymentRequestAmount)
                        : $roundAmount;
                } else {
                    $pendingAmount = $roundAmount;
                }
            } else {
                $missing = true;
            }
        } else {
            $roundStatus = strtolower((string) ($round->status ?? ''));

            if (in_array($roundStatus, $this->paidRoundStatuses(), true)) {
                $paidAmount = $roundAmount;
            } elseif (in_array($roundStatus, $this->pendingRoundStatuses(), true)) {
                $pendingAmount = $roundAmount;
            }
        }

        $remainingAmount = max($roundAmount - $paidAmount, 0);
        $isPartial = $paidAmount > 0 && $remainingAmount > 0;

        return [
            'payment_request' => $paymentRequest,
            'payment_request_amount' => $paymentRequestAmount,
            'payment_request_status' => $paymentRequestStatus,
            'payment_request_missing' => $missing,
            'payment_request_is_locked' => $isLocked,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'remaining_amount' => $remainingAmount,
            'is_partial_paid' => $isPartial,
        ];
    }

    /**
     * Kiểm tra đã tồn tại đợt công nợ cho phần còn thiếu của đợt này chưa.
     */
    public function remainingRoundAlreadyExists($round, float $remainingAmount): bool
    {
        if ($remainingAmount <= 0 || ! Schema::hasTable('finance_supplier_debt_payments')) {
            return false;
        }

        return DB::table('finance_supplier_debt_payments')
            ->where('supplier_debt_id', (int) ($round->supplier_debt_id ?? 0))
            ->whereNull('payment_request_id')
            ->where(function ($q) use ($round, $remainingAmount) {
                $q->where('note', 'like', '%Phần còn lại của đợt '.($round->payment_round ?? '').'%')
                    ->orWhere(function ($x) use ($remainingAmount) {
                        $x->whereBetween('amount', [$remainingAmount - 1, $remainingAmount + 1])
                            ->whereIn('status', ['planned', 'draft', 'requested', 'pending']);
                    });
            })
            ->exists();
    }

    /**
     * Chuẩn hóa chuỗi tiền tệ nhập tay (dấu chấm / phẩy) về dạng số thuần.
     */
    public function normalizeMoneyInput($value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[\s\x{00A0}]+/u', '', $value) ?? '';
        $value = preg_replace('/[^0-9,.-]/u', '', $value) ?? '';

        if ($value === '' || $value === '-' || $value === ',' || $value === '.') {
            return '';
        }

        $isNegative = substr($value, 0, 1) === '-';
        $value = ltrim($value, '-');

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        } elseif ($lastComma !== false) {
            $after = strlen($value) - $lastComma - 1;
            if (substr_count($value, ',') > 1 || $after > 2) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace(',', '.', $value);
            }
        } elseif ($lastDot !== false) {
            $after = strlen($value) - $lastDot - 1;
            if (substr_count($value, '.') > 1 || $after === 3) {
                $value = str_replace('.', '', $value);
            }
        }

        $value = preg_replace('/[^0-9.]/', '', $value) ?? '';

        if (substr_count($value, '.') > 1) {
            $parts = explode('.', $value);
            $decimal = array_pop($parts);
            $value = implode('', $parts).'.'.$decimal;
        }

        $value = trim($value, '.');

        if ($value === '') {
            return '';
        }

        return ($isNegative ? '-' : '').$value;
    }

    /**
     * Kiểm tra đợt thanh toán bị khóa do ĐNTT đã gửi hoặc đã duyệt.
     */
    public function paymentRoundIsLocked($round): bool
    {
        if (empty($round->payment_request_id)) {
            return false;
        }

        if (! Schema::hasTable('payment_requests') || ! Schema::hasColumn('payment_requests', 'status')) {
            return true;
        }

        $status = DB::table('payment_requests')
            ->where('id', $round->payment_request_id)
            ->value('status');

        return in_array((string) $status, ['submitted', 'admin_approved', 'accounting_approved', 'paid'], true);
    }

    /**
     * Đồng bộ lại tổng đã trả và trạng thái của công nợ từ các đợt thanh toán.
     */
    public function syncSupplierDebtTotals(int $debtId): void
    {
        if (! Schema::hasTable('finance_supplier_debts')) {
            return;
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $debtId)->first();

        if (! $debt) {
            return;
        }

        $rounds = collect();

        if (Schema::hasTable('finance_supplier_debt_payments')) {
            $rounds = DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $debtId)
                ->get();
        }

        $paidAmount = 0.0;
        $pendingAmount = 0.0;

        foreach ($rounds as $round) {
            $meta = $this->roundPaymentMeta($round);
            $paidAmount += (float) $meta['paid_amount'];
            $pendingAmount += (float) $meta['pending_amount'];
        }

        $totalAmount = (float) ($debt->total_amount ?? 0);
        $remainAmount = max($totalAmount - $paidAmount, 0);

        $status = 'unpaid';

        if ($remainAmount <= 0 && $totalAmount > 0) {
            $status = 'paid';
        } elseif ($paidAmount > 0 || $pendingAmount > 0) {
            $status = 'partial';
        }

        $payload = [
            'paid_amount' => $paidAmount,
            'status' => $status,
            'updated_at' => now(),
        ];

        DB::table('finance_supplier_debts')
            ->where('id', $debtId)
            ->update($payload);
    }

    /**
     * Lấy tháng (Y-m) của công nợ để quay về đúng bộ lọc.
     */
    public function supplierDebtMonth($debt)
    {
        return $debt && ! empty($debt->debt_month)
            ? date('Y-m', strtotime($debt->debt_month))
            : now()->format('Y-m');
    }

    /**
     * Sinh nội dung lý do thanh toán cho ĐNTT theo đợt và chứng từ.
     */
    public function supplierDebtPaymentReason($debt, $round)
    {
        $roundNo = (int) ($round->payment_round ?? 1);

        $reason = 'Thanh toán đợt '.$roundNo.' công nợ nhà cung cấp '.($debt->supplier_name ?? '');

        if (! empty($debt->document_no)) {
            $reason .= ' - chứng từ '.$debt->document_no;
        }

        if (! empty($debt->document_date)) {
            $reason .= ' ngày '.date('d/m/Y', strtotime($debt->document_date));
        }

        return $reason;
    }

    /**
     * Kiểm tra ĐNTT còn tồn tại (chưa xóa mềm) hay không.
     */
    public function paymentRequestExistsForSupplierDebt($paymentRequestId): bool
    {
        if (! $paymentRequestId || ! Schema::hasTable('payment_requests')) {
            return false;
        }

        $query = DB::table('payment_requests')->where('id', (int) $paymentRequestId);

        if (Schema::hasColumn('payment_requests', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    /**
     * Gỡ liên kết các đợt trỏ tới ĐNTT đã bị xóa; trả về số dòng đã xử lý.
     */
    public function clearMissingSupplierDebtPaymentRequests(array $roundIds = []): int
    {
        if (
            ! Schema::hasTable('finance_supplier_debt_payments') ||
            ! Schema::hasTable('payment_requests') ||
            ! Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id')
        ) {
            return 0;
        }

        $query = DB::table('finance_supplier_debt_payments as p')
            ->leftJoin('payment_requests as pr', 'pr.id', '=', 'p.payment_request_id')
            ->whereNotNull('p.payment_request_id');

        if (Schema::hasColumn('payment_requests', 'deleted_at')) {
            $query->where(function ($q) {
                $q->whereNull('pr.id')->orWhereNotNull('pr.deleted_at');
            });
        } else {
            $query->whereNull('pr.id');
        }

        if (count($roundIds)) {
            $query->whereIn('p.id', $roundIds);
        }

        $ids = $query->pluck('p.id')->values()->all();

        if (! count($ids)) {
            return 0;
        }

        DB::table('finance_supplier_debt_payments')
            ->whereIn('id', $ids)
            ->update([
                'payment_request_id' => null,
                'status' => 'planned',
                'updated_at' => now(),
            ]);

        return count($ids);
    }
}
