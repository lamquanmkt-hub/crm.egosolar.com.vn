<?php

namespace App\Models\Payments;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nhật ký thao tác trên phiếu Đề nghị thanh toán.
 *
 * Mỗi dòng = một thay đổi (một trường được sửa) hoặc một hành động
 * (duyệt / từ chối / hủy / xóa). Bản ghi chỉ được THÊM, không sửa, không xóa.
 */
class PaymentRequestEditLog extends Model
{
    public const ACTION_EDIT = 'edit';

    public const ACTION_APPROVE = 'approve';

    public const ACTION_REJECT = 'reject';

    public const ACTION_CANCEL = 'cancel';

    public const ACTION_DELETE = 'delete';

    public const ACTION_FORCE_DELETE = 'force_delete';

    public const ACTION_RESTORE = 'restore';

    public const ACTION_COPY = 'copy';

    public const ACTION_LABELS = [
        self::ACTION_EDIT => 'Sửa phiếu',
        self::ACTION_APPROVE => 'Duyệt',
        self::ACTION_REJECT => 'Từ chối',
        self::ACTION_CANCEL => 'Hủy phiếu',
        self::ACTION_DELETE => 'Xóa phiếu',
        self::ACTION_FORCE_DELETE => 'Xóa vĩnh viễn',
        self::ACTION_RESTORE => 'Khôi phục',
        self::ACTION_COPY => 'Sao chép',
    ];

    protected $table = 'payment_request_edit_logs';

    protected $fillable = [
        'payment_request_id',
        'payment_request_code',
        'user_id',
        'user_name',
        'action_type',
        'status_before',
        'status_after',
        'field_name',
        'old_value',
        'new_value',
        'reason',
        'ip_address',
        'user_agent',
    ];

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action_type] ?? (string) $this->action_type;
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class, 'payment_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
