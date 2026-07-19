<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderDepartment;
use App\Enums\OrderStatusCode;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderApproval;
use App\Models\CRM\Orders\OrderStatusType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Xử lý logic phê duyệt đơn hàng.
 *
 * Quản lý:
 * - Tạo & cập nhật bản ghi approval
 * - Chuyển đổi trạng thái giữa các bộ phận
 * - Chốt đơn khi Giám đốc duyệt
 */
class OrderApprovalHandler
{
    /**
     * Tạo approval ban đầu khi Sales tạo đơn.
     *
     * @param Order $order
     */
    public function createInitialApproval(Order $order): void
    {
        OrderApproval::create([
            'order_id'    => $order->id,
            'level'       => OrderDepartment::SALES->value,
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Tạo bản ghi approval cho bước tiếp theo.
     *
     * @param Order  $order
     * @param string $level Tên department
     */
    public function createApprovalRecord(Order $order, string $level): void
    {
        OrderApproval::firstOrCreate(
            ['order_id' => $order->id, 'level' => $level, 'status' => 'pending']
        );
    }

    /**
     * Chuyển đơn hàng sang department khác.
     *
     * @param Order                $order
     * @param OrderDepartment|null $dept       Department đích (null nếu cancelled)
     * @param OrderStatusCode|null $statusCode Status code tùy chỉnh (null = lấy từ dept)
     * @param string|null          $rawDept    Department string raw (cho trường hợp 'cancelled')
     */
    public function transitionToDepartment(
        Order $order,
        ?OrderDepartment $dept,
        ?OrderStatusCode $statusCode = null,
        ?string $rawDept = null,
    ): void {
        $deptValue  = $rawDept ?? $dept?->value ?? 'cancelled';
        $codeValue  = $statusCode?->value ?? $dept?->statusCode()->value ?? 'CANCELLED';
        $status     = OrderStatusType::where('code', $codeValue)->first();

        $order->update([
            'current_department'     => $deptValue,
            'current_status_type_id' => $status?->id ?? $order->current_status_type_id,
        ]);
    }

    /**
     * Cập nhật approval hiện tại khi được duyệt.
     *
     * @param Order           $order
     * @param OrderDepartment $dept Department đang duyệt
     * @param mixed           $user User duyệt
     * @param array           $data Dữ liệu bổ sung (note, debt_checked, ...)
     */
    public function updateCurrentApproval(Order $order, OrderDepartment $dept, $user, array $data): void
    {
        $approval = OrderApproval::where('order_id', $order->id)
            ->where('level', $dept->value)
            ->where('status', 'pending')
            ->first();

        if (!$approval) {
            $approval = new OrderApproval([
                'order_id' => $order->id,
                'level'    => $dept->value,
                'status'   => 'pending',
            ]);
        }

        $updateData = [
            'status'      => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'note'        => $data['note'] ?? null,
        ];

        // Xử lý đặc biệt cho Kế toán
        if ($dept === OrderDepartment::ACCOUNTING) {
            $updateData['debt_checked'] = isset($data['debt_checked']);
            $updateData['debt_note']    = $data['debt_note'] ?? null;

            if (!empty($data['is_paid'])) {
                $order->update(['payment_recorded' => true]);
            }
        }

        $approval->fill($updateData)->save();
    }

    /**
     * Cập nhật approval khi bị từ chối.
     *
     * @param Order  $order
     * @param string $dept   Department từ chối
     * @param mixed  $user   User từ chối
     * @param array  $data   {rejection_reason, note?}
     */
    public function updateRejectedApproval(Order $order, string $dept, $user, array $data): void
    {
        OrderApproval::updateOrCreate(
            ['order_id' => $order->id, 'level' => $dept, 'status' => 'pending'],
            [
                'status'           => 'rejected',
                'approved_by'      => $user->id,
                'approved_at'      => now(),
                'rejection_reason' => $data['rejection_reason'],
                'note'             => $data['note'] ?? null,
            ]
        );
    }

    /**
     * Chốt đơn khi Giám đốc duyệt xong.
     *
     * @param Order $order
     * @param mixed $user
     */
    public function finalizeApproval(Order $order, $user): void
    {
        $order->update([
            'approved_by'        => $user->id,
            'approved_at'        => now(),
            'estimated_delivery' => Carbon::now()->addDays(3),
        ]);
    }
}
