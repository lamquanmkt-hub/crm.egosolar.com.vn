<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CRM\Orders\OrderRefund;
use App\Models\CRM\Orders\OrderReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Service tài chính cho đổi/trả hàng.
 *
 * V4 tách hoàn hàng khỏi hoàn tiền:
 * - Kho vẫn nhận/kiểm tra/nhập hoàn dù khách chưa thanh toán.
 * - Sau khi kho nhập hoàn, hệ thống tính lại nghĩa vụ của đơn dựa trên hàng thực nhận.
 * - Phần chưa thanh toán được giảm công nợ.
 * - Chỉ phần khách thực sự trả dư sau khi trừ giá trị hàng còn giữ mới cần hoàn tiền.
 */
class OrderReturnFinancialService
{
    private const CLOSED_RETURN_STATUSES = ['rejected', 'cancelled'];

    /**
     * Chốt phương án tài chính ngay sau khi kho đã nhập hoàn.
     *
     * Công thức cốt lõi:
     *   nghĩa vụ mới = tổng đơn gốc - tổng tín dụng hàng hoàn đã nhập kho
     *   tiền cần hoàn = phần tăng thêm của số tiền khách đã trả vượt nghĩa vụ mới
     *   giảm công nợ = tín dụng hàng hoàn hiện tại - tiền cần hoàn
     */
    public function reconcileAfterStockIn(OrderReturn $return, User $user): OrderReturn
    {
        return DB::transaction(function () use ($return, $user) {
            $return = OrderReturn::query()
                ->with(['items', 'order'])
                ->lockForUpdate()
                ->findOrFail($return->id);

            if ($return->inventory_status !== 'posted') {
                throw ValidationException::withMessages([
                    'financial' => 'Chỉ chốt tài chính sau khi kho đã nhập hoàn.',
                ]);
            }

            $metadata = is_array($return->metadata) ? $return->metadata : [];
            if (! empty($metadata['financial_reconciled_v4_at'])) {
                return $return->fresh(['refunds']);
            }

            $current = $this->creditForReturn($return);
            $isCreditReturn = in_array($return->type, ['return', 'recall'], true);
            $currentNetCredit = $isCreditReturn ? $current['net'] : 0.0;

            $allPosted = OrderReturn::query()
                ->with('items')
                ->where('order_id', $return->order_id)
                ->where('inventory_status', 'posted')
                ->whereIn('type', ['return', 'recall'])
                ->whereNotIn('status', self::CLOSED_RETURN_STATUSES)
                ->get();

            $cumulativeNetCredit = 0.0;
            foreach ($allPosted as $postedReturn) {
                $cumulativeNetCredit += $this->creditForReturn($postedReturn)['net'];
            }
            $cumulativeNetCredit = round(max(0, $cumulativeNetCredit), 2);
            $previousNetCredit = round(max(0, $cumulativeNetCredit - $currentNetCredit), 2);

            $orderTotal = round(max(0, (float) ($return->order?->total_amount ?? 0)), 2);
            $paidAmount = $this->paidAmountForOrder((int) $return->order_id);

            $previousObligation = round(max(0, $orderTotal - $previousNetCredit), 2);
            $newObligation = round(max(0, $orderTotal - $cumulativeNetCredit), 2);

            $previousOverpayment = round(max(0, $paidAmount - $previousObligation), 2);
            $newOverpayment = round(max(0, $paidAmount - $newObligation), 2);

            // Chỉ lấy phần tiền trả dư phát sinh thêm do chính phiếu hoàn hiện tại.
            $plannedRefund = round(max(0, $newOverpayment - $previousOverpayment), 2);
            $plannedRefund = min($plannedRefund, $currentNetCredit);

            // Đổi hàng không bắt buộc hoàn tiền trong module này.
            // Phần tín dụng không trả ngược cho khách sẽ giảm nghĩa vụ công nợ.
            $plannedDebtReduction = round(max(0, $currentNetCredit - $plannedRefund), 2);
            $appliedDebtReduction = $this->reduceDebtObligation($return, $plannedDebtReduction);

            $metadata = array_merge($metadata, [
                'financial_policy_version' => 'v4',
                'financial_reconciled_v4_at' => now()->toIso8601String(),
                'accepted_return_amount' => round($current['gross'], 2),
                'net_return_credit' => round($currentNetCredit, 2),
                'order_total_snapshot' => $orderTotal,
                'payment_snapshot' => $paidAmount,
                'previous_return_credit' => $previousNetCredit,
                'cumulative_return_credit' => $cumulativeNetCredit,
                'previous_order_obligation' => $previousObligation,
                'order_obligation_after_return' => $newObligation,
                'cash_refund_planned' => round($plannedRefund, 2),
                'debt_reduction_planned' => $plannedDebtReduction,
                'debt_adjustment_amount' => round($appliedDebtReduction, 2),
            ]);

            $from = (string) $return->status;
            $updates = [
                'refund_amount' => round($plannedRefund, 2),
                'metadata' => $metadata,
            ];

            if ($return->type === 'exchange') {
                $updates['financial_status'] = 'not_required';
                // Giữ stocked_in để nghiệp vụ xuất hàng đổi có thể tiếp tục ở module bán hàng/kho.
                $updates['status'] = 'stocked_in';
                $note = 'Đã chốt kho. Đổi hàng không bắt buộc hoàn tiền; tiếp tục nghiệp vụ xuất hàng đổi.';
            } elseif ($plannedRefund <= 0.01) {
                $updates['financial_status'] = 'not_required';
                $updates['status'] = 'completed';
                $updates['completed_by'] = $user->id;
                $updates['completed_at'] = now();
                $note = $plannedDebtReduction > 0
                    ? 'Không phát sinh tiền hoàn. Đã giảm nghĩa vụ công nợ theo hàng thực nhận và hoàn tất phiếu.'
                    : 'Không phát sinh tiền hoàn. Đã hoàn tất phiếu.';
            } else {
                $updates['financial_status'] = 'pending';
                $updates['status'] = 'pending_refund';
                $note = 'Kho đã hoàn tất. Phát sinh '.number_format($plannedRefund, 0, ',', '.').' đ cần hoàn/cấn cho khách.';
            }

            $return->forceFill($updates)->save();

            $to = (string) $return->status;
            if ($from !== $to) {
                app(OrderReturnService::class)->history(
                    $return,
                    $from,
                    $to,
                    'financial_reconcile',
                    $note,
                    $user
                );
            } else {
                app(OrderReturnService::class)->history(
                    $return,
                    $from,
                    $to,
                    'financial_reconcile',
                    $note,
                    $user
                );
            }

            return $return->fresh(['items', 'refunds', 'order']);
        });
    }

