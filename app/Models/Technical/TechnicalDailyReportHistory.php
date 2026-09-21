<?php

declare(strict_types=1);

namespace App\Models\Technical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nhật ký thao tác trên báo cáo ngày kỹ thuật.
 *
 * Theo đúng mẫu `PaymentRequestEditLog` của đợt P0: CHỈ THÊM dòng mới, không
 * sửa, không xoá; và cột `report_id` cố tình KHÔNG có khoá ngoại cascade để
 * lịch sử sống sót kể cả khi báo cáo gốc bị xoá.
 */
class TechnicalDailyReportHistory extends Model
{
    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_SUBMIT = 'submit';

    public const ACTION_APPROVE = 'approve';

    public const ACTION_REQUEST_REVISION = 'request_revision';

    public const ACTION_REOPEN = 'reopen';

    public const ACTION_DELETE = 'delete';

    public const ACTION_LABELS = [
        self::ACTION_CREATE => 'Tạo báo cáo',
        self::ACTION_UPDATE => 'Cập nhật nội dung',
        self::ACTION_SUBMIT => 'Gửi duyệt',
        self::ACTION_APPROVE => 'Duyệt báo cáo',
        self::ACTION_REQUEST_REVISION => 'Yêu cầu sửa',
        self::ACTION_REOPEN => 'Mở lại báo cáo',
        self::ACTION_DELETE => 'Xoá báo cáo',
    ];

    protected $table = 'technical_daily_report_histories';

    protected $fillable = [
        'report_id',
        'user_id',
        'user_name',
        'action',
        'status_before',
        'status_after',
        'note',
        'ip_address',
        'user_agent',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(TechnicalDailyReport::class, 'report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? (string) $this->action;
    }
}
