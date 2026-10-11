<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Nguồn KPI tuyển dụng HC01–HC03 (đợt hcns-kpi P5).
 *
 * - Lượt tuyển (`hr_recruitment_hires`): một suất của yêu cầu tuyển đã duyệt → ứng viên → thư mời → tài khoản
 *   nhân viên. HC01 đo số ngày từ mốc HCNS tiếp nhận yêu cầu tới ngày nhận việc THỰC TẾ (không dùng ngày tạo/dự kiến).
 *   HC03 đọc kết quả thử việc của chính lượt tuyển (đang thử việc / đạt / nghỉ trong thử việc / chưa xác định).
 * - Chi phí (`hr_recruitment_costs`): phân bổ chứng từ đề nghị thanh toán đang có cho đợt tuyển. Chỉ tính phần đã
 *   thực chi: ĐNTT/phiếu chi kế toán đã duyệt; tạm ứng chỉ tính số đã quyết toán; hoàn tiền trừ ra.
 * Mỗi phần có xác nhận độc lập riêng (HcnsSourceReviews: recruit_time, probation_quit, recruit_cost).
 */
final class RecruitmentKpi
{
    public const REQUEST_TYPE = 'hr_recruitment_request';

    public const HIRE_TYPE = 'hr_recruitment_hire';

    public const COST_TYPE = 'hr_recruitment_cost';

    public const ATTACHMENT_ENTITY = 'hires';

    public const LOG_LABELS = [
        'created' => 'Tạo', 'kpi_updated' => 'Sửa phần KPI', 'cancelled' => 'Hủy', 'updated' => 'Sửa lượt tuyển',
        'probation_updated' => 'Sửa kết quả thử việc', 'file_added' => 'Thêm tệp', 'file_deleted' => 'Xóa tệp',
        'review_invalidated' => 'Xác nhận/bản gửi hết hiệu lực', 'kpi_draft' => 'Lưu nháp', 'kpi_submit' => 'Gửi kiểm',
        'kpi_confirm' => 'Xác nhận', 'kpi_return' => 'Trả bổ sung', 'adjusted' => 'Điều chỉnh', 'removed' => 'Gỡ khoản chi',
    ];

    /** Loại chứng từ được phép phân bổ. */
    public const DOC_TYPES = [
        'payment_request' => 'Đề nghị thanh toán',
        'payment_voucher' => 'Phiếu chi',
        'advance' => 'Tạm ứng',
        'refund_request' => 'Hoàn tiền / hoàn ứng',
    ];

    public static function ready(): bool
    {
        return SchemaCache::hasTable('hr_recruitment_hires') && SchemaCache::hasTable('hr_recruitment_costs')
            && SchemaCache::hasColumn('hr_recruitment_requests', 'request_received_at')
            && HcnsSourceReviews::ready() && SchemaCache::hasTable('hr_kpi_source_logs')
            && SchemaCache::hasTable('hr_recruitment_attachments');
    }

    public static function costsReady(): bool
    {
        return self::ready() && SchemaCache::hasTable('payment_requests');
    }

    // ---------------------------------------------------------------- HC01 lượt tuyển

    public function hireDeclared(object $hire): array
    {
        $request = DB::table('hr_recruitment_requests')->where('id', $hire->request_id)->first();

        return [
            'request_id' => (int) $hire->request_id,
            'request_received_at' => (string) ($request->request_received_at ?? ''),
            'candidate_id' => (int) ($hire->candidate_id ?? 0),
            'employee_user_id' => (int) ($hire->employee_user_id ?? 0),
            'owner_id' => (int) ($hire->owner_id ?? 0),
            'status' => (string) $hire->status,
            'actual_start_date' => (string) ($hire->actual_start_date ?? ''),
            'files' => $this->filePart((int) $hire->id),
        ];
    }

    public function hireFingerprint(object $hire): string
    {
        return hash('sha256', json_encode($this->hireDeclared($hire), JSON_UNESCAPED_UNICODE));
    }

