<?php

declare(strict_types=1);

namespace App\Validators;

use App\Enums\OrderDepartment;
use App\Models\CRM\Orders\Order;

/**
 * Validator cho Order - tách riêng validation logic.
 *
 * Tuân thủ Single Responsibility Principle:
 * mỗi method validate 1 business rule cụ thể.
 *
 * Sử dụng static methods để dễ gọi từ Service/Controller
 * mà không cần inject dependency.
 */
class OrderValidator
{
    /**
     * Validate đơn hàng có thể gửi duyệt.
     *
     * Điều kiện:
     * - Đang ở bước Sales
     * - Có ít nhất 1 item
     * - Tổng tiền > 0
     *
     * @param Order $order
     *
     * @throws \Exception
     */
    public static function canSubmit(Order $order): void
    {
        if ($order->current_department !== OrderDepartment::SALES->value) {
            throw new \Exception('Chỉ có thể gửi duyệt đơn ở bước Sales');
        }

        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            throw new \Exception('Đơn hàng phải có ít nhất 1 sản phẩm');
        }

        if ((float) $order->total_amount <= 0) {
            throw new \Exception('Tổng tiền đơn hàng phải lớn hơn 0');
        }
    }

    /**
     * Validate đơn hàng có thể duyệt.
     *
     * @param Order $order
     *
     * @throws \Exception
     */
    public static function canApprove(Order $order): void
    {
        $dept = OrderDepartment::tryFrom($order->current_department);

        if (!$dept || !$dept->isApprovable()) {
            throw new \Exception('Đơn hàng không thể duyệt ở trạng thái hiện tại');
        }
    }

    /**
     * Validate đơn hàng có thể từ chối.
     *
     * @param Order $order
     *
     * @throws \Exception
     */
    public static function canReject(Order $order): void
    {
        $dept = OrderDepartment::tryFrom($order->current_department);

        if (!$dept || !$dept->isApprovable()) {
            throw new \Exception('Chỉ các bộ phận được phân công mới có thể từ chối');
        }
    }

    /**
     * Validate đơn hàng có thể xuất kho.
     *
     * @param Order $order
     *
     * @throws \Exception
     */
    public static function canShip(Order $order): void
    {
        if ($order->current_department !== OrderDepartment::WAREHOUSE->value) {
            throw new \Exception('Chỉ đơn ở bước Kho mới có thể xuất kho');
        }

        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            throw new \Exception('Đơn hàng không có sản phẩm để xuất');
        }
    }

    /**
     * Validate thông tin thanh toán.
     *
     * @param array $data
     *
     * @throws \Exception
     */
    public static function validatePayment(array $data): void
    {
        if (empty($data['payment_date'])) {
            throw new \Exception('Ngày thanh toán không được để trống');
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \Exception('Số tiền thanh toán phải lớn hơn 0');
        }

        if (empty($data['method_id'])) {
            throw new \Exception('Phương thức thanh toán không được để trống');
        }
    }

    /**
     * Validate đơn hàng có thể hủy.
     *
     * @param Order $order
     *
     * @throws \Exception
     */
    public static function canCancel(Order $order): void
    {
        if ($order->current_department !== OrderDepartment::SALES->value) {
            throw new \Exception('Chỉ được hủy đơn hàng khi đang ở bộ phận Sales');
        }
    }

    /**
     * Validate đơn hàng có thể xóa.
     *
     * @param Order $order
     *
     * @throws \Exception
     */
    public static function canDelete(Order $order): void
    {
        if ($order->current_department === OrderDepartment::COMPLETED->value) {
            throw new \Exception('Không thể xóa đơn hàng đã hoàn tất');
        }
    }
}
