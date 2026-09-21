<?php

namespace App\Services\Payments;

use App\Models\Payments\PaymentRequestEditLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi nhật ký thao tác cho phiếu Đề nghị thanh toán.
 *
 * Nguyên tắc:
 * - CHỈ thêm dòng mới, không bao giờ sửa/xóa dòng cũ.
 * - Không bao giờ làm hỏng nghiệp vụ: nếu bảng log chưa được migrate hoặc
 *   ghi log lỗi, thao tác chính vẫn chạy (lỗi được `report()` để xem ở log).
 * - Mỗi trường bị sửa ghi thành MỘT dòng riêng (field_name / old_value /
 *   new_value) — dễ đọc trên timeline và dễ truy vết từng giá trị, hợp với
 *   cách `role_permission_audits` đang làm nhưng chi tiết hơn tới cấp cột.
 */
class PaymentRequestAuditLogger
{
    /**
     * Các trạng thái coi là "đã duyệt / đã hoàn tất" — sửa/hủy/xóa ở các
     * trạng thái này bắt buộc phải có lý do và phải được ghi log.
     *
     * Gồm enum thật của bảng `payment_requests` (admin_approved,
     * accounting_approved) cộng các biến thể legacy đang được
     * `LockCompletedFinanceRecords` middleware nhận diện.
     */
    public const LOCKED_STATUSES = [
        'admin_approved',
        'accounting_approved',
        'paid',
        'completed',
        'complete',
        'done',
        'closed',
        'da_chi',
        'da_thanh_toan',
        'ke_toan_da_chi',
        'ketoan_da_chi',
    ];

    /** Độ dài tối thiểu của lý do khi sửa/hủy/xóa phiếu đã duyệt. */
    public const REASON_MIN_LENGTH = 5;

