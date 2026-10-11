<?php

namespace App\Services\TechnicalKpi;

use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Đánh giá KPI kỹ sư theo đúng tài liệu chuẩn của Ban lãnh đạo
 * (config/technical_kpi_standard.php). CHỈ ĐỌC: không ghi DB, không tạo dữ liệu.
 *
 * Nguyên tắc:
 *   - Tiêu chí không có nguồn dữ liệu đã xác minh => trạng thái "Chưa đủ dữ liệu",
 *     giá trị null (không bao giờ quy về 0 điểm).
 *   - Tiêu chí chưa được lãnh đạo xác nhận (pending_confirmation) => "Chờ xác nhận
 *     nghiệp vụ", không tính điểm, không cộng vào tổng.
 *   - Tổng KPI = tổng điểm thành phần, chỉ có giá trị khi MỌI tiêu chí có trọng số
 *     đều đủ dữ liệu.
 *   - Minh chứng công trình chỉ được dùng khi đã có người duyệt (approved_at).
 */
class KpiStandardEvaluator
{
    public const STATUS_PASS = 'pass';

    public const STATUS_FAIL = 'fail';

    public const STATUS_NO_DATA = 'no_data';

    public const STATUS_PENDING = 'pending';

    public const STATUS_LABELS = [
        self::STATUS_PASS => 'Đạt',
        self::STATUS_FAIL => 'Chưa đạt',
        self::STATUS_NO_DATA => 'Chưa đủ dữ liệu',
        self::STATUS_PENDING => 'Chờ xác nhận nghiệp vụ',
    ];

    public const DATA_FULL = 'full';

    public const DATA_PARTIAL = 'partial';

    public const DATA_NONE = 'none';

    public const DATA_LABELS = [
        self::DATA_FULL => 'Đủ dữ liệu',
        self::DATA_PARTIAL => 'Thiếu dữ liệu',
        self::DATA_NONE => 'Chưa đánh giá',
    ];

    /** Nguồn: Trưởng phòng nhập Kế hoạch / Thực tế ở trang Nhập số liệu KPI tháng. */
    public const SOURCE_MANUAL_LABEL = 'Nhập tay theo tháng (Trưởng phòng)';

    /** Tiêu chí lấy từ cấu hình đã duyệt ở trang Cài đặt KPI (null = dùng file chuẩn). */
    private ?array $configCriteria = null;

    /** Bộ quy tắc đã chọn cho kỳ (null = chưa có phiên bản được duyệt có hiệu lực). */
    private ?array $rule = null;

    /** Tháng đầu của kỳ đánh giá (Y-m) — quyết định bộ tiêu chí chuẩn áp dụng; null = tháng hiện tại. */
    private ?string $periodMonth = null;

    public function __construct(private readonly ProjectKpiLinkService $links) {}

    /** Thông tin bộ quy tắc gắn với kết quả: phiên bản, ngày hiệu lực hoặc lý do chưa có. */
    public function rule(): array
    {
        $revision = $this->revisionFor($this->normalizeMonth($this->periodMonth));

        return $this->rule ?? [
            'config_id' => null,
            'version' => null,
            'label' => 'Tài liệu chuẩn'.($revision ? ' hiệu lực '.Carbon::parse($revision['effective_from'])->format('d/m/Y') : ' (bộ gốc)')
                .' — chưa có phiên bản cấu hình được duyệt có hiệu lực cho kỳ này',
            'effective_date' => $revision['effective_from'] ?? null,
            'has_config' => false,
            'config_stale' => false,
        ];
    }

    /**
     * Cách xử lý công trình quá hạn nhưng chưa bàn giao ở tiêu chí Tiến độ (DECISIONS D07).
     * list_only: chỉ liệt kê, chưa vào mẫu số (đúng công thức "/ Tổng số hệ thống bàn giao");
     * count_as_late: tính là một hệ thống trễ hạn trong kỳ.
     */
    public function openOverduePolicy(): string
    {
        $policy = (string) config('technical_kpi_standard.timeline.open_overdue_policy', 'list_only');

        return in_array($policy, ['list_only', 'count_as_late'], true) ? $policy : 'list_only';
    }

    /*
    |--------------------------------------------------------------------------
    | Chuẩn KPI
    |--------------------------------------------------------------------------
    */

    /**
     * Dùng tiêu chí + trọng số của phiên bản cấu hình đã duyệt (trang Cài đặt KPI) thay cho file chuẩn.
     * Tiêu chí không tick "tính điểm" hoặc chưa ở trạng thái áp dụng => không trọng số (chờ xác nhận).
     */
    public function useConfig(?\App\Models\TechnicalKpiConfig $config, ?string $month = null): static
    {
        $this->periodMonth = $this->normalizeMonth($month);
        $revision = $this->revisionFor($this->periodMonth);
        $stale = $config && $revision
            && (! $config->effective_date || $config->effective_date->toDateString() < $revision['effective_from']);

        $this->rule = $config ? [
            'config_id' => (int) $config->id,
            'version' => (int) $config->version,
            'label' => $config->ruleLabel(),
            'effective_date' => $config->effective_date?->toDateString(),
            'has_config' => true,
            'config_stale' => false,
        ] : null;

        if ($stale) {
            // Cấu hình đã duyệt có hiệu lực TRƯỚC bộ tiêu chí mới: không dùng trọng số cũ cho kỳ mới.
            // Tính theo bộ tiêu chí đã xác nhận của kỳ; phần trả tiền vẫn cần duyệt phiên bản cấu hình mới.
            $this->configCriteria = $this->standardCriteria($this->periodMonth);
            $this->rule['config_stale'] = true;
            $this->rule['label'] = 'Bộ tiêu chí hiệu lực '.Carbon::parse($revision['effective_from'])->format('d/m/Y')
                .' (tài liệu đã xác nhận). Cấu hình đã duyệt '.$config->ruleLabel()
                .' chưa cập nhật bộ này — cần duyệt phiên bản cấu hình mới trước khi tính lương.';

            return $this;
        }

        $rows = $config ? (array) ($config->criteria_config ?? []) : [];
        if ($rows === []) {
            $this->configCriteria = null;

            return $this;
        }

        $standard = collect($this->standardCriteria($this->periodMonth))->keyBy('code');
        $this->configCriteria = collect($rows)->map(function (array $c) use ($standard): array {
            $base = (array) ($standard->get($c['code'] ?? '') ?? []);
            $active = ! empty($c['is_calculated']) && ($c['status'] ?? '') === 'applied' && (float) ($c['weight'] ?? 0) > 0;

            return array_merge($base, [
                'no' => (int) ($c['no'] ?? ($base['no'] ?? 0)),
                'code' => (string) ($c['code'] ?? ''),
                'name' => (string) ($c['name'] ?? ($base['name'] ?? '')),
                'weight' => $active ? (float) $c['weight'] : null,
                'target' => (string) (($c['threshold'] ?? '') !== '' ? $c['threshold'] : ($base['target'] ?? '')),
                'measure' => (string) (($c['formula'] ?? '') !== '' ? $c['formula'] : ($base['measure'] ?? '')),
                'pending_confirmation' => ! $active,
                'pending_reason' => $active ? null : ($c['pending_reason'] ?? ($base['pending_reason'] ?? 'Tiêu chí chưa được áp dụng tính điểm trong cấu hình KPI.')),
            ]);
        })->sortBy('no')->values()->all();

        return $this;
    }

