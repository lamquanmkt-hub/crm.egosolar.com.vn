<?php

declare(strict_types=1);

namespace App\Models\Technical;

use App\Models\User;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Báo cáo ngày của kỹ thuật viên, gắn với MỘT đầu việc thật.
 *
 * Khác hẳn `technical_work_records` cũ (một kế hoạch chỉ có một báo cáo và bị
 * GHI ĐÈ mỗi lần lưu): ở đây mỗi lần báo cáo là MỘT BẢN GHI RIÊNG, nên một
 * người có thể báo cáo nhiều đầu việc trong cùng một ngày và lịch sử không
 * bao giờ bị mất.
 *
 * Liên kết nguồn công việc bằng cặp (source_type, source_id) — cố tình KHÔNG
 * đặt khoá ngoại vì nguồn nằm ở nhiều bảng khác nhau; bù lại có index và snapshot
 * tên công trình / tên đầu việc để báo cáo vẫn đọc được khi bản ghi gốc đổi.
 */
class TechnicalDailyReport extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVISION = 'revision_requested';

    /**
     * Nhãn tiếng Việt.
     *
     * Lưu ý nghiệp vụ: "đã gửi" và "chờ duyệt" là CÙNG MỘT trạng thái
     * (`submitted`) — gửi đi tức là đang chờ người có thẩm quyền duyệt.
     */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Nháp',
        self::STATUS_SUBMITTED => 'Đã gửi · chờ duyệt',
        self::STATUS_APPROVED => 'Đã duyệt',
        self::STATUS_REVISION => 'Yêu cầu sửa',
    ];

    public const STATUS_TONES = [
        self::STATUS_DRAFT => 'secondary',
        self::STATUS_SUBMITTED => 'warning',
        self::STATUS_APPROVED => 'success',
        self::STATUS_REVISION => 'danger',
    ];

    /** Trạng thái mà người tạo còn được sửa nội dung. */
    public const EDITABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_REVISION];

    public const SOURCE_TYPES = [
        TechnicalWorkItem::SOURCE_PROJECT_WORKFLOW,
        TechnicalWorkItem::SOURCE_TASK,
        TechnicalWorkItem::SOURCE_MAINTENANCE,
    ];

    protected $table = 'technical_daily_reports';

    protected $fillable = [
        'company_id',
        'user_id',
        'user_name',
        'report_date',
        'source_type',
        'source_id',
        'plan_item_id',
        'week_plan_id',
        'is_unplanned',
        'unplanned_reason',
        'site_id',
        'site_name',
        'work_title',
        'content',
        'result_achieved',
        'not_done_reason',
        'progress_percent',
        'work_hours',
        'materials_note',
        'issues_note',
        'next_plan',
        'status',
        'submitted_at',
        'approved_by',
        'approved_at',
        'review_note',
    ];

    protected $casts = [
        'report_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'progress_percent' => 'integer',
        'work_hours' => 'decimal:2',
        'is_unplanned' => 'boolean',
    ];

    /**
     * Ánh xạ trạng thái kỹ thuật (giai đoạn 1) sang NGÔN NGỮ NGHIỆP VỤ MỚI.
     *
     * Nghiệp vụ mới chỉ còn hai trạng thái mà nhân viên quan tâm:
     *   - "Nháp"                = draft
     *   - "Hoàn tất (đã nộp)"   = submitted HOẶC approved
     *   - revision_requested vẫn hiển thị riêng vì nhân viên phải sửa lại.
     *
     * Enum trong DB KHÔNG đổi (giữ nguyên luồng duyệt sẵn có ở backend); đây
     * thuần tuý là lớp hiển thị, nên 38 test của giai đoạn 1 không bị ảnh hưởng.
     */
    public const BUSINESS_STATUS_LABELS = [
        self::STATUS_DRAFT => 'Nháp',
        self::STATUS_SUBMITTED => 'Hoàn tất (đã nộp)',
        self::STATUS_APPROVED => 'Hoàn tất (đã nộp)',
        self::STATUS_REVISION => 'Cần cập nhật lại',
    ];

    /** Trạng thái được coi là "đã nộp" khi so sánh kế hoạch ↔ kết quả. */
    public const FINALIZED_STATUSES = [self::STATUS_SUBMITTED, self::STATUS_APPROVED];

    public function businessStatusLabel(): string
    {
        return self::BUSINESS_STATUS_LABELS[$this->status] ?? $this->statusLabel();
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, self::FINALIZED_STATUSES, true);
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(TechnicalPlanItem::class, 'plan_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(TechnicalDailyReportFile::class, 'report_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TechnicalDailyReportHistory::class, 'report_id')->latest('id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUS_TONES[$this->status] ?? 'secondary';
    }

    public function sourceLabel(): string
    {
        return TechnicalWorkItem::SOURCE_LABELS[$this->source_type] ?? (string) $this->source_type;
    }

    /** Người tạo còn được sửa nội dung hay không (chưa xét quyền của người xem). */
    public function isEditableByOwner(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    /** Đang chờ người có thẩm quyền xử lý. */
    public function isAwaitingReview(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
