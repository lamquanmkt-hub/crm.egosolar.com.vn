<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CRM\Orders\OrderRefund;
use App\Models\CRM\Orders\OrderReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderReturnFinancialService
{
    public function createRefund(OrderReturn $return, User $user, array $data): OrderRefund
    {
        return DB::transaction(function () use ($return, $user, $data) {
            $return = OrderReturn::query()->lockForUpdate()->findOrFail($return->id);
            if (!in_array($return->status, ['stocked_in', 'pending_refund'], true)) {
                throw ValidationException::withMessages(['status' => 'Cần hoàn tất xử lý kho trước khi tạo phiếu hoàn tiền.']);
            }
            $max = max(0, (float) $return->total_return_amount - (float) $return->restocking_fee - (float) $return->shipping_fee);
            $amount = (float) ($data['amount'] ?? 0);
            $already = (float) $return->refunds()->whereNotIn('status', ['rejected', 'cancelled'])->sum('amount');
            if ($amount <= 0 || ($already + $amount) > ($max + 0.01)) {
                throw ValidationException::withMessages(['amount' => 'Số tiền hoàn vượt giá trị còn có thể xử lý.']);
            }
            $refund = OrderRefund::create([
                'refund_code' => 'RF-' . now()->format('YmdHis') . '-' . $return->id . '-' . random_int(100, 999),
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
            $return->update(['financial_status' => 'pending', 'status' => 'pending_refund']);
            app(OrderReturnService::class)->history($return, 'stocked_in', 'pending_refund', 'create_refund', 'Tạo phiếu hoàn tiền ' . $refund->refund_code, $user);
            return $refund;
        });
    }

    public function approve(OrderRefund $refund, User $user): OrderRefund
    {
        if ($refund->status !== 'pending_accounting') {
            throw ValidationException::withMessages(['status' => 'Phiếu hoàn tiền không ở trạng thái chờ duyệt.']);
        }
        $refund->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
        return $refund->fresh();
    }

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
            $this->adjustDebt($return, (float) $refund->amount);

            $remaining = (float) $return->refunds()->whereNotIn('status', ['paid', 'rejected', 'cancelled'])->sum('amount');
            $return->update([
                'financial_status' => $remaining > 0 ? 'partial' : 'paid',
                'status' => $return->inventory_status === 'posted' && $remaining <= 0 ? 'completed' : $return->status,
                'completed_by' => $return->inventory_status === 'posted' && $remaining <= 0 ? $user->id : $return->completed_by,
                'completed_at' => $return->inventory_status === 'posted' && $remaining <= 0 ? now() : $return->completed_at,
            ]);
            app(OrderReturnService::class)->history($return, 'pending_refund', $return->status, 'refund_paid', 'Đã xử lý ' . $refund->refund_code, $user);
            return $refund->fresh();
        });
    }

    private function adjustDebt(OrderReturn $return, float $amount): void
    {
        $debt = DB::table('crm_customer_debts')->where('order_id', $return->order_id)->lockForUpdate()->first();
        if (!$debt) return;
        $targetObligation = max(0, (float) $debt->total_amount - $amount);
        $safeTotal = max((float) $debt->paid_amount, $targetObligation);
        $newDebt = max(0, $safeTotal - (float) $debt->paid_amount);
        $status = $newDebt <= 0 ? 'paid' : ((float) $debt->paid_amount > 0 ? 'partial' : 'unpaid');
        DB::table('crm_customer_debts')->where('id', $debt->id)->update([
            'total_amount' => $safeTotal,
            'status' => $status,
            'updated_at' => now(),
        ]);
    }
}