    /**
     * Tạo phiếu hoàn tiền/cấn trừ trong đúng hạn mức đã được V4 tính sau khi kho nhập hoàn.
     */
    public function createRefund(OrderReturn $return, User $user, array $data): OrderRefund
    {
        return DB::transaction(function () use ($return, $user, $data) {
            $return = OrderReturn::query()->lockForUpdate()->findOrFail($return->id);

            if (! in_array($return->status, ['stocked_in', 'pending_refund'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Cần hoàn tất xử lý kho trước khi tạo phiếu hoàn tiền.',
                ]);
            }

            if ($return->type === 'exchange' || $return->financial_status === 'not_required') {
                throw ValidationException::withMessages([
                    'amount' => 'Phiếu này không phát sinh tiền cần hoàn.',
                ]);
            }

            $max = round(max(0, (float) $return->refund_amount), 2);
            $amount = round((float) ($data['amount'] ?? 0), 2);
            $already = round((float) $return->refunds()
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->sum('amount'), 2);

            if ($max <= 0.01) {
                throw ValidationException::withMessages([
                    'amount' => 'Không có số tiền cần hoàn sau khi đối chiếu thanh toán và công nợ.',
                ]);
            }

            if ($amount <= 0 || ($already + $amount) > ($max + 0.01)) {
                $remaining = max(0, $max - $already);
                throw ValidationException::withMessages([
                    'amount' => 'Số tiền hoàn vượt hạn mức còn lại '.number_format($remaining, 0, ',', '.').' đ.',
                ]);
            }

            $refund = OrderRefund::create([
                'refund_code' => 'RF-'.now()->format('YmdHis').'-'.$return->id.'-'.random_int(100, 999),
                'order_return_id' => $return->id,
                'order_id' => $return->order_id,
                'amount' => $amount,
                'method' => $data['method'],
                'status' => 'pending_accounting',
                'bank_information' => $data['bank_information'] ?? null,
                'requested_by' => $user->id,
                'requested_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            $from = (string) $return->status;
            $return->update([
                'financial_status' => 'pending',
                'status' => 'pending_refund',
            ]);

            app(OrderReturnService::class)->history(
                $return,
                $from,
                'pending_refund',
                'create_refund',
                'Tạo phiếu hoàn tiền '.$refund->refund_code,
                $user
            );

            return $refund;
        });
    }

    /**
     * Kế toán duyệt phiếu hoàn tiền đang chờ.
     */
    public function approve(OrderRefund $refund, User $user): OrderRefund
    {
        if ($refund->status !== 'pending_accounting') {
            throw ValidationException::withMessages(['status' => 'Phiếu hoàn tiền không ở trạng thái chờ duyệt.']);
        }

        $refund->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return $refund->fresh();
    }

    /**
     * Ghi nhận đã hoàn tiền/cấn trừ. Với phiếu V4, công nợ đã được giảm ở bước nhập hoàn,
     * vì vậy tuyệt đối không giảm lần thứ hai khi chi tiền.
     */
    public function process(OrderRefund $refund, User $user, ?string $attachmentPath = null): OrderRefund
    {
        return DB::transaction(function () use ($refund, $user, $attachmentPath) {
            $refund = OrderRefund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status === 'paid') {
                throw ValidationException::withMessages(['status' => 'Phiếu này đã được xử lý trước đó.']);
            }
            if ($refund->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Phiếu hoàn tiền chưa được phê duyệt.']);
            }

            $refund->update([
                'status' => 'paid',
                'processed_by' => $user->id,
                'processed_at' => now(),
                'attachment_path' => $attachmentPath ?: $refund->attachment_path,
            ]);

            $return = OrderReturn::query()->lockForUpdate()->findOrFail($refund->order_return_id);
            $metadata = is_array($return->metadata) ? $return->metadata : [];

            // Tương thích phiếu cũ: trước V4, công nợ chỉ được giảm khi xử lý refund.
            if (empty($metadata['financial_reconciled_v4_at'])) {
                $this->legacyAdjustDebt($return, (float) $refund->amount);
            }

            $planned = round(max(0, (float) $return->refund_amount), 2);
            $paid = round((float) $return->refunds()->where('status', 'paid')->sum('amount'), 2);
            $pending = round((float) $return->refunds()
                ->whereNotIn('status', ['paid', 'rejected', 'cancelled'])
                ->sum('amount'), 2);
            $outstanding = round(max(0, $planned - $paid), 2);

            $isDone = $return->inventory_status === 'posted'
                && $outstanding <= 0.01
                && $pending <= 0.01;

            $from = (string) $return->status;
            $return->update([
                'financial_status' => $isDone ? 'paid' : ($paid > 0 ? 'partial' : 'pending'),
                'status' => $isDone ? 'completed' : 'pending_refund',
                'completed_by' => $isDone ? $user->id : $return->completed_by,
                'completed_at' => $isDone ? now() : $return->completed_at,
            ]);

            app(OrderReturnService::class)->history(
                $return,
                $from,
                (string) $return->status,
                'refund_paid',
                'Đã xử lý '.$refund->refund_code,
                $user
            );

            return $refund->fresh();
        });
    }

    /**
     * Giá trị hàng thực tế được kho chấp nhận, trừ phí xử lý/vận chuyển.
     *
     * @return array{gross: float, net: float}
     */
    private function creditForReturn(OrderReturn $return): array
    {
        $items = $return->relationLoaded('items') ? $return->items : $return->items()->get();
        $gross = 0.0;

        foreach ($items as $item) {
            $requested = max(1, (int) $item->requested_quantity);
            $accepted = max(0, (int) $item->accepted_quantity, (int) $item->stock_posted_quantity);
            $lineAmount = max(0, (float) $item->return_amount);
            $gross += ($lineAmount / $requested) * $accepted;
        }

        // Phiếu không có items (ví dụ dữ liệu cũ đặc biệt) thì fallback tổng phiếu.
        if ($items->count() === 0) {
            $gross = max(0, (float) $return->total_return_amount);
        }

        $gross = round(max(0, $gross), 2);
        $net = round(max(
            0,
            $gross - (float) $return->restocking_fee - (float) $return->shipping_fee
        ), 2);

        return ['gross' => $gross, 'net' => $net];
    }

    /**
     * Tổng tiền khách đã thực thu trên đơn.
     */
    private function paidAmountForOrder(int $orderId): float
    {
        if (! Schema::hasTable('crm_payments')) {
            return 0.0;
        }

        return round(max(0, (float) DB::table('crm_payments')
            ->where('order_id', $orderId)
            ->sum('amount')), 2);
    }

    /**
     * Giảm nghĩa vụ công nợ theo phần hàng hoàn chưa phải trả ngược tiền cho khách.
     * Trả về số tiền thực tế đã giảm trên debt.total_amount.
     */
    private function reduceDebtObligation(OrderReturn $return, float $amount): float
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0.01 || ! Schema::hasTable('crm_customer_debts')) {
            return 0.0;
        }

