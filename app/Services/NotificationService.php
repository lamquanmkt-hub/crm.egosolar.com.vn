<?php

namespace App\Services;

use App\Contracts\Services\NotificationServiceInterface;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

/**
 * Service gửi và quản lý thông báo đơn hàng cho sales và các bộ phận.
 */
class NotificationService implements NotificationServiceInterface
{
    /**
     * Gửi thông báo cho Sales
     */
    public function notifySales($salesId, Order $order, string $type, string $message): void
    {
        if (! $salesId) {
            return; // Không làm gì nếu không có người nhận
        }
        $titles = [
            'status_change' => '📋 Cập nhật trạng thái đơn hàng',
            'approved' => '✅ Đơn hàng đã được duyệt',
            'rejected' => '❌ Đơn hàng bị từ chối',
            'shipped' => '📦 Đơn hàng đã xuất kho',
            'payment_received' => '💰 Đã ghi nhận thanh toán',
            'overdue' => '⏰ Đơn hàng quá hạn xử lý',
        ];

        OrderNotification::create([
            'order_id' => $order->id,
            'user_id' => $salesId,
            'type' => $type,
            'title' => $titles[$type] ?? 'Thông báo hệ thống',
            'message' => $message,
            'is_read' => false,
        ]);
    }

    /**
     * Gửi thông báo cho bộ phận
     */
    public function notifyDepartment(string $department, Order $order, string $message): void
    {
        // Use default title for department notification if not specified
        $this->notifyDepartmentWithTitle($department, $order, '🔔 Đơn hàng mới cần xử lý', $message);
    }

    /**
     * Gửi thông báo cho bộ phận với tiêu đề tùy chỉnh (Hàm hỗ trợ mới)
     */
    public function notifyDepartmentWithTitle(string $department, Order $order, string $title, string $message): void
    {
        // Lấy danh sách users thuộc department (đã được map sang role đúng)
        $users = $this->getUsersByDepartment($department);

        if ($users->isEmpty()) {
            Log::warning("Không tìm thấy user nào cho bộ phận/role: $department để gửi thông báo.");

            return;
        }

        $dataToInsert = [];
        $now = now();

        foreach ($users as $user) {
            // Chuẩn bị dữ liệu để insert hàng loạt (tối ưu hơn foreach create)
            $dataToInsert[] = [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'type' => 'department_notification',
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($dataToInsert)) {
            OrderNotification::insert($dataToInsert);
        }
    }

    /**
     * Gửi thông báo quá hạn
     */
    public function notifyOverdue(Order $order): void
    {
        $salesId = $order->created_by;
        $message = "Đơn hàng #{$order->order_code} đã quá thời gian dự kiến xử lý. ".
                   'Hiện đang ở bộ phận: '.$this->getDepartmentName($order->current_department);
        $this->notifySales($salesId, $order, 'status_change', $message);
    }

    /**
     * Đánh dấu đã đọc
     */
    public function markAsRead($notificationId): void
    {
        OrderNotification::where('id', $notificationId)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Đánh dấu tất cả đã đọc
     */
    public function markAllAsRead($userId): void
    {
        OrderNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Lấy số thông báo chưa đọc
     */
    public function getUnreadCount($userId): int
    {
        return OrderNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Lấy danh sách thông báo chưa đọc
     */
    public function getUnreadNotifications($userId, $limit = 10)
    {
        return OrderNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->with('order')
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Lấy tất cả thông báo
     */
    public function getAllNotifications($userId, $limit = 50)
    {
        return OrderNotification::where('user_id', $userId)
            ->with('order')
            ->latest()
            ->paginate($limit);
    }

    // ==================== HELPER METHODS ====================
    //    protected function getUsersByDepartment(string $department): Collection
    //    {
    //        // Chỉ còn 3 bộ phận chính: Sales -> Accounting -> Warehouse
    //        $roleMap = [
    //            'sales'      => 'sales',
    //            // Kế toán
    //            'accounting' => 'accounting',
    //            'ketoan'     => 'accounting', // Fallback
    //            'management' => 'director',
    //            // Kho
    //            'warehouse'  => 'warehouse',
    //            'kho'        => 'warehouse', // Fallback
    //        ];
    //
    //        // Nếu department không nằm trong map, mặc định dùng chính tên đó làm role
    //        $roleName = $roleMap[$department] ?? $department;
    //
    //        try {
    //            return User::role($roleName)->get();
    //        } catch (RoleDoesNotExist $e) {
    //            Log::error("NotificationService: Role '$roleName' không tồn tại.");
    //            return new Collection();
    //        }
    //    }
    /**
     * Lấy danh sách user theo bộ phận (map sang role Spatie tương ứng).
     */
    protected function getUsersByDepartment(string $department): Collection
    {
        $roleMap = [
            'sales' => 'sales',
            'sales_manager' => 'sales_manager',
            'accounting' => 'accounting',
            'management' => 'management',
            'warehouse' => 'warehouse',
        ];

        $roleName = $roleMap[$department] ?? $department;

        try {
            return User::role($roleName)->get();
        } catch (RoleDoesNotExist $e) {
            Log::error("NotificationService: Role '$roleName' không tồn tại.");

            return new Collection;
        }
    }

    /**
     * Chuyển mã bộ phận sang tên hiển thị tiếng Việt.
     */
    protected function getDepartmentName(string $dept): string
    {
        $names = [
            'sales' => 'Kinh doanh',
            'sales_manager' => 'Quản lý kinh doanh',
            'accounting' => 'Kế toán',
            'management' => 'Ban Giám đốc',
            'warehouse' => 'Kho vận',
            'completed' => 'Hoàn tất',
            'canceled' => 'Đã hủy',
        ];

        return $names[$dept] ?? ucfirst($dept);
    }
}
