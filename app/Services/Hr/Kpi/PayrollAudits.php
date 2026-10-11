<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Support\EgoCompanyLock;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Đối soát độ chính xác bảng lương HR (KPI HC04). Nguồn kiểm duy nhất là bảng lương Nhân sự
 * (hr_payroll_periods/lines); bảng lương cũ ở Tài chính (payrolls) không được cộng hay coi là tương đương.
 *
 * Một đợt = một kỳ lương + phạm vi (toàn bộ / phòng ban / chọn dòng) + HCNS chịu KPI + người nhập + người kiểm.
 * Mỗi dòng đã kiểm ghi các khoản đã kiểm, kết quả (đúng / có lỗi / chưa xác định) và ảnh chụp số tiền lúc kiểm.
 * Lỗi có khoản, loại, nội dung, nguồn, chứng cứ, cách xử lý; gỡ lỗi chỉ đánh dấu, lịch sử giữ nguyên.
 */
final class PayrollAudits
{
    public const SOURCE_TYPE = 'hr_payroll_audit';

    /** Chứng cứ của từng lỗi (hr_kpi_source_files.source_type). */
    public const ERROR_FILE_TYPE = 'hr_payroll_audit_error';

    public const SCOPES = [
        'all' => 'Toàn bộ dòng của kỳ lương',
        'department' => 'Một phòng ban',
        'lines' => 'Chọn từng dòng',
    ];

    public const LOG_LABELS = [
        'created' => 'Lập đợt', 'plan_updated' => 'Sửa đợt', 'items_saved' => 'Ghi kết quả', 'error_added' => 'Thêm lỗi',
        'error_updated' => 'Sửa lỗi', 'error_removed' => 'Gỡ lỗi', 'file_added' => 'Thêm tệp', 'file_deleted' => 'Xóa tệp',
        'submitted' => 'Gửi kiểm', 'cancelled' => 'Hủy đợt', 'review_invalidated' => 'Xác nhận/bản gửi hết hiệu lực',
    ];

    public static function ready(): bool
    {
        return SchemaCache::hasTable('hr_payroll_audits') && SchemaCache::hasTable('hr_payroll_audit_items')
            && SchemaCache::hasTable('hr_payroll_audit_errors') && SchemaCache::hasTable('hr_payroll_lines')
            && SchemaCache::hasTable('hr_kpi_source_logs') && HcnsSourceReviews::ready() && HcnsSourceFiles::ready();
    }

    /** @return array<string, array{label: string, fields: array<int, string>}> */
    public static function components(): array
    {
        return (array) config('hcns_kpi.payroll_components', []);
    }

    /** Số tiền/ngày công của dòng lương ở thời điểm kiểm (đủ các cột của mọi khoản). */
    public static function lineSnapshot(object $line): array
    {
        $out = [];
        foreach (self::components() as $definition) {
            foreach ($definition['fields'] as $field) {
                $out[$field] = property_exists($line, $field) && $line->{$field} !== null ? round((float) $line->{$field}, 4) : null;
            }
        }

        return $out;
    }

    public static function lineFingerprint(object $line): string
    {
        return hash('sha256', json_encode(self::lineSnapshot($line)));
    }

    /** Công ty của đợt: đúng công ty đang khóa hoặc chưa gán (dữ liệu cũ). */
    public static function inCompany(object $audit): bool
    {
        return $audit->company_id === null || (int) $audit->company_id === (int) EgoCompanyLock::id();
    }

    /** Dòng lương thuộc phạm vi đợt (đọc hiện tại; dòng bị xóa khi tính lại không còn trong danh sách). */
    public function scopeLines(object $audit): Collection
    {
        $q = DB::table('hr_payroll_lines')->where('period_id', $audit->period_id);
        if ($audit->scope === 'department') {
            $dept = trim((string) $audit->scope_department);
            $dept === 'Chưa gán phòng ban'
                ? $q->where(fn ($w) => $w->whereNull('department_name')->orWhere('department_name', ''))
                : $q->where('department_name', $dept);
        } elseif ($audit->scope === 'lines') {
            $q->whereIn('id', array_map('intval', json_decode((string) $audit->scope_line_ids, true) ?: [0]));
        }

        return $q->orderBy('department_name')->orderBy('employee_name')->orderBy('id')->get();
    }