        $debt = DB::table('crm_customer_debts')
            ->where('order_id', $return->order_id)
            ->lockForUpdate()
            ->first();

        if (! $debt) {
            return 0.0;
        }

        $oldTotal = max(0, (float) ($debt->total_amount ?? 0));
        $paid = max(0, (float) ($debt->paid_amount ?? 0));
        $targetObligation = max(0, $oldTotal - $amount);

        // Không để debt.total_amount nhỏ hơn gross paid_amount vì hệ thống hiện tại
        // lưu tiền hoàn ở order_refunds, không trừ ngược crm_payments.
        $safeTotal = max($paid, $targetObligation);
        $remainingDebt = max(0, $safeTotal - $paid);
        $status = $remainingDebt <= 0.01 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        $update = [
            'total_amount' => round($safeTotal, 2),
            'status' => $status,
            'updated_at' => now(),
        ];

        // debt_amount co the la GENERATED COLUMN tren MySQL/MariaDB.
        // Khong duoc UPDATE truc tiep generated column; DB se tu tinh lai tu total_amount/paid_amount.
        if (Schema::hasColumn('crm_customer_debts', 'debt_amount')
            && ! $this->isGeneratedColumn('crm_customer_debts', 'debt_amount')) {
            $update['debt_amount'] = round($remainingDebt, 2);
        }

