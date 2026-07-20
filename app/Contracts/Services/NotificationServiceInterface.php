<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\CRM\Orders\Order;

/**
 * Hợp đồng Gửi và quản lý thông báo đơn hàng tới sales/bộ phận liên quan.
 *
 * Sinh từ implementation NotificationService của chính dự án này (KHÔNG copy từ crm-shop —
 * signature hai codebase đã phân kỳ).
 */
interface NotificationServiceInterface
{
    public function notifySales($salesId, Order $order, string $type, string $message): void;

    public function notifyDepartment(string $department, Order $order, string $message): void;

    public function notifyDepartmentWithTitle(string $department, Order $order, string $title, string $message): void;

    public function notifyOverdue(Order $order): void;

    public function markAsRead($notificationId): void;

    public function markAllAsRead($userId): void;

    public function getUnreadCount($userId): int;

    public function getUnreadNotifications($userId, $limit = 10);

    public function getAllNotifications($userId, $limit = 50);
}