    /** @return array<int, string> dữ kiện còn thiếu trước khi gửi kiểm HC01 */
    public function hireMissing(object $hire): array
    {
        $request = DB::table('hr_recruitment_requests')->where('id', $hire->request_id)->first();
        $missing = [];
        if (! $request || ! in_array((string) $request->approval_status, ['approved', 'closed'], true)) {
            $missing[] = 'yêu cầu tuyển đã duyệt';
        }
        if (empty($request?->request_received_at)) {
            $missing[] = 'mốc HCNS tiếp nhận yêu cầu';
        }
        foreach (['owner_id' => 'HCNS phụ trách', 'reviewer_id' => 'người kiểm', 'candidate_id' => 'ứng viên', 'employee_user_id' => 'tài khoản nhân viên'] as $field => $label) {
            if (empty($hire->{$field})) {
                $missing[] = $label;
            }
        }
        if ($hire->status !== 'started') {
            $missing[] = 'trạng thái “Đã nhận việc thực tế”';
        }
        if (empty($hire->actual_start_date)) {
            $missing[] = 'ngày nhận việc thực tế';
        }
        if ($this->files((int) $hire->id)->isEmpty()) {
            $missing[] = 'chứng cứ nhận việc (HĐ/biên bản/quyết định)';
        }

        return $missing;
    }

    /** Số ngày lịch từ mốc tiếp nhận yêu cầu tới ngày nhận việc thực tế. */
    public static function recruitDays(?string $receivedAt, ?string $startDate): ?int
    {
        if (! $receivedAt || ! $startDate) {
            return null;
        }
        $from = Carbon::parse($receivedAt)->startOfDay();
        $to = Carbon::parse($startDate)->startOfDay();

        return $to->lessThan($from) ? null : (int) $from->diffInDays($to);
    }

    // ---------------------------------------------------------------- HC03 thử việc

    public function probationDeclared(object $hire): array
    {
        return [
            'employee_user_id' => (int) ($hire->employee_user_id ?? 0),
            'actual_start_date' => (string) ($hire->actual_start_date ?? ''),
            'probation_start_date' => (string) ($hire->probation_start_date ?? ''),
            'probation_end_date' => (string) ($hire->probation_end_date ?? ''),
            'probation_outcome' => (string) ($hire->probation_outcome ?? ''),
            'probation_left_date' => (string) ($hire->probation_left_date ?? ''),
            'probation_left_type' => (string) ($hire->probation_left_type ?? ''),
            'probation_reason' => (string) ($hire->probation_reason ?? ''),
            'files' => $this->filePart((int) $hire->id),
        ];
    }

    public function probationFingerprint(object $hire): string
    {
        return hash('sha256', json_encode($this->probationDeclared($hire), JSON_UNESCAPED_UNICODE));
    }

    /** @return array<int, string> */
    public function probationMissing(object $hire): array
    {
        $missing = [];
        if ($hire->status !== 'started' || empty($hire->employee_user_id)) {
            $missing[] = 'lượt tuyển đã nhận việc và nối tài khoản nhân viên';
        }
        foreach (['probation_start_date' => 'ngày bắt đầu thử việc', 'probation_end_date' => 'ngày kết thúc thử việc', 'probation_outcome' => 'kết quả thử việc', 'reviewer_id' => 'người kiểm'] as $field => $label) {
            if (empty($hire->{$field})) {
                $missing[] = $label;
            }
        }
        if (($hire->probation_outcome ?? null) === 'left') {
            foreach (['probation_left_date' => 'ngày nghỉ thực', 'probation_left_type' => 'hình thức nghỉ', 'probation_reason' => 'lý do nghỉ'] as $field => $label) {
                if (empty($hire->{$field})) {
                    $missing[] = $label;
                }
            }
        }
        if ($this->files((int) $hire->id)->isEmpty()) {
            $missing[] = 'chứng cứ (quyết định/biên bản/đơn nghỉ)';
        }

        return $missing;
    }

    /** Tháng của cohort HC03: ngày nghỉ thực nếu nghỉ trong thử việc, ngược lại ngày kết thúc thử việc. */
    public static function probationMonth(object $hire): ?string
    {
        $date = ($hire->probation_outcome ?? null) === 'left' && ! empty($hire->probation_left_date)
            ? $hire->probation_left_date : ($hire->probation_end_date ?? null);

        return $date ? Carbon::parse($date)->format('Y-m') : null;
    }