        DB::table('crm_customer_debts')->where('id', $debt->id)->update($update);

        return round(max(0, $oldTotal - $safeTotal), 2);
    }

    /**
     * Kiem tra cot co phai GENERATED COLUMN hay khong.
     * MariaDB/MySQL se bao loi 1906 neu co gang UPDATE generated column.
     */
    private function isGeneratedColumn(string $table, string $column): bool
    {
        try {
            $row = DB::selectOne(
                'SELECT EXTRA, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
                [$table, $column]
            );

            if (! $row) {
                return false;
            }

            $extra = strtolower((string) ($row->EXTRA ?? $row->extra ?? ''));
            $expression = trim((string) ($row->GENERATION_EXPRESSION ?? $row->generation_expression ?? ''));

            return str_contains($extra, 'generated') || $expression !== '';
        } catch (\Throwable $e) {
            // Neu khong doc duoc information_schema, uu tien khong pha luong chinh.
            // Cot generated tren server nay se duoc phat hien binh thuong.
            return false;
        }
    }

    /**
     * Cơ chế cũ cho phiếu tạo trước V4.
     */
    private function legacyAdjustDebt(OrderReturn $return, float $amount): void
    {
        if (! Schema::hasTable('crm_customer_debts')) {
            return;
        }

        $debt = DB::table('crm_customer_debts')
            ->where('order_id', $return->order_id)
            ->lockForUpdate()
            ->first();

        if (! $debt) {
            return;
        }

        $targetObligation = max(0, (float) $debt->total_amount - $amount);
        $safeTotal = max((float) $debt->paid_amount, $targetObligation);
        $newDebt = max(0, $safeTotal - (float) $debt->paid_amount);
        $status = $newDebt <= 0.01 ? 'paid' : ((float) $debt->paid_amount > 0 ? 'partial' : 'unpaid');

        $update = [
            'total_amount' => round($safeTotal, 2),
            'status' => $status,
            'updated_at' => now(),
        ];

        // Tuong thich DB cu: chi ghi debt_amount neu day la cot thuong.
        if (Schema::hasColumn('crm_customer_debts', 'debt_amount')
            && ! $this->isGeneratedColumn('crm_customer_debts', 'debt_amount')) {
            $update['debt_amount'] = round($newDebt, 2);
        }

        DB::table('crm_customer_debts')->where('id', $debt->id)->update($update);
    }
}
