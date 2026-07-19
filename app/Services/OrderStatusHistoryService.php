<?php

namespace App\Services;

use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderStatusHistory;

/**
 * Service xử lý lịch sử trạng thái đơn hàng
 *
 * Tách riêng để tuân thủ Single Responsibility Principle
 */
class OrderStatusHistoryService
{
    /**
     * Ghi lại thay đổi trạng thái
     *
     * @param Order $order
     * @param string $from
     * @param string $to
     * @param string $type [APPROVAL, REJECTION, SHIPPING, PAYMENT, ...]
     * @param string $note
     * @return OrderStatusHistory
     */
    public function record(
        Order $order,
        string $from,
        string $to,
        string $type = 'STATUS_CHANGE',
        string $note = ''
    ): OrderStatusHistory {
        return OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_department' => $from,
            'to_department' => $to,
            'type' => $type,
            'note' => $note,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Lấy timeline của đơn hàng
     *
     * @param Order $order
     * @return mixed
     */
    public function getTimeline(Order $order)
    {
        return $order->statusHistory()
            ->orderByDesc('recorded_at')
            ->get();
    }
}
