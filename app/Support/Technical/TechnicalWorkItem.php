<?php

declare(strict_types=1);

namespace App\Support\Technical;

use Illuminate\Support\Carbon;

/**
 * Một đầu việc kỹ thuật đã được CHUẨN HOÁ từ nhiều nguồn khác nhau.
 *
 * Quan trọng: lớp này KHÔNG phải một bảng mới. Nó chỉ là hình dạng chung
 * (read-only) để 3 nguồn dữ liệu thật của hệ thống nói cùng một ngôn ngữ:
 *
 *   1. project_workflow_assignments  (giao việc theo bước quy trình Công trình)
 *   2. tasks (có site_id)            (giao việc rời gắn công trình)
 *   3. solar_maintenance_schedules   (lịch bảo trì / bảo hành)
 *
 * Công trình vẫn là nguồn chuẩn duy nhất; Kỹ thuật chỉ ĐỌC.
 */
final class TechnicalWorkItem
{
    public const SOURCE_PROJECT_WORKFLOW = 'project_workflow';

    public const SOURCE_TASK = 'task';

    public const SOURCE_MAINTENANCE = 'maintenance';

    public const SOURCE_LABELS = [
        self::SOURCE_PROJECT_WORKFLOW => 'Công trình',
        self::SOURCE_TASK => 'Task nội bộ',
        self::SOURCE_MAINTENANCE => 'Bảo trì / Bảo hành',
    ];

    /** Màu nhận diện nguồn trên lịch + nhãn (token CSS, không phải mã màu cứng trong PHP). */
    public const SOURCE_TONES = [
        self::SOURCE_PROJECT_WORKFLOW => 'project',
        self::SOURCE_TASK => 'task',
        self::SOURCE_MAINTENANCE => 'maintenance',
    ];

    public const GROUP_PENDING = 'pending';

    public const GROUP_IN_PROGRESS = 'in_progress';

    public const GROUP_DONE = 'done';

    public const GROUP_CANCELLED = 'cancelled';

    public const GROUP_LABELS = [
        self::GROUP_PENDING => 'Chờ thực hiện',
        self::GROUP_IN_PROGRESS => 'Đang thực hiện',
        self::GROUP_DONE => 'Hoàn thành',
        self::GROUP_CANCELLED => 'Đã huỷ / hoãn',
    ];

    /** Nhóm trạng thái còn phải làm — dùng cho "quá hạn", "đang phụ trách". */
    public const ACTIVE_GROUPS = [self::GROUP_PENDING, self::GROUP_IN_PROGRESS];

    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly ?int $siteId,
        public readonly ?string $siteName,
        public readonly ?string $siteCode,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?int $assignedUserId,
        public readonly ?string $assignedUserName,
        public readonly string $rawStatus,
        public readonly string $statusGroup,
        public readonly int $progress,
        public readonly ?Carbon $plannedDate,
        public readonly ?Carbon $dueDate,
        public readonly bool $isOverdue,
        public readonly string $reportStatus,
        public readonly ?string $url,
    ) {}

    /** Khoá khử trùng: một đầu việc = một nguồn + một bản ghi gốc + một người được giao. */
    public function key(): string
    {
        return $this->sourceType.':'.$this->sourceId.':'.($this->assignedUserId ?? 0);
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->sourceType] ?? $this->sourceType;
    }

    public function sourceTone(): string
    {
        return self::SOURCE_TONES[$this->sourceType] ?? 'task';
    }

    public function statusGroupLabel(): string
    {
        return self::GROUP_LABELS[$this->statusGroup] ?? $this->statusGroup;
    }

    public function isActive(): bool
    {
        return in_array($this->statusGroup, self::ACTIVE_GROUPS, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'site_id' => $this->siteId,
            'site_name' => $this->siteName,
            'site_code' => $this->siteCode,
            'title' => $this->title,
            'description' => $this->description,
            'assigned_user_id' => $this->assignedUserId,
            'assigned_user_name' => $this->assignedUserName,
            'status' => $this->statusGroup,
            'raw_status' => $this->rawStatus,
            'progress' => $this->progress,
            'planned_date' => $this->plannedDate?->toDateString(),
            'due_date' => $this->dueDate?->toDateTimeString(),
            'is_overdue' => $this->isOverdue,
            'report_status' => $this->reportStatus,
            'url' => $this->url,
        ];
    }
}