    public static function isLockedStatus(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), self::LOCKED_STATUSES, true);
    }

    /** Quy tắc validate lý do, dùng chung cho update/cancel/destroy. */
    public static function reasonRules(): array
    {
        return ['required', 'string', 'min:'.self::REASON_MIN_LENGTH, 'max:2000'];
    }

    public static function reasonMessages(): array
    {
        return [
            'audit_reason.required' => 'Phiếu đã duyệt/đã chi: bắt buộc nhập lý do thay đổi.',
            'audit_reason.min' => 'Lý do thay đổi phải có ít nhất '.self::REASON_MIN_LENGTH.' ký tự.',
        ];
    }

    /** Bảng log đã được migrate chưa. */
    public static function available(): bool
    {
        try {
            return Schema::hasTable('payment_request_edit_logs');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * SQLSTATE cho "base table or view not found" (MySQL/MariaDB + chuẩn ANSI).
     * Chỉ đúng MÃ NÀY mới được coi là "chưa migrate" và bỏ qua êm.
     */
    private const SQLSTATE_TABLE_NOT_FOUND = '42S02';

    /**
     * Ghi một dòng log.
     *
     * QUY TẮC XỬ LÝ LỖI (quan trọng cho một nhật ký bảo mật):
     *
     * - Nếu bảng CHƯA được migrate: bỏ qua êm (ghi cảnh báo vào log hệ thống).
     *   Đây là giai đoạn chuyển tiếp trước khi deploy migration, không được
     *   làm gãy nghiệp vụ đang chạy.
     * - Nếu bảng ĐÃ tồn tại mà ghi log thất bại vì bất kỳ lý do nào khác
     *   (deadlock, mất kết nối, sai kiểu cột, đầy đĩa...): NÉM LỖI RA NGOÀI.
     *   Tuyệt đối KHÔNG nuốt lỗi — vì lời gọi nằm trong DB::transaction()
     *   của hành động sửa/xóa/duyệt, ném lỗi sẽ rollback luôn thay đổi dữ
     *   liệu chính. Không bao giờ được phép có thay đổi dữ liệu tài chính mà
     *   thiếu dòng nhật ký tương ứng.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws \Throwable khi bảng đã tồn tại nhưng ghi log thất bại
     */
    public static function log(array $attributes): void
    {
        /*
         * Cố tình KHÔNG dùng self::available() làm điều kiện tiên quyết ở đây:
         * nếu Schema::hasTable() lỗi vì lý do tạm thời (mất kết nối), tiền
         * kiểm sẽ âm thầm bỏ qua việc ghi log. Thay vào đó cứ ghi thẳng, rồi
         * chỉ tha thứ đúng lỗi "bảng không tồn tại".
         */
        try {
            $user = auth()->user();

            PaymentRequestEditLog::create(array_merge([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'ip_address' => self::ip(),
                'user_agent' => self::userAgent(),
            ], $attributes));
        } catch (QueryException $e) {
            // Trường hợp đua (bảng bị xóa giữa chừng / chưa migrate thật sự):
            // vẫn coi là "chưa có bảng" và bỏ qua êm.
            if (self::isTableNotFound($e)) {
                self::reportMissingTable($attributes);

                return;
            }

            throw $e;
        }
    }

    private static function isTableNotFound(QueryException $e): bool
    {
        if (($e->getCode() === self::SQLSTATE_TABLE_NOT_FOUND)) {
            return true;
        }

        return str_contains(
            strtolower($e->getMessage()),
            'base table or view not found'
        );
    }

    /**
     * Bảng nhật ký chưa tồn tại: không làm gãy nghiệp vụ, nhưng phải để lại
     * dấu vết trong log hệ thống để vận hành biết đang thiếu audit trail.
     */
    private static function reportMissingTable(array $attributes): void
    {
        try {
            Log::warning('payment_request_edit_logs chưa tồn tại — bỏ qua ghi nhật ký ĐNTT.', [
                'payment_request_id' => $attributes['payment_request_id'] ?? null,
                'action_type' => $attributes['action_type'] ?? null,
                'user_id' => auth()->id(),
            ]);
        } catch (\Throwable) {
            // Không bao giờ để việc ghi cảnh báo làm hỏng nghiệp vụ.
        }
    }

    /**
     * Ghi log cho một hành động không gắn với thay đổi trường cụ thể
     * (duyệt / từ chối / hủy / xóa / sao chép).
     */
    public static function logAction(
        int $paymentRequestId,
        ?string $code,
        string $actionType,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?string $reason = null,
    ): void {
        self::log([
            'payment_request_id' => $paymentRequestId,
            'payment_request_code' => $code,
            'action_type' => $actionType,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'reason' => $reason,
        ]);
    }

    /**
     * Ghi log cho một lần sửa: mỗi trường thay đổi là một dòng.
     *
     * @param  array<string, mixed>  $before  Giá trị trước khi sửa
     * @param  array<string, mixed>  $after   Giá trị sau khi sửa
     * @return int Số trường thực sự thay đổi
     */
    public static function logFieldChanges(
        int $paymentRequestId,
        ?string $code,
        array $before,
        array $after,
        ?string $reason = null,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
    ): int {
        $changed = 0;

        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;

            if (self::stringify($oldValue) === self::stringify($newValue)) {
                continue;
            }

            $changed++;

            self::log([
                'payment_request_id' => $paymentRequestId,
                'payment_request_code' => $code,
                'action_type' => PaymentRequestEditLog::ACTION_EDIT,
                'status_before' => $statusBefore,
                'status_after' => $statusAfter,
                'field_name' => (string) $field,
                'old_value' => self::stringify($oldValue),
                'new_value' => self::stringify($newValue),
                'reason' => $reason,
            ]);
        }

        return $changed;
    }

    public static function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private static function ip(): ?string
    {
        try {
            return app()->bound('request') ? app(Request::class)->ip() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function userAgent(): ?string
    {
        try {
            return app()->bound('request') ? app(Request::class)->userAgent() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