    // ---------------------------------------------------------------- HC02 chi phí

    /**
     * Giá trị hợp lệ của chứng từ: chỉ phần đã thực chi. Tạm ứng = số đã quyết toán (không tính tiền tạm ứng);
     * hoàn tiền mang dấu âm. valid=false → chưa thực chi/bị từ chối, kèm lý do.
     *
     * @return array{id:int, code:string, type:string, type_label:string, status:string, amount:float, valid:bool, valid_amount:?float, valid_at:?string, sign:int, reason:string, created_by:?int}
     */
    public function document(int $paymentRequestId): ?array
    {
        $doc = DB::table('payment_requests')->where('id', $paymentRequestId)->first();
        if (! $doc) {
            return null;
        }
        $type = (string) ($doc->doc_type ?: 'payment_request');
        $base = ['id' => (int) $doc->id, 'code' => (string) ($doc->code ?: 'ĐNTT#'.$doc->id), 'type' => $type, 'type_label' => self::DOC_TYPES[$type] ?? $type,
            'status' => (string) $doc->status, 'amount' => (float) $doc->amount, 'created_by' => $doc->created_by ? (int) $doc->created_by : null,
            'content' => (string) ($doc->payment_content ?: $doc->reason), 'sign' => $type === 'refund_request' ? -1 : 1];
        if (! isset(self::DOC_TYPES[$type])) {
            return $base + ['valid' => false, 'valid_amount' => null, 'valid_at' => null, 'reason' => 'Loại chứng từ không dùng cho chi phí tuyển dụng.'];
        }
        if ($type === 'advance') {
            $s = SchemaCache::hasTable('advance_settlements') ? DB::table('advance_settlements')->where('payment_request_id', $doc->id)->first() : null;
            if (! $s || $s->status !== 'completed') {
                return $base + ['valid' => false, 'valid_amount' => null, 'valid_at' => null,
                    'reason' => 'Tạm ứng chưa quyết toán xong — không tính tiền tạm ứng, chờ số thực chi khi quyết toán.'];
            }

            return $base + ['valid' => true, 'valid_amount' => (float) $s->actual_spent_amount, 'valid_at' => (string) $s->completed_at,
                'reason' => 'Đã quyết toán: thực chi '.number_format((float) $s->actual_spent_amount, 0, ',', '.').' đ.'];
        }
        if ($doc->status !== 'accounting_approved') {
            return $base + ['valid' => false, 'valid_amount' => null, 'valid_at' => null,
                'reason' => str_contains((string) $doc->status, 'rejected') ? 'Chứng từ bị từ chối — không tính.' : 'Chứng từ chưa được kế toán duyệt chi — chưa phải thực chi.'];
        }

        return $base + ['valid' => true, 'valid_amount' => (float) $doc->amount, 'valid_at' => (string) ($doc->accounting_approved_at ?: $doc->updated_at),
            'reason' => 'Kế toán đã duyệt chi.'];
    }

    /** Tổng đã phân bổ của một chứng từ cho mọi đợt tuyển (trừ một dòng). */
    public function allocatedElsewhere(int $paymentRequestId, ?int $exceptCostId = null): float
    {
        return (float) DB::table('hr_recruitment_costs')->where('payment_request_id', $paymentRequestId)->whereNull('removed_at')
            ->when($exceptCostId, fn ($q) => $q->where('id', '!=', $exceptCostId))->sum('allocated_amount');
    }

    public function costDeclared(object $cost): array
    {
        $doc = $this->document((int) $cost->payment_request_id);

        return [
            'request_id' => (int) $cost->request_id,
            'payment_request_id' => (int) $cost->payment_request_id,
            'allocated_amount' => number_format((float) $cost->allocated_amount, 2, '.', ''),
            'owner_id' => (int) ($cost->owner_id ?? 0),
            'removed' => ! empty($cost->removed_at),
            'doc_valid_amount' => $doc['valid_amount'] ?? null,
            'doc_status' => $doc['status'] ?? null,
        ];
    }