    public function criteria(): array
    {
        return $this->configCriteria ?? $this->standardCriteria($this->periodMonth);
    }

    /** Đặt kỳ đánh giá khi không có cấu hình (chọn bộ tiêu chí chuẩn đúng kỳ). */
    public function forMonth(?string $month): static
    {
        $this->periodMonth = $this->normalizeMonth($month);

        return $this;
    }

    /** Bản sửa đổi mới nhất có hiệu lực tại tháng (Y-m); null = bộ gốc. */
    public function revisionFor(string $month): ?array
    {
        $found = null;
        foreach ((array) config('technical_kpi_standard.revisions', []) as $revision) {
            if ((string) ($revision['effective_from'] ?? '') <= $month.'-01'
                && ($found === null || $revision['effective_from'] > $found['effective_from'])) {
                $found = $revision;
            }
        }

        return $found;
    }

    /** Bộ tiêu chí chuẩn của tháng: bộ gốc + các bản sửa đổi có hiệu lực tới tháng đó (theo thứ tự ngày). */
    public function standardCriteria(?string $month = null): array
    {
        $month = $this->normalizeMonth($month ?? $this->periodMonth);
        $criteria = collect((array) config('technical_kpi_standard.criteria', []))->keyBy('code')->all();
        $revisions = collect((array) config('technical_kpi_standard.revisions', []))
            ->filter(fn ($r) => (string) ($r['effective_from'] ?? '') <= $month.'-01')
            ->sortBy('effective_from');

        foreach ($revisions as $revision) {
            foreach ((array) ($revision['weights'] ?? []) as $code => $weight) {
                if (isset($criteria[$code])) {
                    $criteria[$code]['weight'] = $weight === null ? null : (float) $weight;
                    $criteria[$code]['pending_confirmation'] = $weight === null;
                }
            }
            foreach ((array) ($revision['overrides'] ?? []) as $code => $override) {
                if (isset($criteria[$code])) {
                    $criteria[$code] = array_merge($criteria[$code], (array) $override);
                }
            }
        }

        return collect($criteria)->sortBy('no')->values()->all();
    }

