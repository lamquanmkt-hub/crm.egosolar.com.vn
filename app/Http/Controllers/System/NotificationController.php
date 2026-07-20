<?php

namespace App\Http\Controllers\System;

use App\Contracts\Services\NotificationServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller tổng hợp thông báo hệ thống, công việc và nhân sự cho người dùng.
 */
class NotificationController extends Controller
{
    /**
     * Khởi tạo controller với service thông báo.
     */
    public function __construct(
        protected NotificationServiceInterface $notificationService
    ) {}

    /* EGO_TASK_NOTIFY_INDEX */
    /**
     * Hiển thị trang danh sách thông báo tổng hợp của người dùng.
     */
    public function index()
    {
        $userId = Auth::id();
        $limit = 30;
        $legacy = $this->notificationService->getAllNotifications($userId, $limit);

        $notifications = $this->normalizeNotificationItems($legacy)
            ->concat($this->taskNotificationItems($userId, $limit))
            ->concat($this->hrAnnouncementItems($userId, $limit))
            ->unique('id')
            ->sortByDesc(fn ($item) => strtotime((string) ($item['created_at_sort'] ?? $item['created_at'] ?? '')) ?: 0)
            ->take($limit)
            ->values();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Đánh dấu một thông báo (hệ thống/công việc/nhân sự) là đã đọc.
     */
    public function markAsRead($id)
    {
        if (is_string($id) && str_starts_with($id, 'hr_')) {
            $this->ensureHrAnnouncementTables();

            $announcementId = (int) str_replace('hr_', '', $id);

            DB::table('hr_announcement_reads')->updateOrInsert(
                [
                    'announcement_id' => $announcementId,
                    'user_id' => Auth::id(),
                ],
                [
                    'read_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return request()->ajax()
                ? response()->json(['ok' => true])
                : back()->with('success', 'Đã đánh dấu thông báo nhân sự đã đọc');
        }

        /* EGO_TASK_NOTIFY_MARK_READ */
        if (is_string($id) && str_starts_with($id, 'task_')) {
            $this->ensureTaskNotificationTable();
            $notificationId = (int) str_replace('task_', '', $id);

            DB::table('task_notifications')
                ->where('id', $notificationId)
                ->where('user_id', Auth::id())
                ->update(['is_read' => 1, 'read_at' => now(), 'updated_at' => now()]);

            return request()->ajax()
                ? response()->json(['ok' => true])
                : back()->with('success', 'Đã đánh dấu thông báo công việc đã đọc');
        }

        $this->notificationService->markAsRead($id);

        return request()->ajax()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Đã đánh dấu thông báo đã đọc');
    }

    /**
     * Đánh dấu tất cả thông báo của người dùng là đã đọc.
     */
    public function markAllAsRead()
    {
        $userId = Auth::id();

        $this->notificationService->markAllAsRead($userId);
        $this->markAllTaskNotificationsAsRead($userId);
        $this->markAllHrAnnouncementsAsRead($userId);

        return request()->ajax()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Đã đánh dấu tất cả thông báo đã đọc');
    }

    /**
     * Trả về JSON tổng số thông báo chưa đọc.
     */
    public function getUnreadCount()
    {
        $userId = Auth::id();

        $count = (int) $this->notificationService->getUnreadCount($userId);
        $count += $this->taskNotificationUnreadCount($userId);
        $count += $this->hrAnnouncementUnreadCount($userId);

        return response()->json(['count' => $count]);
    }

    /**
     * Trả về danh sách thông báo tổng hợp dạng JSON cho chuông thông báo.
     */
    public function json(Request $request)
    {
        $userId = Auth::id();
        $limit = max(1, min(30, (int) $request->get('limit', 10)));

        $legacy = $this->notificationService->getAllNotifications($userId, $limit);
        $legacyItems = $this->normalizeNotificationItems($legacy);

        $taskItems = $this->taskNotificationItems($userId, $limit);
        $hrItems = $this->hrAnnouncementItems($userId, $limit);

        $items = $legacyItems
            ->concat($taskItems)
            ->concat($hrItems)
            ->unique('id')
            ->sortByDesc(function ($item) {
                return strtotime((string) ($item['created_at_sort'] ?? $item['created_at'] ?? '')) ?: 0;
            })
            ->take($limit)
            ->values();

        return response()->json([
            'items' => [
                'data' => $items,
            ],
        ]);
    }

    /* EGO_TASK_NOTIFY_CONTROLLER_START */
    /**
     * Tạo bảng task_notifications nếu chưa tồn tại.
     */
    private function ensureTaskNotificationTable(): void
    {
        if (Schema::hasTable('task_notifications')) {
            return;
        }

        DB::statement("CREATE TABLE IF NOT EXISTS `task_notifications` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `task_id` BIGINT UNSIGNED NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `type` VARCHAR(50) NOT NULL DEFAULT 'assigned',
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT NULL,
            `link` VARCHAR(500) NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `read_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP NULL DEFAULT NULL,
            `updated_at` TIMESTAMP NULL DEFAULT NULL,
            KEY `task_notifications_user_read_idx` (`user_id`,`is_read`,`created_at`),
            KEY `task_notifications_task_idx` (`task_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Đếm số thông báo công việc chưa đọc của người dùng.
     */
    private function taskNotificationUnreadCount(int $userId): int
    {
        if (! Schema::hasTable('task_notifications')) {
            return 0;
        }

        return (int) DB::table('task_notifications')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->count();
    }

    /**
     * Lấy danh sách thông báo công việc đã chuẩn hóa để hiển thị.
     */
    private function taskNotificationItems(int $userId, int $limit)
    {
        if (! Schema::hasTable('task_notifications')) {
            return collect();
        }

        $query = DB::table('task_notifications as n');
        if (Schema::hasTable('tasks')) {
            $query->leftJoin('tasks as t', 't.id', '=', 'n.task_id')->addSelect('t.title as task_title');
        }

        return $query->addSelect('n.*')
            ->where('n.user_id', $userId)
            ->orderByDesc('n.created_at')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $createdAt = $this->formatDateTime($row->created_at);
                $taskTitle = $row->task_title ?? null;
                $link = $row->link ?: (! empty($row->task_id) ? url('/chat/tasks/'.$row->task_id) : route('notifications.index'));

                return [
                    'id' => 'task_'.$row->id,
                    'title' => $row->title ?: '📌 Công việc mới được giao',
                    'message' => $row->message ?: ($taskTitle ? 'Bạn vừa được giao công việc: '.$taskTitle : 'Bạn vừa được giao công việc mới.'),
                    'is_read' => (bool) $row->is_read,
                    'order_id' => null,
                    'task_id' => $row->task_id,
                    'created_at' => $createdAt,
                    'created_at_sort' => $this->dateForSort($row->created_at),
                    'time_text' => $createdAt,
                    'link' => $link,
                    'source' => 'task',
                    'type' => $row->type ?: 'assigned',
                ];
            });
    }

    /**
     * Đánh dấu tất cả thông báo công việc là đã đọc.
     */
    private function markAllTaskNotificationsAsRead(int $userId): void
    {
        if (! Schema::hasTable('task_notifications')) {
            return;
        }

        DB::table('task_notifications')
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => now(), 'updated_at' => now()]);
    }
    /* EGO_TASK_NOTIFY_CONTROLLER_END */

    /**
     * Chuẩn hóa nguồn thông báo (paginator/collection/mảng) về collection thống nhất.
     */
    private function normalizeNotificationItems($source)
    {
        if ($source instanceof AbstractPaginator) {
            return collect($source->items())->map(fn ($n) => $this->normalizeNotificationItem($n));
        }

        if ($source instanceof Collection) {
            return $source->map(fn ($n) => $this->normalizeNotificationItem($n));
        }

        if (is_object($source) && method_exists($source, 'items')) {
            return collect($source->items())->map(fn ($n) => $this->normalizeNotificationItem($n));
        }

        if (is_object($source) && isset($source->data)) {
            return collect($source->data)->map(fn ($n) => $this->normalizeNotificationItem($n));
        }

        return collect($source)->map(fn ($n) => $this->normalizeNotificationItem($n));
    }

    /**
     * Chuẩn hóa một thông báo thành mảng dữ liệu hiển thị thống nhất.
     */
    private function normalizeNotificationItem($n): array
    {
        if (is_array($n)) {
            $arr = $n;
        } elseif (is_object($n) && method_exists($n, 'toArray')) {
            $arr = $n->toArray();
        } elseif (is_object($n)) {
            $arr = get_object_vars($n);
        } else {
            $arr = [];
        }

        $data = $this->decodeData(data_get($arr, 'data', []));

        if (isset($data['data'])) {
            $nested = $this->decodeData($data['data']);
            if (is_array($nested)) {
                $data = array_merge($data, $nested);
            }
        }

        $id = data_get($arr, 'id') ?: data_get($data, 'id');

        $orderId = data_get($arr, 'order_id')
            ?: data_get($data, 'order_id')
            ?: data_get($data, 'orderId');

        $orderCode = data_get($arr, 'order_code')
            ?: data_get($data, 'order_code')
            ?: data_get($data, 'orderCode')
            ?: ($orderId ? '#'.$orderId : null);

        $title = $this->firstText([
            data_get($arr, 'title'),
            data_get($data, 'title'),
            data_get($data, 'subject'),
            data_get($data, 'heading'),
            data_get($data, 'name'),
        ]);

        $message = $this->firstText([
            data_get($arr, 'message'),
            data_get($arr, 'body'),
            data_get($arr, 'content'),
            data_get($data, 'message'),
            data_get($data, 'body'),
            data_get($data, 'content'),
            data_get($data, 'description'),
            data_get($data, 'text'),
        ]);

        $type = (string) (data_get($arr, 'type') ?: data_get($data, 'type') ?: '');

        if (! $title || $title === 'Thông báo') {
            $title = $this->guessTitle($type, $data, $orderCode);
        }

        if (! $message) {
            $message = $this->guessMessage($type, $data, $orderCode);
        }

        $link = data_get($arr, 'link')
            ?: data_get($data, 'link')
            ?: data_get($data, 'url');

        if (! $link && $orderId) {
            $link = url('/orders/'.$orderId);
        }

        if (! $link) {
            $link = route('notifications.index');
        }

        $createdRaw = data_get($arr, 'created_at')
            ?: data_get($data, 'created_at')
            ?: now();

        $createdAt = $this->formatDateTime($createdRaw);
        $createdSort = $this->dateForSort($createdRaw);

        $readAt = data_get($arr, 'read_at')
            ?: data_get($data, 'read_at');

        $isRead = array_key_exists('is_read', $arr)
            ? (bool) $arr['is_read']
            : ! empty($readAt);

        return [
            'id' => $id,
            'title' => $title ?: 'Thông báo',
            'message' => $message ?: 'Có cập nhật mới trong hệ thống.',
            'is_read' => $isRead,
            'order_id' => $orderId,
            'order_code' => $orderCode,
            'created_at' => $createdAt,
            'created_at_sort' => $createdSort,
            'time_text' => $createdAt,
            'link' => $link,
            'source' => 'system',
        ];
    }

    /**
     * Giải mã trường data của thông báo về mảng.
     */
    private function decodeData($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Lấy chuỗi không rỗng đầu tiên trong danh sách giá trị.
     */
    private function firstText(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $text = trim((string) $value);

            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * Suy đoán tiêu đề thông báo từ loại và dữ liệu.
     */
    private function guessTitle(string $type, array $data, ?string $orderCode): string
    {
        $lower = mb_strtolower($type.' '.json_encode($data, JSON_UNESCAPED_UNICODE), 'UTF-8');

        if (str_contains($lower, 'payment') || str_contains($lower, 'thanh toán')) {
            return 'Đã ghi nhận thanh toán';
        }

        if (str_contains($lower, 'export') || str_contains($lower, 'xuất kho')) {
            return 'Đơn hàng đã xuất kho';
        }

        if (str_contains($lower, 'status') || str_contains($lower, 'trạng thái')) {
            return 'Cập nhật trạng thái đơn hàng';
        }

        if ($orderCode) {
            return 'Cập nhật đơn hàng';
        }

        return 'Thông báo';
    }

    /**
     * Suy đoán nội dung thông báo từ dữ liệu đơn hàng/thanh toán.
     */
    private function guessMessage(string $type, array $data, ?string $orderCode): string
    {
        $status = data_get($data, 'status')
            ?: data_get($data, 'new_status')
            ?: data_get($data, 'to_status');

        $amount = data_get($data, 'amount')
            ?: data_get($data, 'paid_amount');

        if ($amount && $orderCode) {
            return 'Đã thu '.number_format((float) $amount, 0, ',', '.').'đ cho đơn '.$orderCode;
        }

        if ($status && $orderCode) {
            return 'Đơn hàng '.$orderCode.' đã cập nhật trạng thái: '.$status;
        }

        if ($orderCode) {
            return 'Đơn hàng '.$orderCode.' có cập nhật mới.';
        }

        return 'Có cập nhật mới trong hệ thống.';
    }

    /**
     * Định dạng thời gian dạng d/m/Y H:i, trả chuỗi rỗng nếu lỗi.
     */
    private function formatDateTime($value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Chuyển giá trị thời gian về chuỗi datetime dùng để sắp xếp.
     */
    private function dateForSort($value): string
    {
        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return now()->toDateTimeString();
        }
    }

    /**
     * Tạo các bảng thông báo nhân sự nếu chưa tồn tại.
     */
    private function ensureHrAnnouncementTables(): void
    {
        if (! Schema::hasTable('hr_announcements')) {
            DB::statement("
                CREATE TABLE hr_announcements (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    body LONGTEXT NULL,
                    category VARCHAR(80) NOT NULL DEFAULT 'general',
                    target_type VARCHAR(50) NOT NULL DEFAULT 'all',
                    department_id BIGINT UNSIGNED NULL,
                    user_id BIGINT UNSIGNED NULL,
                    starts_at DATETIME NULL,
                    ends_at DATETIME NULL,
                    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(50) NOT NULL DEFAULT 'published',
                    created_by BIGINT UNSIGNED NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (! Schema::hasTable('hr_announcement_reads')) {
            DB::statement('
                CREATE TABLE hr_announcement_reads (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    announcement_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    read_at TIMESTAMP NULL DEFAULT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    UNIQUE KEY hra_reads_unique (announcement_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        }
    }

    /**
     * Dựng query các thông báo nhân sự hiển thị được cho người dùng.
     */
    private function hrVisibleQuery(int $userId)
    {
        $this->ensureHrAnnouncementTables();

        $user = DB::table('users')->where('id', $userId)->first();
        $departmentId = $user->department_id ?? null;
        $now = now();

        return DB::table('hr_announcements as a')
            ->where('a.status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('a.starts_at')->orWhere('a.starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('a.ends_at')->orWhere('a.ends_at', '>=', $now);
            })
            ->where(function ($q) use ($userId, $departmentId) {
                $q->where('a.target_type', 'all')
                    ->orWhereNull('a.target_type')
                    ->orWhere(function ($qq) use ($departmentId) {
                        $qq->where('a.target_type', 'department')
                            ->where('a.department_id', $departmentId);
                    })
                    ->orWhere(function ($qq) use ($userId) {
                        $qq->where('a.target_type', 'user')
                            ->where('a.user_id', $userId);
                    });
            });
    }

    /**
     * Đếm số thông báo nhân sự chưa đọc của người dùng.
     */
    private function hrAnnouncementUnreadCount(int $userId): int
    {
        if (! Schema::hasTable('hr_announcements')) {
            return 0;
        }

        return (int) $this->hrVisibleQuery($userId)
            ->leftJoin('hr_announcement_reads as r', function ($join) use ($userId) {
                $join->on('r.announcement_id', '=', 'a.id')
                    ->where('r.user_id', '=', $userId);
            })
            ->whereNull('r.id')
            ->count('a.id');
    }

    /**
     * Lấy danh sách thông báo nhân sự đã chuẩn hóa để hiển thị.
     */
    private function hrAnnouncementItems(int $userId, int $limit)
    {
        if (! Schema::hasTable('hr_announcements')) {
            return collect();
        }

        return $this->hrVisibleQuery($userId)
            ->leftJoin('hr_announcement_reads as r', function ($join) use ($userId) {
                $join->on('r.announcement_id', '=', 'a.id')
                    ->where('r.user_id', '=', $userId);
            })
            ->select([
                'a.id',
                'a.title',
                'a.body',
                'a.category',
                'a.created_at',
                'a.is_pinned',
                'r.read_at',
            ])
            ->orderByDesc('a.is_pinned')
            ->orderByDesc('a.created_at')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $createdAt = $this->formatDateTime($row->created_at);

                return [
                    'id' => 'hr_'.$row->id,
                    'title' => 'Nhân sự: '.$row->title,
                    'message' => mb_strimwidth(strip_tags((string) $row->body), 0, 140, '...'),
                    'is_read' => ! empty($row->read_at),
                    'order_id' => null,
                    'created_at' => $createdAt,
                    'created_at_sort' => $this->dateForSort($row->created_at),
                    'time_text' => $createdAt,
                    'link' => url('/hr/announcements/'.$row->id),
                    'source' => 'hr',
                    'category' => $row->category,
                ];
            });
    }

    /**
     * Đánh dấu tất cả thông báo nhân sự là đã đọc.
     */
    private function markAllHrAnnouncementsAsRead(int $userId): void
    {
        if (! Schema::hasTable('hr_announcements')) {
            return;
        }

        $this->hrVisibleQuery($userId)
            ->select('a.id')
            ->orderBy('a.id')
            ->chunkById(100, function ($rows) use ($userId) {
                foreach ($rows as $row) {
                    DB::table('hr_announcement_reads')->updateOrInsert(
                        [
                            'announcement_id' => $row->id,
                            'user_id' => $userId,
                        ],
                        [
                            'read_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }, 'a.id', 'id');
    }
}