    public function items(int $auditId): Collection
    {
        return DB::table('hr_payroll_audit_items')->where('audit_id', $auditId)->orderBy('id')->get();
    }

    /** Lỗi của đợt (kể cả đã gỡ — để hiện lịch sử). */
    public function errors(int $auditId): Collection
    {
        return DB::table('hr_payroll_audit_errors')->where('audit_id', $auditId)->orderBy('id')->get();
    }

    /** Nội dung người kiểm xác nhận: người, phạm vi, kết quả từng dòng (kèm số tiền lúc kiểm), lỗi, cách xử lý và tệp. */
    public function declared(object $audit): array
    {
        $files = app(HcnsSourceFiles::class);
        $errors = $this->errors((int) $audit->id);

        return [
            'owner_id' => (int) $audit->owner_id,
            'checker_id' => (int) $audit->checker_id,
            'period_id' => (int) $audit->period_id,
            'scope' => $audit->scope.':'.$audit->scope_department.':'.$audit->scope_line_ids,
            'items' => $this->items((int) $audit->id)->map(fn ($i) => implode('|', [$i->id, $i->line_id, $i->result, $i->components, $i->line_fingerprint, $i->note]))->all(),
            'errors' => $errors->map(fn ($e) => implode('|', [$e->id, $e->item_id, $e->component, $e->error_type, $e->description, $e->expected_amount,
                $e->recorded_amount, $e->source_url, $e->resolution, $e->resolution_status, $e->fixed_at, $e->removed_at]))->all(),
            'files' => $files->fingerprintPart(self::SOURCE_TYPE, (int) $audit->id),
            'error_files' => $errors->mapWithKeys(fn ($e) => [$e->id => $files->fingerprintPart(self::ERROR_FILE_TYPE, (int) $e->id)])->all(),
        ];
    }