    private function normalizeMonth(?string $month): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month) ? (string) $month : now()->format('Y-m');
    }

    /** Tổng trọng số các tiêu chí có trọng số (đúng ô C9 của file chuẩn). */
    public function weightTotal(): float
    {
        return (float) collect($this->criteria())->sum(fn ($c) => (float) ($c['weight'] ?? 0));
    }

    public function bonusTiers(): array
    {
        return (array) config('technical_kpi_standard.bonus_tiers', []);
    }

    /** Quy đổi tổng KPI sang mức thưởng theo ô B12:B15. null => chưa đủ dữ liệu. */
    public function tierFor(?float $total): ?array
    {
        if ($total === null) {
            return null;
        }

        foreach ($this->bonusTiers() as $tier) {
            if ($total + 1e-9 >= (float) $tier['min']) {
                return $tier;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Kỳ đánh giá
    |--------------------------------------------------------------------------
    */

    /**
     * Chuẩn hoá kỳ đánh giá. Trả về [type, value, months[], label].
     * month: value = 'Y-m'; quarter: value = 'Y-Qn'.
     */
    public function resolvePeriod(string $type, ?string $month, ?string $quarter): array
    {
        if ($type === 'quarter') {
            if (! preg_match('/^(\d{4})-Q([1-4])$/', (string) $quarter, $m)) {
                $now = now();
                $m = [null, $now->year, (int) ceil($now->month / 3)];
            }
            $year = (int) $m[1];
            $q = (int) $m[2];
            $months = [];
            for ($i = 0; $i < 3; $i++) {
                $months[] = sprintf('%04d-%02d', $year, ($q - 1) * 3 + 1 + $i);
            }

            return ['quarter', sprintf('%04d-Q%d', $year, $q), $months, 'Quý '.$q.'/'.$year];
        }

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $month)) {
            $month = now()->format('Y-m');
        }
        [$y, $mm] = explode('-', $month);

        return ['month', $month, [$month], 'Tháng '.ltrim($mm, '0').'/'.$y];
    }

    /** N tháng liên tiếp kết thúc tại $endMonth (dùng cho biểu đồ xu hướng). */
    public function trailingMonths(string $endMonth, int $count = 6): array
    {
        $end = Carbon::createFromFormat('Y-m-d', $endMonth.'-01');
        $months = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $months[] = $end->copy()->subMonthsNoOverflow($i)->format('Y-m');
        }

        return $months;
    }

    /*
    |--------------------------------------------------------------------------
    | Thu thập dữ liệu nguồn (chỉ đọc)
    |--------------------------------------------------------------------------
    */

    /**
     * Gom dữ liệu CRM của một kỹ sư trong các tháng: công trình liên quan,
     * minh chứng đã duyệt và phiếu bảo hành (tham khảo cho tiêu chí 6).
     */
    public function collect(int $userId, array $months, ?int $siteId = null): array
    {
        // Thứ tự ưu tiên nguồn (khóa nghiệp vụ = công trình `sites.id`; dự án module cũ không liên kết
        // công trình thì khóa = project_test_projects.id):
        //   Tiến độ:   quy trình /du-an (sites + bước workflow) → module Công trình cũ (project_test_*).
        //   Minh chứng: minh chứng duyệt tay → quy trình /du-an → module Công trình cũ.
        // Chống trùng trên TOÀN kỳ (cả quý), không chỉ trong từng tháng: cùng công trình nghiệm thu ở
        // nguồn này tháng 1 và nguồn kia tháng 2 vẫn chỉ được tính một lần.
        $projectsByMonth = [];
        $siteKeys = [];
        foreach ($months as $month) {
            $projectsByMonth[$month] = $this->links->projectsForUserMonth($userId, $month);
            foreach ($projectsByMonth[$month] as $p) {
                if ((int) ($p['site_id'] ?? 0) > 0) {
                    $siteKeys[(int) $p['site_id']] = true;
                }
            }
        }

        $projects = collect();
        foreach ($months as $month) {
            $duAn = $this->links->duAnProjectsForUserMonth($userId, $month)
                ->reject(fn ($p) => (int) ($p['site_id'] ?? 0) > 0 && isset($siteKeys[(int) $p['site_id']]));
            $monthProjects = $projectsByMonth[$month]->concat($duAn)
                ->map(fn ($p) => $p + ['month' => $month, 'user_id' => $userId]);
            if ($siteId) {
                $monthProjects = $monthProjects->where('site_id', $siteId)->values();
            }
            $projects = $projects->concat($monthProjects);
        }

        $ids = $projects->pluck('site_id')->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $manualEvidence = collect();
        if ($ids->isNotEmpty() && SchemaCache::hasTable('technical_kpi_project_evidence')) {
            $manualEvidence = DB::table('technical_kpi_project_evidence')
                ->where('user_id', $userId)
                ->whereIn('payroll_month', $months)
                ->whereIn('site_id', $ids->all())
                ->whereNotNull('approved_at')
                ->orderBy('payroll_month')
                ->get()
                // Một công trình chỉ có một minh chứng tính điểm trong kỳ (bản của tháng sớm nhất).
                ->unique(fn ($e) => (int) $e->site_id)
                ->values();
        }

        $takenSites = $manualEvidence->pluck('site_id')->map(fn ($id) => (int) $id)->flip()->all();
        $workflowMetrics = collect();
        foreach ($months as $month) {
            $monthMetrics = $this->links->workflowAcceptanceMetrics($userId, $month)
                ->reject(fn ($e) => isset($takenSites[(int) $e->site_id]))
                ->when($siteId, fn ($c) => $c->filter(fn ($e) => $e->site_id === $siteId));
            foreach ($monthMetrics as $e) {
                $takenSites[(int) $e->site_id] = true;
            }
            $workflowMetrics = $workflowMetrics->concat($monthMetrics);
        }

        $duAnMetrics = collect();
        $takenProjects = [];
        foreach ($months as $month) {
            $monthMetrics = $this->links->duAnAcceptanceMetrics($userId, $month)
                ->reject(fn ($e) => ($e->site_id > 0 && isset($takenSites[(int) $e->site_id])) || isset($takenProjects[(int) $e->project_id]))
                ->when($siteId, fn ($c) => $c->filter(fn ($e) => $e->site_id === $siteId));
            foreach ($monthMetrics as $e) {
                $takenProjects[(int) $e->project_id] = true;
            }
            $duAnMetrics = $duAnMetrics->concat($monthMetrics);
        }
        $evidence = $manualEvidence->concat($workflowMetrics)->concat($duAnMetrics)->values();

        return [
            'projects' => $projects->values(),
            'evidence' => $evidence->values(),
            'warranty' => $this->warrantyClaims($userId, $months, $siteId),
            'manual' => $this->manualScores($userId, $months),
            'adjustments' => $this->adjustments($userId, $months),
            'assigned_work' => app(AssignedWorkKpiService::class)->collect($userId, $months, $siteId),
        ];
    }

    /** Số liệu Kế hoạch / Thực tế Trưởng phòng nhập theo tháng. */
    public function manualScores(int $userId, array $months): Collection
    {
        if (! SchemaCache::hasTable('technical_kpi_monthly_scores')) {
            return collect();
        }

        return DB::table('technical_kpi_monthly_scores')
            ->where('user_id', $userId)
            ->whereIn('payroll_month', $months)
            ->get();
    }

    /** Điểm cộng/trừ (kèm lý do) trong các tháng. */
    public function adjustments(int $userId, array $months): Collection
    {
        if (! SchemaCache::hasTable('technical_kpi_adjustments')) {
            return collect();
        }

        return DB::table('technical_kpi_adjustments')
            ->where('user_id', $userId)
            ->whereIn('payroll_month', $months)
            ->orderBy('id')
            ->get();
    }

    /**
     * Phiếu bảo hành (lịch bảo trì/bảo hành loại bảo hành) mà kỹ sư là người phụ trách chính, thuộc kỳ theo
     * thời điểm khách báo. Phiếu chưa ghi mốc khách báo được đưa vào theo ngày dự kiến để báo thiếu dữ liệu.
     * null = chưa có nguồn (bảng/cột chưa migrate).
     */
    private function warrantyClaims(int $userId, array $months, ?int $siteId): ?array
    {
        $table = 'solar_maintenance_schedules';
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'first_responded_at')) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m-d', reset($months).'-01')->startOfMonth();
        $end = Carbon::createFromFormat('Y-m-d', end($months).'-01')->endOfMonth();
        $types = (array) config('technical_kpi_standard.warranty.types', []);

        $rows = DB::table($table.' as m')
            ->where(function ($q) {
                $companyId = \App\Support\EgoCompanyLock::id();
                $q->where('m.company_id', $companyId)->orWhere(function ($q) use ($companyId) {
                    $q->where(fn ($q) => $q->whereNull('m.company_id')->orWhere('m.company_id', 0))
                        ->whereExists(fn ($site) => $site->select(DB::raw(1))->from('sites as warranty_site')
                            ->whereColumn('warranty_site.id', 'm.site_id')->where('warranty_site.company_id', $companyId));
                });
            })
            ->whereIn('m.type', $types)
            ->whereNull('m.deleted_at')
            ->where(function ($q) use ($userId) {
                // Trách nhiệm theo ID: trưởng nhóm được phân công (assigned_to = người đầu danh sách).
                $q->where('m.assigned_to', $userId)
                    ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('solar_maintenance_assignees as a')
                        ->whereColumn('a.maintenance_schedule_id', 'm.id')
                        ->where('a.user_id', $userId)
                        ->where(fn ($l) => $l->where('a.is_leader', 1)->orWhere('a.role', 'leader')));
            })
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('m.customer_reported_at', [$start, $end])
                    ->orWhere(fn ($n) => $n->whereNull('m.customer_reported_at')
                        ->whereBetween('m.scheduled_date', [$start->toDateString(), $end->toDateString()]));
            })
            ->when($siteId, fn ($q) => $q->where('m.site_id', $siteId))
            ->orderBy('m.id')
            ->get(['m.id', 'm.schedule_code', 'm.site_id', 'm.site_name', 'm.type', 'm.status', 'm.scheduled_date',
                'm.customer_reported_at', 'm.first_responded_at', 'm.execution_finished_at', 'm.cancelled_at',
                'm.complaint_status', 'm.complaint_note', 'm.complaint_checked_by', 'm.complaint_checked_at']);

        return [
            'total' => $rows->count(),
            'resolved' => $rows->whereNotNull('execution_finished_at')->count(),
            'rows' => $rows,
        ];
    }

    /**
     * Tiêu chí 6 (từ 10/2026): phiếu đạt khi phản hồi ≤ 5 phút VÀ hoàn tất thực hiện ≤ 24 giờ tính từ lúc
     * khách báo VÀ đã được kiểm tra không có khiếu nại. Thời gian tính liên tục, không trừ thời gian chờ (D12).
     * Thiếu mốc/chưa kiểm tra khiếu nại = thiếu dữ liệu (không đạt, không N/A). Đã vi phạm mốc thì "Chưa đạt"
     * dù chưa kiểm tra khiếu nại. Không có phiếu trong kỳ = chưa đủ dữ liệu (chưa có quy tắc "không phát sinh").
     */
    private function scoreWarranty(array $criterion, ?array $warranty): array
    {
        $r = $this->base($criterion);
        $r['source'] = 'Phiếu bảo hành (Bảo trì/Bảo hành): giờ khách báo, phản hồi đầu, hoàn tất thực hiện, kiểm tra khiếu nại';
        $responseMin = (int) ($criterion['response_minutes'] ?? 5);
        $resolutionHours = (int) ($criterion['resolution_hours'] ?? 24);

        if ($warranty === null) {
            $r['missing'][] = 'Chưa có nguồn mốc SLA bảo hành (chưa chạy migration phiếu bảo hành).';

            return $r;
        }

        $passed = 0;
        $counted = 0;
        $missing = 0;
        foreach ($warranty['rows'] as $w) {
            [$state, $result] = $this->warrantyState($w, $responseMin, $resolutionHours);
            if ($state === 'pass' || $state === 'fail') {
                $counted++;
                $passed += $state === 'pass' ? 1 : 0;
            } elseif ($state === 'missing') {
                $missing++;
            }
            $fmt = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y H:i') : '—';
            $r['evidence'][] = [
                'project' => (string) ($w->schedule_code ?: 'Phiếu #'.$w->id).($w->site_name ? ' · '.$w->site_name : ''),
                'schedule_id' => (int) $w->id,
                'site_id' => $w->site_id ? (int) $w->site_id : null,
                'month' => $w->customer_reported_at ? Carbon::parse($w->customer_reported_at)->format('Y-m') : '',
                'user' => null,
                'detail' => 'Khách báo: '.$fmt($w->customer_reported_at).' · Phản hồi: '.$fmt($w->first_responded_at)
                    .' · Hoàn tất: '.$fmt($w->execution_finished_at),
                'result' => $result,
                'state' => $state,
            ];
        }

        if ($warranty['rows']->isEmpty()) {
            $r['missing'][] = 'Không có phiếu bảo hành nào trong kỳ — chưa có quy tắc/xác nhận "không phát sinh" (D05), nên chưa chấm được tiêu chí.';
        }
        if ($missing > 0) {
            $r['missing'][] = $missing.' phiếu bảo hành thiếu mốc hoặc chưa được kiểm tra khiếu nại — chưa tính.';
            $r['passed'] = $passed;
            $r['total'] = $counted;
            $r['status'] = self::STATUS_NO_DATA;

            return $r;
        }

        return $this->finishRatio($r, $passed, $counted);
    }

    /** @return array{0:string,1:string} [state, mô tả] */
    public function warrantyState(object $w, int $responseMin, int $resolutionHours): array
    {
        if (! empty($w->cancelled_at) || (string) $w->status === 'cancelled') {
            return ['excluded', 'Phiếu đã hủy — không tính'];
        }
        if (empty($w->customer_reported_at)) {
            return ['missing', 'Thiếu thời điểm khách báo'];
        }

        $reported = Carbon::parse($w->customer_reported_at);
        $failures = [];
        $missing = [];

        if (empty($w->first_responded_at)) {
            $missing[] = 'thiếu mốc phản hồi đầu';
        } else {
            $seconds = $reported->diffInSeconds(Carbon::parse($w->first_responded_at), false);
            if ($seconds < 0) {
                $missing[] = 'mốc phản hồi trước lúc khách báo cần xác minh lại';
            } elseif ($seconds > $responseMin * 60) {
                $failures[] = 'phản hồi sau '.(int) ceil($seconds / 60).' phút (> '.$responseMin.' phút)';
            }
        }

        $deadline = $reported->copy()->addHours($resolutionHours);
        if (empty($w->execution_finished_at)) {
            if ((string) $w->status === 'completed') {
                $missing[] = 'thiếu mốc hoàn tất thực hiện';
            } elseif (now()->gt($deadline)) {
                $failures[] = 'quá '.$resolutionHours.' giờ chưa hoàn tất';
            } else {
                $missing[] = 'đang xử lý (hạn '.$deadline->format('d/m H:i').')';
            }
        } elseif (Carbon::parse($w->execution_finished_at)->lt($reported)
            || (! empty($w->first_responded_at) && Carbon::parse($w->execution_finished_at)->lt(Carbon::parse($w->first_responded_at)))) {
            $missing[] = 'mốc hoàn tất trước tiếp nhận/phản hồi cần xác minh lại';
        } elseif (Carbon::parse($w->execution_finished_at)->gt($deadline)) {
            $failures[] = 'hoàn tất sau '.round($reported->diffInMinutes(Carbon::parse($w->execution_finished_at)) / 60, 1).' giờ (> '.$resolutionHours.' giờ)';
        }

        if ((string) $w->complaint_status === 'has_complaint') {
            $failures[] = 'có khiếu nại'.(trim((string) $w->complaint_note) !== '' ? ': '.trim((string) $w->complaint_note) : '');
        } elseif ((string) $w->complaint_status !== 'none' || empty($w->complaint_checked_by) || empty($w->complaint_checked_at)) {
            $missing[] = 'chưa kiểm tra khiếu nại';
        }

        if ($failures !== []) {
            return ['fail', 'Không đạt: '.implode('; ', $failures)];
        }
        if ($missing !== []) {
            return ['missing', 'Chưa đủ dữ liệu: '.implode('; ', $missing)];
        }

        return ['pass', 'Đạt: phản hồi ≤ '.$responseMin.' phút, hoàn tất ≤ '.$resolutionHours.' giờ, không khiếu nại'];
    }

    /*
    |--------------------------------------------------------------------------
    | Tính điểm (hàm thuần — không truy vấn DB)
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Collection  $projects  mỗi phần tử: site_id, code, name, month, deadline_iso,
     *                                completed_iso, on_time, timeline_excluded, (user_name)
     * @param  Collection  $evidence  dòng technical_kpi_project_evidence đã duyệt
     */
    public function score(Collection $projects, Collection $evidence, ?array $warranty = null, ?Collection $manual = null, ?Collection $adjustments = null, ?Collection $assignedWork = null): array
    {
        $projectIndex = $projects->keyBy(fn ($p) => $this->key($p['site_id'] ?? 0, $p['month'] ?? '', $p['user_id'] ?? 0));
        $manualByCode = ($manual ?? collect())
            ->filter(fn ($m) => $m->plan_value !== null && $m->actual_value !== null && (float) $m->plan_value > 0)
            ->groupBy('criterion_code');
        $results = [];

        foreach ($this->criteria() as $criterion) {
            if ($criterion['weight'] === null) {
                $results[] = $this->pending($criterion, $warranty);

                continue;
            }

            // KS01 mới lấy việc giao thực tế: không cho số nhập tay che mất việc chưa nộp.
            if ($criterion['code'] === 'timeline' && ($criterion['source_mode'] ?? '') === 'assigned_reports') {
                $results[] = $this->scoreAssignedWork($criterion, $assignedWork);

                continue;
            }

            // Số liệu Trưởng phòng nhập theo tháng được ưu tiên hơn số tự động ở các tiêu chí còn lại.
            if ($manualByCode->has($criterion['code'])) {
                $results[] = $this->scoreManual($criterion, $manualByCode->get($criterion['code']));

                continue;
            }

            $results[] = match ($criterion['code']) {
                'timeline' => $this->scoreTimeline($criterion, $projects),
                'quality' => $this->scoreEvidence($criterion, $evidence, $projectIndex, 'quality_first_pass',
                    fn ($v) => (bool) $v, fn ($v) => $v ? 'Nghiệm thu đạt lần đầu' : 'Phải sửa / nghiệm thu lại hoặc chủ nhà phàn nàn'),
                'survey' => $this->scoreSurvey($criterion, $evidence, $projectIndex),
                'hse' => $this->scoreEvidence($criterion, $evidence, $projectIndex, 'hse_pass',
                    fn ($v) => (bool) $v, fn ($v) => $v ? 'Đạt checklist HSE' : 'Không đạt HSE'),
                'evn_app' => $this->scoreEvnApp($criterion, $evidence, $projectIndex),
                'warranty' => $this->scoreWarranty($criterion, $warranty),
                default => $this->noAutoSource($criterion),
            };
        }

        $results = array_map(fn (array $r) => $this->withPointShares($r), $results);

        return $this->summarize($results, $projects, $evidence, $adjustments ?? collect());
    }

    /**
     * Gắn phần điểm của từng dòng nguồn (thang 100) khi tiêu chí đã có điểm theo tỷ lệ đạt/tổng và mỗi dòng
     * đạt/không đạt là một phần bằng nhau của mẫu số. Không gắn khi tiêu chí chưa tính hoặc nhập tay theo tháng.
     */
    private function withPointShares(array $r): array
    {
        if ($r['score'] === null || (float) ($r['total'] ?? 0) <= 0 || $r['source'] === self::SOURCE_MANUAL_LABEL) {
            return $r;
        }
        $counted = collect($r['evidence'])->filter(fn ($e) => in_array($e['state'] ?? '', ['pass', 'fail'], true));
        if ($counted->count() !== (int) $r['total']) {
            return $r;
        }
        foreach ($r['evidence'] as &$row) {
            if (in_array($row['state'] ?? '', ['pass', 'fail'], true)) {
                $ratio = $row['point_share_ratio'] ?? 1 / (float) $r['total'];
                $row['point_share_ratio'] = $ratio;
                $row['points_possible'] = round($ratio * (float) $r['weight'] * 100, 4);
                $row['points_earned'] = $row['state'] === 'pass' ? $row['points_possible'] : 0.0;
            }
        }
        unset($row);

        return $r;
    }

    /**
     * Tiêu chí từ số liệu nhập tay: tỷ lệ = Σ Thực tế / Σ Kế hoạch (cộng dồn các tháng trong kỳ).
     * KHÔNG chặn trần — thực tế vượt kế hoạch thì tiêu chí được vượt 100%.
     */
    private function scoreManual(array $criterion, Collection $rows): array
    {
        $r = $this->base($criterion);
        $r['source'] = self::SOURCE_MANUAL_LABEL;
        $plan = (float) $rows->sum(fn ($m) => (float) $m->plan_value);
        $actual = (float) $rows->sum(fn ($m) => (float) $m->actual_value);

        foreach ($rows as $m) {
            $r['evidence'][] = [
                'project' => 'Tháng '.$m->payroll_month,
                'site_id' => null,
                'month' => (string) $m->payroll_month,
                'user' => null,
                'detail' => trim((string) ($m->note ?? '')) ?: 'Kế hoạch '.$this->num($m->plan_value).' · Thực tế '.$this->num($m->actual_value),
                'result' => 'Đạt '.$this->num((float) $m->actual_value).'/'.$this->num((float) $m->plan_value),
                'state' => (float) $m->actual_value >= (float) $m->plan_value ? 'pass' : 'fail',
            ];
        }

        $r['passed'] = $actual;
        $r['total'] = $plan;
        $r['rate'] = $plan > 0 ? $actual / $plan : null;
        $r['score'] = $r['rate'] !== null ? $r['rate'] * (float) $r['weight'] : null;
        $r['status'] = $r['rate'] === null ? self::STATUS_NO_DATA : ($r['rate'] >= 1 - 1e-9 ? self::STATUS_PASS : self::STATUS_FAIL);

        return $r;
    }

    /** Tiêu chí có trọng số nhưng không có nguồn tự động: chỉ chấm được khi Trưởng phòng nhập số liệu. */
    private function noAutoSource(array $criterion): array
    {
        $r = $this->base($criterion);
        $r['source'] = self::SOURCE_MANUAL_LABEL;
        $r['missing'][] = 'Chưa nhập Kế hoạch / Thực tế cho tiêu chí này trong kỳ.';

        return $r;
    }

    private function num(float|string|null $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    }

    private function key($siteId, $month, $userId): string
    {
        return (int) $siteId.'|'.$month.'|'.(int) $userId;
    }

    private function base(array $criterion): array
    {
        return [
            'no' => $criterion['no'],
            'code' => $criterion['code'],
            'name' => $criterion['name'],
            'weight' => $criterion['weight'],
            'target' => $criterion['target'],
            'measure' => $criterion['measure'],
            'source_mode' => $criterion['source_mode'] ?? null,
            'rate' => null,
            'score' => null,
            'passed' => null,
            'total' => null,
            'status' => self::STATUS_NO_DATA,
            'source' => '',
            'missing' => [],
            'evidence' => [],
        ];
    }

    /** KS01 từ 10/2026: mỗi việc được giao có cùng phần điểm khi đến hạn 24 giờ. */
    private function scoreAssignedWork(array $criterion, ?Collection $items): array
    {
        $r = $this->base($criterion);
        $r['source'] = 'Kế hoạch & Giao việc · giờ giao và lần nộp báo cáo đầu tiên';
        if ($items === null) {
            $r['missing'][] = 'Chưa có nguồn công việc được giao / báo cáo ngày.';

            return $r;
        }

        $measured = app(AssignedWorkKpiService::class)->evaluate($items, (int) ($criterion['submission_hours'] ?? 24));
        $r['evidence'] = $measured['evidence'];
        foreach ($r['evidence'] as &$row) {
            if (isset($row['point_share_ratio'])) {
                $points = $row['point_share_ratio'] * (float) $r['weight'] * 100;
                $row['result'] .= ' ('.rtrim(rtrim(number_format($row['state'] === 'pass' ? $points : 0, 2, ',', ''), '0'), ',').'/'.rtrim(rtrim(number_format($points, 2, ',', ''), '0'), ',').' điểm)';
            }
        }
        unset($row);
        if ($measured['missing'] > 0) {
            $r['missing'][] = $measured['missing'].' việc thiếu mốc hợp lệ — chưa chốt điểm Tiến độ.';

            return $r;
        }
        if ($measured['counted'] === 0) {
            $r['missing'][] = 'Chưa có việc trưởng phòng giao đã đến hạn 24 giờ trong kỳ.';
        }

        return $this->finishRatio($r, $measured['passed'], $measured['counted']);
    }

    private function scoreTimeline(array $criterion, Collection $projects): array
    {
        $r = $this->base($criterion);
        $r['source'] = 'Phân công thi công/nghiệm thu, hạn do người giao việc đặt, ngày duyệt nghiệm thu bàn giao';
        $countOverdue = $this->openOverduePolicy() === 'count_as_late';
        $eligible = 0;
        $onTime = 0;
        $openOverdue = 0;
        $missingCompletion = 0;

        foreach ($this->dedupeTimeline($projects) as $p) {
            $month = (string) ($p['month'] ?? '');
            $completedInMonth = ! empty($p['completed_iso']) && str_starts_with((string) $p['completed_iso'], $month);
            $detail = 'Hạn: '.($p['deadline'] ?? '—').' · Bàn giao: '.($p['completed'] ?? '—');
            if (! empty($p['completion_source'])) {
                $detail .= ' ('.$p['completion_source'].')';
            }
            if (! empty($p['deadline_note'])) {
                $detail .= ' · '.$p['deadline_note'];
            }
            $row = [
                'project' => ($p['code'] ?? '').' · '.($p['name'] ?? ''),
                'site_id' => $p['site_id'] ?? null,
                'month' => $month,
                'user' => $p['user_name'] ?? null,
                'detail' => $detail,
            ];

            if (! empty($p['timeline_excluded'])) {
                $row['result'] = 'Loại trừ khỏi tiến độ (đã duyệt)';
                $row['state'] = 'excluded';
            } elseif (array_key_exists('timeline_responsible', $p) && ! $p['timeline_responsible']) {
                $row['result'] = 'Không phụ trách thi công/bàn giao công trình này — không tính tiến độ';
                $row['state'] = 'info';
            } elseif ($completedInMonth) {
                if (empty($p['has_deadline'])) {
                    $row['result'] = 'Thiếu hạn hoàn thành — không đánh giá được';
                    $row['state'] = 'missing';
                } else {
                    $eligible++;
                    $ok = ($p['on_time'] ?? null) === true;
                    $onTime += $ok ? 1 : 0;
                    $row['result'] = $ok ? 'Đúng tiến độ' : 'Trễ tiến độ';
                    $row['state'] = $ok ? 'pass' : 'fail';
                }
            } elseif (! empty($p['completion_missing'])) {
                $missingCompletion++;
                $row['result'] = (string) $p['completion_missing'];
                $row['state'] = 'missing';
            } elseif (! empty($p['overdue_open'])) {
                $openOverdue++;
                if ($countOverdue) {
                    $eligible++;
                    $row['result'] = 'Quá hạn chưa bàn giao — tính là trễ hạn';
                    $row['state'] = 'fail';
                } else {
                    $row['result'] = 'Quá hạn chưa bàn giao (tại cuối kỳ)';
                    $row['state'] = 'overdue';
                }
            } else {
                $row['result'] = empty($p['completed_iso']) ? 'Chưa bàn giao trong kỳ' : 'Bàn giao ngoài kỳ';
                $row['state'] = 'info';
            }
            $r['evidence'][] = $row;
        }

        $r['open_overdue'] = $openOverdue;
        if ($projects->isEmpty()) {
            $r['missing'][] = 'Chưa có công trình nào gán cho kỹ sư trong kỳ (phân công workflow / kỹ sư phụ trách).';
        } elseif ($eligible === 0) {
            $r['missing'][] = 'Không có hệ thống bàn giao trong kỳ có đủ hạn hoàn thành và ngày duyệt nghiệm thu.';
        }
        if ($openOverdue > 0 && ! $countOverdue) {
            $r['missing'][] = $openOverdue.' hệ thống quá hạn chưa bàn giao: đang liệt kê, chưa đưa vào mẫu số (công thức hiện hành chỉ tính hệ thống đã bàn giao; chờ xác nhận quy tắc kỳ cho việc quá hạn còn mở).';
        }
        if ($missingCompletion > 0) {
            $r['missing'][] = $missingCompletion.' công trình đã qua thi công nhưng chưa có ngày bàn giao được xác nhận — cần bổ sung ở bước Nghiệm thu và bàn giao.';
        }

        return $this->finishRatio($r, $onTime, $eligible);
    }

    /**
     * Một dòng cho mỗi cặp kỹ sư–công trình trong kỳ (quý có thể lặp lại cùng công trình ở nhiều tháng).
     * Ưu tiên dòng có bàn giao trong chính tháng của nó; nếu không có thì giữ dòng của tháng gần nhất.
     */
    private function dedupeTimeline(Collection $projects): Collection
    {
        return $projects
            ->groupBy(fn ($p) => (int) ($p['user_id'] ?? 0).'|'.$this->businessKey($p))
            ->map(function (Collection $rows) {
                $completed = $rows->first(fn ($p) => ! empty($p['completed_iso'])
                    && str_starts_with((string) $p['completed_iso'], (string) ($p['month'] ?? '')));

                return $completed ?? $rows->sortBy(fn ($p) => (string) ($p['month'] ?? ''))->last();
            })
            ->values();
    }

    /** Khóa nghiệp vụ công trình: sites.id nếu có liên kết, ngược lại id dự án module cũ. */
    private function businessKey(array $p): string
    {
        return (int) ($p['site_id'] ?? 0) > 0 ? 's'.(int) $p['site_id'] : 'p'.(int) ($p['project_id'] ?? 0);
    }

    /** Tiêu chí 2, 3, 4: tỷ lệ công trình đạt chuẩn theo minh chứng đã duyệt. */
    private function scoreEvidence(array $criterion, Collection $evidence, Collection $projectIndex, string $field, callable $isPass, callable $describe): array
    {
        $r = $this->base($criterion);
        $r['source'] = match ($field) {
            'quality_first_pass' => 'Minh chứng KPI công trình · nghiệm thu lần đầu (đã duyệt)',
            'material_waste_percent' => 'Minh chứng KPI công trình · % sai lệch/hao hụt vật tư (đã duyệt)',
            default => 'Minh chứng KPI công trình · checklist HSE (đã duyệt)',
        };

        $rows = $evidence->filter(fn ($e) => $e->{$field} !== null);
        // Prefix trường lý do thiếu / dấu xác nhận do nguồn công trình gắn kèm (quality_missing, hse_confirmed...).
        $prefix = $field === 'hse_pass' ? 'hse' : 'quality';
        $missing = collect();
        if ($field === 'quality_first_pass') {
            $missing = $evidence->filter(fn ($e) => $e->{$field} === null || empty($e->complaint_recorded));
            $rows = $rows->filter(fn ($e) => ! empty($e->complaint_recorded));
        } elseif ($field === 'hse_pass') {
            // Chỉ nguồn nghiệm thu công trình mới gắn hse_missing: công trình đã nghiệm thu mà thiếu kết quả HSE
            // đã kiểm tra là THIẾU, không được bỏ ra khỏi mẫu số (minh chứng khác không gắn trường này).
            $missing = $evidence->filter(fn ($e) => $e->{$field} === null && ! empty($e->hse_missing ?? null));
        }
        foreach ($missing as $e) {
            $reason = (string) ($e->{$prefix.'_missing'} ?? '') ?: 'Chưa xác nhận chủ nhà có phàn nàn hay không';
            $r['evidence'][] = $this->evidenceRow($e, $projectIndex, $reason, 'missing', $e->{$prefix.'_status'} ?? null);
        }
        $passed = 0;
        foreach ($rows as $e) {
            $ok = $isPass($e->{$field});
            $passed += $ok ? 1 : 0;
            $confirmed = (string) ($e->{$prefix.'_confirmed'} ?? '');
            $r['evidence'][] = $this->evidenceRow($e, $projectIndex, $describe($e->{$field}).($confirmed !== '' ? ' · '.$confirmed : ''), $ok ? 'pass' : 'fail', $e->{$prefix.'_status'} ?? null);
        }

        // Có công trình nghiệm thu nhưng chưa xác nhận: lý do là "chưa xác nhận", không phải "chưa có dự án".
        if ($rows->isEmpty() && $missing->isEmpty()) {
            $r['missing'][] = $field === 'hse_pass'
                ? 'Chưa có đánh giá HSE trong kỳ (checklist HSE khi nghiệm thu) — hoặc Trưởng phòng nhập ở trang Nhập số liệu KPI tháng.'
                : 'Chưa có dự án được nghiệm thu trong kỳ (hoặc chưa có phiếu vật tư) — có thể nhập tay ở trang Nhập số liệu KPI tháng.';
        }
        if ($missing->isNotEmpty()) {
            $r['missing'][] = $field === 'hse_pass'
                ? 'Có công trình đã nghiệm thu chưa có kết quả HSE được Trưởng phòng xác nhận (đủ 5 mục).'
                : 'Có biên bản nghiệm thu chưa ghi nhận chủ nhà có phàn nàn hay không.';
            // Không bỏ công trình chưa được đánh giá ra khỏi mẫu số rồi cho những công trình còn lại đạt 100%.
            $r['passed'] = $passed;
            $r['total'] = $rows->count();
            $r['status'] = self::STATUS_NO_DATA;

            return $r;
        }

        return $this->finishRatio($r, $passed, $rows->count());
    }

    /**
     * Tiêu chí 3: công trình đạt khi hồ sơ khảo sát đầy đủ (hiện trạng, số đo, bản vẽ mô phỏng, dự toán)
     * VÀ sai lệch vật tư dưới ngưỡng. Minh chứng cũ không có thông tin hồ sơ khảo sát thì chỉ xét vật tư.
     */
    private function scoreSurvey(array $criterion, Collection $evidence, Collection $projectIndex): array
    {
        $r = $this->base($criterion);
        $r['source'] = 'Hồ sơ khảo sát và phiếu vật tư dự án · % sai lệch vật tư';
        $threshold = (float) ($criterion['threshold_percent'] ?? 3);
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 6, ',', '.'), '0'), ',');

        $rows = $evidence->filter(fn ($e) => $e->material_waste_percent !== null || isset($e->survey_complete));
        $passed = 0;
        foreach ($rows as $e) {
            $problems = [];
            if (! empty($e->material_manual)) {
                $missing = [];
                if ($e->material_waste_percent === null) {
                    $missing[] = (string) ($e->material_missing ?? '') ?: 'Chưa xác nhận kết quả vật tư';
                }
                if (isset($e->survey_complete) && ! $e->survey_complete) {
                    $missing[] = 'Khảo sát thiếu '.implode(', ', (array) ($e->survey_missing ?? []));
                }
                if ($missing !== []) {
                    $r['missing'][] = ((string) ($e->project_label ?? '') ?: 'Công trình #'.$e->site_id).': '.implode(' · ', $missing);
                    $r['evidence'][] = $this->evidenceRow($e, $projectIndex, implode(' · ', $missing), 'missing', $e->material_status ?? null);
                    continue;
                }
            }
            if (isset($e->survey_complete) && ! $e->survey_complete) {
                $problems[] = 'Khảo sát thiếu '.implode(', ', (array) ($e->survey_missing ?? []));
            }
            if ($e->material_waste_percent !== null && (float) $e->material_waste_percent >= $threshold) {
                $problems[] = 'Sai lệch vật tư '.$fmt($e->material_waste_percent).'%';
            }
            $ok = $problems === [];
            $passed += $ok ? 1 : 0;
            $result = $ok
                ? 'Khảo sát đủ hồ sơ'.($e->material_waste_percent !== null ? ' · lệch vật tư '.$fmt($e->material_waste_percent).'%' : '')
                : implode(' · ', $problems);
            $r['evidence'][] = $this->evidenceRow($e, $projectIndex, $result, $ok ? 'pass' : 'fail', $e->material_status ?? null);
        }

        if ($rows->isEmpty()) {
            $r['missing'][] = 'Chưa có dự án được nghiệm thu trong kỳ (hoặc chưa có phiếu vật tư) — có thể nhập tay ở trang Nhập số liệu KPI tháng.';
        }

        if ($rows->isNotEmpty() && $r['missing'] !== []) {
            $r['status'] = self::STATUS_NO_DATA;

            return $r;
        }

        return $this->finishRatio($r, $passed, $rows->count());
    }

    /** Tiêu chí 5: công trình có yêu cầu EVN/App đã hoàn tất. Không yêu cầu => N/A. */
    private function scoreEvnApp(array $criterion, Collection $evidence, Collection $projectIndex): array
    {
        $r = $this->base($criterion);
        $r['source'] = 'Minh chứng KPI công trình · trạng thái EVN và cài App (đã duyệt)';

        $rows = $evidence->filter(fn ($e) => $e->evn_app_required !== null);
        $required = $rows->filter(fn ($e) => (bool) $e->evn_app_required);
        $passed = 0;

        foreach ($rows as $e) {
            if (! $e->evn_app_required) {
                $r['evidence'][] = $this->evidenceRow($e, $projectIndex, 'Không yêu cầu EVN/App (N/A)', 'excluded');

                continue;
            }
            if ($e->evn_app_completed === null) {
                $r['evidence'][] = $this->evidenceRow($e, $projectIndex, (string) ($e->evn_missing ?? '') ?: 'Có yêu cầu, chưa xác nhận trạng thái', 'missing', $e->evn_status ?? null);

                continue;
            }
            $ok = (bool) $e->evn_app_completed;
            $passed += $ok ? 1 : 0;
            $confirmed = (string) ($e->evn_confirmed ?? '');
            $r['evidence'][] = $this->evidenceRow($e, $projectIndex,
                ($ok ? 'Đã hoàn tất EVN (nếu cần đấu nối) & bàn giao App' : 'Chưa hoàn tất EVN/App').($confirmed !== '' ? ' · '.$confirmed : ''), $ok ? 'pass' : 'fail',
                $e->evn_status ?? null);
        }

        $total = $required->filter(fn ($e) => $e->evn_app_completed !== null)->count();
        $unconfirmed = $required->filter(fn ($e) => $e->evn_app_completed === null)->count();

        if ($rows->isEmpty()) {
            $r['missing'][] = 'Chưa có dự án được nghiệm thu trong kỳ — có thể nhập tay ở trang Nhập số liệu KPI tháng.';
        } elseif ($required->isEmpty()) {
            // Mọi công trình trong kỳ đều không yêu cầu: không có mẫu số để chấm.
            $r['missing'][] = 'Không có công trình nào yêu cầu EVN/App trong kỳ (N/A) — không đủ căn cứ chấm tiêu chí.';
        } elseif ($unconfirmed > 0) {
            // Không bỏ công trình chưa xác nhận ra khỏi mẫu số rồi chấm phần còn lại.
            $r['missing'][] = 'Có '.$unconfirmed.' công trình yêu cầu EVN/App chưa có kết quả được xác nhận.';
            $r['passed'] = $passed;
            $r['total'] = $total;
            $r['status'] = self::STATUS_NO_DATA;

            return $r;
        }

        return $this->finishRatio($r, $passed, $total);
    }

    /** Tiêu chí 6: chờ Ban lãnh đạo xác nhận — chỉ hiển thị nguồn sẵn có. */
    private function pending(array $criterion, ?array $warranty): array
    {
        $r = $this->base($criterion);
        $r['status'] = self::STATUS_PENDING;
        $r['source'] = $warranty === null
            ? 'Chưa có nguồn phiếu bảo hành phù hợp'
            : 'Phiếu bảo hành (khách báo → hoàn tất thực hiện) — tham khảo, chưa tính điểm';
        $r['missing'][] = (string) ($criterion['pending_reason'] ?? 'Chờ Ban lãnh đạo xác nhận.');

        foreach (($warranty['rows'] ?? []) as $w) {
            $hours = ($w->customer_reported_at && $w->execution_finished_at)
                ? round(Carbon::parse($w->customer_reported_at)->diffInMinutes(Carbon::parse($w->execution_finished_at)) / 60, 1)
                : null;
            $r['evidence'][] = [
                'project' => (string) ($w->schedule_code ?: ('Phiếu #'.$w->id)),
                'schedule_id' => (int) $w->id,
                'site_id' => $w->site_id ? (int) $w->site_id : null,
                'month' => $w->customer_reported_at ? Carbon::parse($w->customer_reported_at)->format('Y-m') : '',
                'user' => null,
                'detail' => 'Khách báo: '.($w->customer_reported_at ? Carbon::parse($w->customer_reported_at)->format('d/m/Y H:i') : '—')
                    .' · Hoàn tất: '.($w->execution_finished_at ? Carbon::parse($w->execution_finished_at)->format('d/m/Y H:i') : '—'),
                'result' => $hours === null ? 'Chưa đủ mốc' : 'Thời gian xử lý: '.$hours.' giờ',
                'state' => 'info',
            ];
        }
        $r['warranty'] = $warranty ? ['total' => $warranty['total'], 'resolved' => $warranty['resolved']] : null;

        return $r;
    }

    /** @param  string|null  $sourceStatus  trạng thái nhập/xác nhận của nguồn (vd. luồng Nghiệm thu), nếu nguồn có */
    private function evidenceRow(object $e, Collection $projectIndex, string $result, string $state, ?string $sourceStatus = null): array
    {
        $project = $projectIndex->get($this->key($e->site_id, $e->payroll_month, $e->user_id));

        return [
            'project' => $project ? (($project['code'] ?? '').' · '.($project['name'] ?? '')) : ((string) ($e->project_label ?? '') ?: ('Công trình #'.$e->site_id)),
            'site_id' => (int) $e->site_id,
            'month' => (string) $e->payroll_month,
            'user' => $project['user_name'] ?? null,
            'detail' => trim((string) ($e->note ?? '')) ?: 'Duyệt lúc '.Carbon::parse($e->approved_at)->format('d/m/Y H:i'),
            'result' => $result,
            'state' => $state,
            'source_status' => $sourceStatus,
            'material_manual' => (bool) ($e->material_manual ?? false),
        ];
    }

    private function finishRatio(array $r, int $passed, int $total): array
    {
        if ($total <= 0) {
            $r['status'] = self::STATUS_NO_DATA;

            return $r;
        }

        $r['passed'] = $passed;
        $r['total'] = $total;
        $r['rate'] = $passed / $total;
        $r['score'] = $r['rate'] * (float) $r['weight'];
        $r['status'] = $r['rate'] >= 1 - 1e-9 ? self::STATUS_PASS : self::STATUS_FAIL;

        return $r;
    }

    private function summarize(array $results, Collection $projects, Collection $evidence, ?Collection $adjustments = null): array
    {
        $weighted = collect($results)->filter(fn ($r) => $r['weight'] !== null);
        $withData = $weighted->filter(fn ($r) => $r['rate'] !== null);
        $complete = $weighted->isNotEmpty() && $withData->count() === $weighted->count();
        // Điểm cộng/trừ của Trưởng phòng: 1 điểm = 1% KPI. Chỉ áp khi tổng KPI đã tính được.
        $adjustments ??= collect();
        $adjustmentPoints = (float) $adjustments->sum(fn ($a) => (float) $a->points);
        $total = $complete ? (float) $withData->sum('score') + $adjustmentPoints / 100 : null;

        $dataStatus = $withData->isEmpty()
            ? self::DATA_NONE
            : ($complete ? self::DATA_FULL : self::DATA_PARTIAL);

        // Tỷ lệ hoàn thành công trình: công trình đã bàn giao trong kỳ / công trình liên quan trong kỳ
        // (đếm theo cặp kỹ sư–công trình, một công trình hoạt động nhiều tháng chỉ tính một lần).
        $completion = null;
        if ($projects->isNotEmpty()) {
            $bySite = $projects->groupBy(fn ($p) => (int) ($p['user_id'] ?? 0).'|'.(int) ($p['site_id'] ?? 0));
            $done = $bySite->filter(fn ($rows) => $rows->contains(
                fn ($p) => ! empty($p['completed_iso']) && str_starts_with((string) $p['completed_iso'], (string) ($p['month'] ?? ''))
            ))->count();
            $completion = ['done' => $done, 'total' => $bySite->count(), 'rate' => $done / $bySite->count()];
        }

        return [
            'criteria' => $results,
            'total' => $total,
            'partial_score' => $withData->isNotEmpty() ? (float) $withData->sum('score') : null,
            'partial_weight' => (float) $withData->sum('weight'),
            'tier' => $this->tierFor($total),
            'data_status' => $dataStatus,
            'with_data' => $withData->count(),
            'missing_data' => $weighted->count() - $withData->count(),
            'pending' => collect($results)->where('status', self::STATUS_PENDING)->count(),
            'weighted_count' => $weighted->count(),
            'completion' => $completion,
            'penalty_points' => (float) $evidence->sum(fn ($e) => (float) ($e->penalty_points ?? 0)),
            'project_count' => $projects->map(fn ($p) => ($p['project_id'] ?? null) ? 'p'.$p['project_id'] : 's'.($p['site_id'] ?? 0))->unique()->count(),
            'adjustment_points' => $adjustmentPoints,
            'adjustments' => $adjustments->map(fn ($a) => [
                'month' => (string) $a->payroll_month,
                'points' => (float) $a->points,
                'reason' => (string) $a->reason,
            ])->values()->all(),
            'missing_summary' => $weighted->filter(fn ($r) => $r['rate'] === null)->pluck('name')->values()->all(),
            // Bộ quy tắc của kỳ: kết quả luôn đi kèm phiên bản đã dùng để tính.
            'rule' => $this->rule(),
            'open_overdue_policy' => $this->openOverduePolicy(),
        ];
    }
}
