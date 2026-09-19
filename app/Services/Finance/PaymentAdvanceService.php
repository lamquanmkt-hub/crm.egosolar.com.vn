<?php

namespace App\Services\Finance;

use App\Models\Payments\PaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentAdvanceService
{
    /**
     * Đồng bộ nghiệp vụ sau khi Kế toán xác nhận đã chi.
     * Hàm idempotent: gọi lặp lại không cộng tạm ứng lương hai lần.
     */
    public static function afterAccountingApproved(PaymentRequest $item): void
    {
        DB::transaction(function () use ($item) {
            if ($item->doc_type === 'advance') {
                self::activateSettlement((int) $item->id, (int) $item->created_by);
                return;
            }

            if ($item->doc_type === 'salary_advance') {
                self::applySalaryAdvance((int) $item->id);
            }
        });
    }

    private static function activateSettlement(int $paymentRequestId, int $createdBy): void
    {
        if (! Schema::hasTable('advance_settlements')) {
            return;
        }

        // V1.1: chỉ đồng bộ hồ sơ DNTU-HU được tạo từ module mới.
        // Tuyệt đối không tự "nhận" các phiếu tạm ứng cũ có mã PR-*.
        $payment = DB::table('payment_requests')
            ->where('id', $paymentRequestId)
            ->first(['id', 'code']);
        if (! $payment || ! str_starts_with((string) $payment->code, 'DNTU-')) {
            return;
        }

        $row = DB::table('advance_settlements')
            ->where('payment_request_id', $paymentRequestId)
            ->lockForUpdate()
            ->first();

        // Chỉ các hồ sơ đã có marker advance_settlements từ lúc tạo mới mới được xử lý.
        if (! $row) {
            return;
        }

        if (in_array((string) $row->status, ['waiting_payment', 'returned'], true)) {
            DB::table('advance_settlements')
                ->where('id', $row->id)
                ->update([
                    'status' => 'waiting_settlement',
                    'updated_at' => now(),
                ]);
        }
    }

    private static function applySalaryAdvance(int $paymentRequestId): void
    {
        if (! Schema::hasTable('salary_advance_requests') || ! Schema::hasTable('payrolls')) {
            return;
        }

        $payment = DB::table('payment_requests')
            ->where('id', $paymentRequestId)
            ->first(['id', 'code']);
        if (! $payment || ! str_starts_with((string) $payment->code, 'DNTUL-')) {
            return;
        }

        $advance = DB::table('salary_advance_requests')
            ->where('payment_request_id', $paymentRequestId)
            ->lockForUpdate()
            ->first();

        if (! $advance || $advance->applied_to_payroll_at) {
            return;
        }

        $amount = (float) $advance->requested_amount;
        if ($amount <= 0) {
            return;
        }

        $payroll = DB::table('payrolls')
            ->where('user_id', $advance->user_id)
            ->where('payroll_month', $advance->payroll_month)
            ->lockForUpdate()
            ->first();

        if ($payroll) {
            DB::table('payrolls')
                ->where('id', $payroll->id)
                ->update([
                    'advance' => ((float) ($payroll->advance ?? 0)) + $amount,
                    'updated_at' => now(),
                ]);
            $payrollId = (int) $payroll->id;
        } else {
            $columns = Schema::getColumnListing('payrolls');
            $data = [
                'user_id' => (int) $advance->user_id,
                'payroll_month' => (string) $advance->payroll_month,
                'advance' => $amount,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $payrollId = (int) DB::table('payrolls')->insertGetId(
                array_intersect_key($data, array_flip($columns))
            );
        }

        DB::table('salary_advance_requests')
            ->where('id', $advance->id)
            ->update([
                'payroll_id' => $payrollId,
                'applied_to_payroll_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
