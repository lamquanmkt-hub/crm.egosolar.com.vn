<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\CRM\Orders\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Hợp đồng service quản lý đơn hàng (Order): truy vấn, tạo/sửa, duyệt, xuất kho, thanh toán.
 */
interface OrderServiceInterface
{
    public function count(): int;

    public function getRecentOrders(int $limit = 5): Collection;

    public function getOrdersByUserRole($user, array $filters = []): LengthAwarePaginator;

    public function getOrderIndexSummary($user, array $filters = []): array;

    public function getAllOrders(array $filters = []): LengthAwarePaginator;

    public function find($id): ?Order;

    public function findWithDetails($id): Order;

    public function getCurrentApprovalLevel(Order $order): ?string;

    public function getOrderTimeline($orderId);

    public function getOrderNotifications($orderId);

    public function getSalesStatistics(int $salesId): array;

    public function getRecentOrdersBySales(int $salesId, int $limit = 10);

    public function getPendingNotifications(int $salesId);

    public function createOrder(array $data): Order;

    public function updateOrder($id, array $data): Order;

    public function submitForApproval($id): void;

    public function processApproval(string $id, array $data): void;

    public function approveOrder($id, array $data): void;

    public function rejectOrder($id, array $data): void;

    public function cancelOrder($id): void;

    public function deleteOrder($id): void;

    public function shipOrder($id, array $data): void;

    public function recordPayment($id, array $data): void;

    public function getShipSerialsPayload(Order $order): array;

    public function recordStatusHistory(Order $order, ?string $from, string $to, string $note): void;
}