    public function fingerprint(object $audit): string
    {
        return hash('sha256', json_encode($this->declared($audit), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Điều kiện gửi kiểm: đủ người, có ít nhất một dòng đã kết luận, kết quả khớp lỗi, lỗi có khoản đã kiểm.
     * Dòng “chưa xác định” được gửi nhưng vẫn là phần thiếu của số đo.
     *
     * @return array<int, string>
     */
    public function missing(object $audit): array
    {
        $missing = [];
        if (empty($audit->owner_id)) {
            $missing[] = 'HCNS chịu trách nhiệm';
        }
        if (empty($audit->reviewer_id)) {
            $missing[] = 'người kiểm';
        }
        $items = $this->items((int) $audit->id);
        $errors = $this->errors((int) $audit->id)->whereNull('removed_at')->groupBy('item_id');
        if ($items->whereIn('result', ['ok', 'error'])->isEmpty()) {
            $missing[] = 'ít nhất một dòng đã kết luận đúng/có lỗi';
        }
        foreach ($items as $item) {
            $active = $errors->get($item->id, collect());
            if ($item->result === 'error' && $active->isEmpty()) {
                $missing[] = 'lỗi chi tiết cho dòng “'.$item->employee_name.'” (đang ghi Có lỗi)';
            }
            if ($item->result !== 'error' && $active->isNotEmpty()) {
                $missing[] = 'kết quả “Có lỗi” cho dòng “'.$item->employee_name.'” (đang có '.$active->count().' lỗi)';
            }
        }

        return $missing;
    }

    /** Kỳ KPI của đợt theo quy tắc (mặc định nháp: tháng sau kỳ lương). */
    public static function kpiMonth(string $payrollMonth, array $rules): string
    {
        return ($rules['payroll_accuracy']['period_by'] ?? 'next_month') === 'payroll_month'
            ? $payrollMonth
            : Carbon::createFromFormat('Y-m-d', $payrollMonth.'-01')->addMonthNoOverflow()->format('Y-m');
    }

    /** Kỳ lương được đo trong một kỳ KPI (ngược của kpiMonth). */
    public static function payrollMonthFor(string $kpiMonth, array $rules): string
    {
        return ($rules['payroll_accuracy']['period_by'] ?? 'next_month') === 'payroll_month'
            ? $kpiMonth
            : Carbon::createFromFormat('Y-m-d', $kpiMonth.'-01')->subMonthNoOverflow()->format('Y-m');
    }

    /** Kỳ KPI chứa đợt này đã chốt lương (ảnh chụp đã lưu) → khóa mọi đường ghi của đợt. */
    public static function lockedMonth(object $audit): ?string
    {
        $next = Carbon::createFromFormat('Y-m-d', $audit->payroll_month.'-01')->addMonthNoOverflow()->format('Y-m');
        $rules = app(HcnsKpiRules::class)->forMonth($next)['rules'];

        return HcnsKpiRules::lockedMonth([self::kpiMonth((string) $audit->payroll_month, $rules)]);
    }

    public static function lockMessage(string $month): string
    {
        return 'Kỳ KPI tháng '.substr($month, 5, 2).'/'.substr($month, 0, 4).' đã chốt lương — đợt đối soát của kỳ này bị khóa (không sửa kết quả, lỗi, tệp hay người).';
    }

    /**
     * Phần đúng/sai của một dòng đã kiểm theo đơn vị và quy tắc lỗi đã sửa.
     *
     * @param  Collection  $errors  lỗi chưa gỡ của dòng
     * @return array{units: int, wrong: int, wrong_components: array<int, string>, excluded_fixed: int}
     */
    public static function unitResult(object $item, Collection $errors, array $rules, ?string $approvedAt): array
    {
        $rule = (array) ($rules['payroll_accuracy'] ?? []);
        $components = json_decode((string) $item->components, true) ?: [];
        $cutoff = $approvedAt ? Carbon::parse($approvedAt) : null;
        $counted = $errors->filter(function ($e) use ($rule, $cutoff) {
            if (($rule['fixed_errors'] ?? 'count_all') !== 'exclude_fixed_before_approval') {
                return true;
            }
            // Đã sửa trên bảng lương trước lúc duyệt lương (kỳ chưa duyệt: đã sửa là đủ) → không tính.
            $fixedBefore = $e->resolution_status === 'fixed' && $e->fixed_at && (! $cutoff || Carbon::parse($e->fixed_at)->lessThanOrEqualTo($cutoff));

            return ! $fixedBefore;
        });
        $wrongComponents = $counted->pluck('component')->unique()->values()->all();
        if (($rule['unit'] ?? 'line') === 'component') {
            return ['units' => count($components), 'wrong' => count(array_intersect($wrongComponents, $components)),
                'wrong_components' => $wrongComponents, 'excluded_fixed' => $errors->count() - $counted->count()];
        }

        return ['units' => 1, 'wrong' => $counted->isNotEmpty() ? 1 : 0, 'wrong_components' => $wrongComponents, 'excluded_fixed' => $errors->count() - $counted->count()];
    }

    public function log(int $id, string $action, ?string $note, ?int $actorId): void
    {
        DB::table('hr_kpi_source_logs')->insert([
            'source_type' => self::SOURCE_TYPE, 'source_id' => $id, 'action' => $action,
            'note' => $note === null ? null : mb_substr($note, 0, 4000), 'actor_id' => $actorId, 'created_at' => now(),
        ]);
    }

    public function logs(int $id): Collection
    {
        return DB::table('hr_kpi_source_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.actor_id')
            ->where('l.source_type', self::SOURCE_TYPE)->where('l.source_id', $id)->orderByDesc('l.id')
            ->get(['l.*', 'u.name as actor_name']);
    }

    public static function nextCode(): string
    {
        $prefix = 'DS-'.now()->format('ym').'-';
        $last = DB::table('hr_payroll_audits')->where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 3, '0', STR_PAD_LEFT);
    }
}