    public function costFingerprint(object $cost): string
    {
        return hash('sha256', json_encode($this->costDeclared($cost), JSON_UNESCAPED_UNICODE));
    }

    /** @return array<int, string> */
    public function costMissing(object $cost): array
    {
        $missing = [];
        $doc = $this->document((int) $cost->payment_request_id);
        if (! $doc) {
            $missing[] = 'chứng từ';
        } elseif (! $doc['valid']) {
            $missing[] = mb_strtolower(rtrim($doc['reason'], '.'));
        } elseif ($this->allocatedElsewhere((int) $cost->payment_request_id) > (float) $doc['valid_amount'] + 0.004) {
            $missing[] = 'tổng phân bổ của chứng từ vượt số thực chi — điều chỉnh số phân bổ';
        }
        foreach (['owner_id' => 'HCNS phụ trách', 'reviewer_id' => 'người kiểm'] as $field => $label) {
            if (empty($cost->{$field})) {
                $missing[] = $label;
            }
        }
        if ((float) $cost->allocated_amount <= 0) {
            $missing[] = 'số phân bổ';
        }

        return $missing;
    }

    /** Tháng tính chi phí: ngày thực chi của chứng từ (duyệt chi / hoàn tất quyết toán). */
    public function costMonth(object $cost): ?string
    {
        $doc = $this->document((int) $cost->payment_request_id);

        return ! empty($doc['valid_at']) ? Carbon::parse($doc['valid_at'])->format('Y-m') : null;
    }

    // ---------------------------------------------------------------- dùng chung

    /** Kỳ đã chốt lương ảnh hưởng tới lượt tuyển (HC01/HC02 theo tháng nhận việc; HC03 theo tháng cohort). */
    public static function hireLockedMonth(object $hire, array $extraDates = []): ?string
    {
        $months = array_filter(array_map(fn ($d) => $d ? Carbon::parse($d)->format('Y-m') : null,
            array_merge([$hire->actual_start_date ?? null], $extraDates)));

        return HcnsKpiRules::lockedMonth(array_values($months));
    }

    public static function probationLockedMonth(object $hire, array $extraDates = []): ?string
    {
        $months = array_filter(array_merge([self::probationMonth($hire)], array_map(fn ($d) => $d ? Carbon::parse($d)->format('Y-m') : null, $extraDates)));

        return HcnsKpiRules::lockedMonth(array_values($months));
    }

    public function costLockedMonth(object $cost): ?string
    {
        return ($m = $this->costMonth($cost)) ? HcnsKpiRules::lockedMonth([$m]) : null;
    }

    public static function lockMessage(string $month, string $what): string
    {
        return 'Kỳ KPI tháng '.substr($month, 5, 2).'/'.substr($month, 0, 4).' đã chốt lương — '.$what.' của kỳ này bị khóa. Mở lại kỳ lương theo quy trình nếu cần sửa.';
    }

    public function files(int $hireId): Collection
    {
        return DB::table('hr_recruitment_attachments as a')->leftJoin('users as u', 'u.id', '=', 'a.uploaded_by')
            ->where('a.entity', self::ATTACHMENT_ENTITY)->where('a.record_id', $hireId)->orderBy('a.id')
            ->get(['a.*', 'u.name as uploader_name']);
    }

    private function filePart(int $hireId): array
    {
        return $this->files($hireId)->map(fn ($f) => $f->id.':'.$f->original_name.':'.$f->size)->all();
    }

    public function log(string $type, int $id, string $action, ?string $note, ?int $actorId): void
    {
        DB::table('hr_kpi_source_logs')->insert([
            'source_type' => $type, 'source_id' => $id, 'action' => $action,
            'note' => $note === null ? null : mb_substr($note, 0, 4000), 'actor_id' => $actorId, 'created_at' => now(),
        ]);
    }

    public function logs(string $type, int $id): Collection
    {
        return DB::table('hr_kpi_source_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.actor_id')
            ->where('l.source_type', $type)->where('l.source_id', $id)->orderByDesc('l.id')
            ->get(['l.*', 'u.name as actor_name']);
    }
}
