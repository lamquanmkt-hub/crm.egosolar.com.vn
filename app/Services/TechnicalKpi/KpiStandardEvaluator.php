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

    public function __construct(private readonly ProjectKpiLinkService $links) {}

    /*
    |--------------------------------------------------------------------------
    | Chuẩn KPI
    |--------------------------------------------------------------------------
    */

    /**
     * Dùng tiêu chí + trọng số của phiên bản cấu hình đã duyệt (trang Cài đặt KPI) thay cho file chuẩn.
     * Tiêu chí không tick "tính điểm" hoặc chưa ở trạng thái áp dụng => không trọng số (chờ xác nhận).
     */
    public function useConfig(?\App\Models\TechnicalKpiConfig $config): static
    {
        $rows = $config ? (array) ($config->criteria_config ?? []) : [];
        if ($rows === []) {
            $this->configCriteria = null;

            return $this;
        }

        $standard = collect((array) config('technical_kpi_standard.criteria', []))->keyBy('code');
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
        return $this->configCriteria ?? (array) config('technical_kpi_standard.criteria', []);
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
        $projects = collect();
        $evidence = collect();

        foreach ($months as $month) {
            $legacy = $this->links->projectsForUserMonth($userId, $month);
            $legacySiteIds = $legacy->pluck('site_id')->map(fn ($id) => (int) $id)->filter()->all();
            // Module Dự án mới (/du-an): bỏ các dự án đã có ở nguồn cũ (trùng công trình liên kết) để không đếm 2 lần.
            $duAn = $this->links->duAnProjectsForUserMonth($userId, $month)
                ->reject(fn ($p) => (int) ($p['site_id'] ?? 0) > 0 && in_array((int) $p['site_id'], $legacySiteIds, true));

            $monthProjects = $legacy->concat($duAn)
                ->map(fn ($p) => $p + ['month' => $month, 'user_id' => $userId]);
            if ($siteId) {
                $monthProjects = $monthProjects->where('site_id', $siteId)->values();
            }
            $projects = $projects->concat($monthProjects);

            $ids = $monthProjects->pluck('site_id')->map(fn ($id) => (int) $id)->filter()->unique()->values();
            $legacyEvidence = collect();
            if ($ids->isNotEmpty() && SchemaCache::hasTable('technical_kpi_project_evidence')) {
                $legacyEvidence = DB::table('technical_kpi_project_evidence')
                    ->where('user_id', $userId)
                    ->where('payroll_month', $month)
                    ->whereIn('site_id', $ids->all())
                    ->whereNotNull('approved_at')
                    ->get();
                $evidence = $evidence->concat($legacyEvidence);
            }

            // Tự động từ quy trình dự án /du-an: công trình được duyệt nghiệm thu trong tháng
            // (chất lượng, khảo sát & vật tư, HSE, EVN/App). Ưu tiên: minh chứng nhập tay → quy trình /du-an
            // → dữ liệu module Công trình cũ (project_test_*); mỗi công trình chỉ đếm một lần.
            $takenSites = $legacyEvidence->pluck('site_id')->map(fn ($id) => (int) $id)->all();
            $workflowMetrics = $this->links->workflowAcceptanceMetrics($userId, $month)
                ->reject(fn ($e) => in_array($e->site_id, $takenSites, true))
                ->when($siteId, fn ($c) => $c->filter(fn ($e) => $e->site_id === $siteId));
            $takenSites = array_merge($takenSites, $workflowMetrics->pluck('site_id')->all());
            $duAnMetrics = $this->links->duAnAcceptanceMetrics($userId, $month)
                ->reject(fn ($e) => $e->site_id > 0 && in_array($e->site_id, $takenSites, true))
                ->when($siteId, fn ($c) => $c->filter(fn ($e) => $e->site_id === $siteId));
            $evidence = $evidence->concat($workflowMetrics)->concat($duAnMetrics);
        }

        return [
            'projects' => $projects->values(),
            'evidence' => $evidence->values(),
            'warranty' => $this->warrantyClaims($userId, $months, $siteId),
            'manual' => $this->manualScores($userId, $months),
            'adjustments' => $this->adjustments($userId, $months),
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

    /** Phiếu bảo hành giao cho kỹ sư trong kỳ — chỉ để hiển thị, KHÔNG tính điểm. */
    private function warrantyClaims(int $userId, array $months, ?int $siteId): ?array
    {
        $table = 'crm_serial_warranty_claims';
        foreach (['assigned_to', 'received_at', 'resolved_at'] as $col) {
            if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, $col)) {
                return null;
            }
        }

        $start = Carbon::createFromFormat('Y-m-d', reset($months).'-01')->startOfMonth();
        $end = Carbon::createFromFormat('Y-m-d', end($months).'-01')->endOfMonth();

        $rows = DB::table($table)
            ->where('assigned_to', $userId)
            ->whereBetween('received_at', [$start, $end])
            ->when(SchemaCache::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when($siteId && SchemaCache::hasColumn($table, 'site_id'), fn ($q) => $q->where('site_id', $siteId))
            ->select('id', 'claim_code', 'status', 'received_at', 'resolved_at')
            ->orderBy('received_at')
            ->get();

        return [
            'total' => $rows->count(),
            'resolved' => $rows->whereNotNull('resolved_at')->count(),
            'rows' => $rows,
        ];
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
    public function score(Collection $projects, Collection $evidence, ?array $warranty = null, ?Collection $manual = null, ?Collection $adjustments = null): array
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

            // Số liệu Trưởng phòng nhập theo tháng được ưu tiên hơn số tự động.
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
                default => $this->noAutoSource($criterion),
            };
        }

        return $this->summarize($results, $projects, $evidence, $adjustments ?? collect());
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

    /** Tiêu chí 1: (Số hệ thống hoàn thành đúng tiến độ / Tổng số hệ thống bàn giao). */
    private function scoreTimeline(array $criterion, Collection $projects): array
    {
        $r = $this->base($criterion);
        $r['source'] = 'Hạn hoàn thành (workflow / công trình) và ngày nghiệm thu, bàn giao';
        $eligible = 0;
        $onTime = 0;

        foreach ($projects as $p) {
            $month = (string) ($p['month'] ?? '');
            $completedInMonth = ! empty($p['completed_iso']) && str_starts_with((string) $p['completed_iso'], $month);
            $row = [
                'project' => ($p['code'] ?? '').' · '.($p['name'] ?? ''),
                'site_id' => $p['site_id'] ?? null,
                'month' => $month,
                'user' => $p['user_name'] ?? null,
                'detail' => 'Hạn: '.($p['deadline'] ?? '—').' · Hoàn thành: '.($p['completed'] ?? '—'),
            ];

            if (! empty($p['timeline_excluded'])) {
                $row['result'] = 'Loại trừ khỏi tiến độ (đã duyệt)';
                $row['state'] = 'excluded';
            } elseif (! $completedInMonth) {
                $row['result'] = empty($p['completed_iso']) ? 'Chưa bàn giao trong kỳ' : 'Bàn giao ngoài kỳ';
                $row['state'] = 'info';
            } elseif (empty($p['has_deadline'])) {
                $row['result'] = 'Thiếu ngày kế hoạch — không đánh giá được';
                $row['state'] = 'missing';
            } else {
                $eligible++;
                $ok = ($p['on_time'] ?? null) === true;
                $onTime += $ok ? 1 : 0;
                $row['result'] = $ok ? 'Đúng tiến độ' : 'Trễ tiến độ';
                $row['state'] = $ok ? 'pass' : 'fail';
            }
            $r['evidence'][] = $row;
        }

        if ($projects->isEmpty()) {
            $r['missing'][] = 'Chưa có công trình nào gán cho kỹ sư trong kỳ (phân công workflow / kỹ sư phụ trách).';
        } elseif ($eligible === 0) {
            $r['missing'][] = 'Không có hệ thống bàn giao trong kỳ có đủ ngày kế hoạch và ngày hoàn thành.';
        }

        return $this->finishRatio($r, $onTime, $eligible);
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
        $passed = 0;
        foreach ($rows as $e) {
            $ok = $isPass($e->{$field});
            $passed += $ok ? 1 : 0;
            $r['evidence'][] = $this->evidenceRow($e, $projectIndex, $describe($e->{$field}), $ok ? 'pass' : 'fail');
        }

        if ($rows->isEmpty()) {
            $r['missing'][] = $field === 'hse_pass'
                ? 'Chưa có đánh giá HSE trong kỳ (checklist HSE khi nghiệm thu) — hoặc Trưởng phòng nhập ở trang Nhập số liệu KPI tháng.'
                : 'Chưa có dự án được nghiệm thu trong kỳ (hoặc chưa có phiếu vật tư) — có thể nhập tay ở trang Nhập số liệu KPI tháng.';
        }
        if ($field === 'quality_first_pass' && $rows->isNotEmpty() && $rows->contains(fn ($e) => empty($e->complaint_recorded))) {
            $r['missing'][] = 'Có biên bản nghiệm thu chưa ghi nhận chủ nhà có phàn nàn hay không.';
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
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');

        $rows = $evidence->filter(fn ($e) => $e->material_waste_percent !== null || isset($e->survey_complete));
        $passed = 0;
        foreach ($rows as $e) {
            $problems = [];
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
            $r['evidence'][] = $this->evidenceRow($e, $projectIndex, $result, $ok ? 'pass' : 'fail');
        }

        if ($rows->isEmpty()) {
            $r['missing'][] = 'Chưa có dự án được nghiệm thu trong kỳ (hoặc chưa có phiếu vật tư) — có thể nhập tay ở trang Nhập số liệu KPI tháng.';
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
                $r['evidence'][] = $this->evidenceRow($e, $projectIndex, 'Có yêu cầu, chưa xác nhận trạng thái', 'missing');

                continue;
            }
            $ok = (bool) $e->evn_app_completed;
            $passed += $ok ? 1 : 0;
            $r['evidence'][] = $this->evidenceRow($e, $projectIndex, $ok ? 'Đã đấu nối thử & cài App' : 'Chưa hoàn tất EVN/App', $ok ? 'pass' : 'fail');
        }

        $total = $required->filter(fn ($e) => $e->evn_app_completed !== null)->count();

        if ($rows->isEmpty()) {
            $r['missing'][] = 'Chưa có dự án được nghiệm thu trong kỳ — có thể nhập tay ở trang Nhập số liệu KPI tháng.';
        } elseif ($required->isEmpty()) {
            // Mọi công trình trong kỳ đều không yêu cầu: không có mẫu số để chấm.
            $r['missing'][] = 'Không có công trình nào yêu cầu EVN/App trong kỳ (N/A) — không đủ căn cứ chấm tiêu chí.';
        } elseif ($total === 0) {
            $r['missing'][] = 'Công trình có yêu cầu EVN/App nhưng chưa xác nhận trạng thái hoàn tất.';
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
            : 'Phiếu bảo hành serial (ngày tiếp nhận → ngày xử lý) — tham khảo, chưa tính điểm';
        $r['missing'][] = (string) ($criterion['pending_reason'] ?? 'Chờ Ban lãnh đạo xác nhận.');

        foreach (($warranty['rows'] ?? []) as $w) {
            $hours = ($w->received_at && $w->resolved_at)
                ? Carbon::parse($w->received_at)->diffInHours(Carbon::parse($w->resolved_at))
                : null;
            $r['evidence'][] = [
                'project' => (string) ($w->claim_code ?: ('Phiếu #'.$w->id)),
                'site_id' => null,
                'month' => $w->received_at ? Carbon::parse($w->received_at)->format('Y-m') : '',
                'user' => null,
                'detail' => 'Tiếp nhận: '.($w->received_at ? Carbon::parse($w->received_at)->format('d/m/Y H:i') : '—')
                    .' · Xử lý xong: '.($w->resolved_at ? Carbon::parse($w->resolved_at)->format('d/m/Y H:i') : '—'),
                'result' => $hours === null ? 'Chưa xử lý xong' : 'Thời gian xử lý: '.$hours.' giờ',
                'state' => 'info',
            ];
        }
        $r['warranty'] = $warranty ? ['total' => $warranty['total'], 'resolved' => $warranty['resolved']] : null;

        return $r;
    }

    private function evidenceRow(object $e, Collection $projectIndex, string $result, string $state): array
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
        ];
    }
}
