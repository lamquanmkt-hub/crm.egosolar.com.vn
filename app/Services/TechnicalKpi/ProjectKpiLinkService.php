<?php

namespace App\Services\TechnicalKpi;

use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectKpiLinkService
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_TIMELINE = 'project_timeline';

    public const SOURCE_QUALITY = 'project_quality';

    public const SOURCE_MATERIAL = 'project_material_waste';

    public const SOURCE_HSE = 'project_hse';

    public const SOURCE_EVN_APP = 'project_evn_app';

    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_MANUAL => 'Nhập tay',
            self::SOURCE_TIMELINE => 'Công trình · Tiến độ / deadline',
            self::SOURCE_QUALITY => 'Công trình · Nghiệm thu / chất lượng',
            self::SOURCE_MATERIAL => 'Công trình · Hao hụt vật tư',
            self::SOURCE_HSE => 'Công trình · HSE / vệ sinh',
            self::SOURCE_EVN_APP => 'Công trình · EVN / App',
        ];
    }

    private function table(string $table): bool
    {
        return SchemaCache::hasTable($table);
    }

    private function column(string $table, string $column): bool
    {
        return $this->table($table) && SchemaCache::hasColumn($table, $column);
    }

    private function monthBounds(string $month): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }

        return [$start, $start->copy()->endOfMonth()];
    }

    /**
     * Danh sách công trình liên quan đến kỹ sư trong kỳ KPI.
     * Ưu tiên assignment workflow; fallback lead_engineer_id để dữ liệu cũ vẫn được tính.
     */
    public function projectsForUserMonth(int $userId, string $month): Collection
    {
        if ($userId <= 0 || ! $this->table('sites')) {
            return collect();
        }

        [$start, $end] = $this->monthBounds($month);
        $projects = collect();

        if ($this->table('project_workflow_assignments') && $this->table('project_workflow_steps')) {
            $rows = DB::table('project_workflow_assignments as a')
                ->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
                ->join('sites as s', 's.id', '=', 'w.site_id')
                ->where('a.user_id', $userId)
                ->when($this->column('project_workflow_assignments', 'is_active'), fn ($q) => $q->where('a.is_active', 1))
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('w.updated_at', [$start, $end]);
                    if ($this->column('project_workflow_steps', 'approved_at')) {
                        $q->orWhereBetween('w.approved_at', [$start, $end]);
                    }
                    if ($this->column('project_workflow_steps', 'submitted_at')) {
                        $q->orWhereBetween('w.submitted_at', [$start, $end]);
                    }
                    if ($this->column('project_workflow_assignments', 'submitted_at')) {
                        $q->orWhereBetween('a.submitted_at', [$start, $end]);
                    }
                    if ($this->column('sites', 'completed_at')) {
                        $q->orWhereBetween('s.completed_at', [$start->toDateString(), $end->toDateString()]);
                    }
                    if ($this->column('sites', 'handover_at')) {
                        $q->orWhereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]);
                    }
                    // Việc đã đến hạn trong/ trước kỳ mà chưa được duyệt tới cuối kỳ: vẫn phải xuất hiện
                    // (quá hạn còn mở), kể cả khi tháng này không có thao tác nào trên bước.
                    $q->orWhere(function ($open) use ($end) {
                        $open->whereNotNull('w.due_at')
                            ->where('w.due_at', '<=', $end)
                            ->where(fn ($a) => $a->whereNull('w.approved_at')->orWhere('w.approved_at', '>', $end));
                    });
                    // Mốc cấp công trình: nghiệm thu được duyệt trong kỳ, hoặc nghiệm thu/thi công đã đến hạn
                    // mà chưa được duyệt tới cuối kỳ (kỹ sư có thể chỉ được giao bước Thi công).
                    $q->orWhereExists(fn ($sub) => $sub->select(DB::raw(1))
                        ->from('project_workflow_steps as acc')
                        ->whereColumn('acc.site_id', 'w.site_id')
                        ->where('acc.step_code', 'acceptance')
                        ->whereBetween('acc.approved_at', [$start, $end]));
                    $q->orWhereExists(fn ($sub) => $sub->select(DB::raw(1))
                        ->from('project_workflow_steps as due')
                        ->whereColumn('due.site_id', 'w.site_id')
                        ->whereIn('due.step_code', ['construction', 'acceptance'])
                        ->whereNotNull('due.due_at')
                        ->where('due.due_at', '<=', $end)
                        ->where(fn ($a) => $a->whereNull('due.approved_at')->orWhere('due.approved_at', '>', $end)));
                })
                ->select([
                    's.id as site_id', 's.project_code', 's.name', 's.status as site_status',
                    's.project_phase', 's.progress_percent', 's.target_completion_at', 's.completed_at', 's.handover_at',
                    $this->column('sites', 'lead_engineer_id') ? 's.lead_engineer_id' : DB::raw('NULL as lead_engineer_id'),
                    'w.id as workflow_step_id', 'w.step_code', 'w.status as step_status', 'w.due_at',
                    'w.recommitted_due_at', 'w.approved_at', 'w.submitted_at', 'w.returned_reason',
                    'a.assignment_role', 'a.status as assignment_status',
                ])
                ->orderBy('s.id')
                ->orderBy('w.sequence')
                ->get();

            foreach ($rows->groupBy('site_id') as $siteId => $siteRows) {
                $projects->put((int) $siteId, $this->summarizeProjectRows($siteRows, $userId, $month));
            }
        }

        // Dữ liệu cũ chỉ có kỹ sư phụ trách chính.
        if ($this->column('sites', 'lead_engineer_id')) {
            $leadRows = DB::table('sites as s')
                ->where('s.lead_engineer_id', $userId)
                ->where(function ($q) use ($start, $end) {
                    $hasAny = false;
                    if ($this->column('sites', 'completed_at')) {
                        $q->whereBetween('s.completed_at', [$start->toDateString(), $end->toDateString()]);
                        $hasAny = true;
                    }
                    if ($this->column('sites', 'handover_at')) {
                        $hasAny ? $q->orWhereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]) : $q->whereBetween('s.handover_at', [$start->toDateString(), $end->toDateString()]);
                        $hasAny = true;
                    }
                    if (! $hasAny) {
                        $q->whereBetween('s.updated_at', [$start, $end]);
                    }
                    // Hạn hợp đồng đã tới trong/trước kỳ mà chưa hoàn thành tới cuối kỳ: giữ trong danh sách.
                    if ($this->column('sites', 'target_completion_at')) {
                        $q->orWhere(function ($open) use ($end) {
                            $open->whereNotNull('s.target_completion_at')
                                ->where('s.target_completion_at', '<=', $end->toDateString());
                            foreach (['completed_at', 'handover_at'] as $col) {
                                if ($this->column('sites', $col)) {
                                    $open->where(fn ($c) => $c->whereNull('s.'.$col)->orWhere('s.'.$col, '>', $end->toDateString()));
                                }
                            }
                        });
                    }
                })
                ->when($this->column('sites', 'deleted_at'), fn ($q) => $q->whereNull('s.deleted_at'))
                ->select('s.*')
                ->get();

            foreach ($leadRows as $site) {
                if ($projects->has((int) $site->id)) {
                    continue;
                }
                $projects->put((int) $site->id, $this->summarizeLegacySite($site, $userId, $month));
            }
        }

        return $projects->values()->sortByDesc(fn ($row) => $row['activity_at'] ?? '')->values();
    }

    /**
     * Dự án ở module Công trình cũ (bảng project_test_*, đã chuyển sang /du-an) mà kỹ sư tham gia trong tháng:
     * có tên trong phân công (project_test_assignments) hoặc là kỹ thuật viên chính (lead_technician_id),
     * và có ngày lắp đặt xong (installed_at) hoặc hạn hoàn thành (target_completion_at) rơi vào tháng.
     * Trả về cùng khuôn với projectsForUserMonth() để dùng chung cho tiêu chí Tiến độ.
     */
    public function duAnProjectsForUserMonth(int $userId, string $month): Collection
    {
        if ($userId <= 0 || ! $this->table('project_test_projects')) {
            return collect();
        }

        [$start, $end] = $this->monthBounds($month);
        $hasAssignments = $this->table('project_test_assignments');

        $rows = DB::table('project_test_projects as p')
            ->whereNull('p.deleted_at')
            ->when($this->column('project_test_projects', 'company_id'), fn ($q) => $q->where('p.company_id', \App\Support\EgoCompanyLock::id()))
            ->where(function ($q) use ($userId, $hasAssignments) {
                $q->where('p.lead_technician_id', $userId);
                if ($hasAssignments) {
                    $q->orWhereExists(fn ($sub) => $sub->select(DB::raw(1))
                        ->from('project_test_assignments as a')
                        ->whereColumn('a.project_id', 'p.id')
                        ->where('a.user_id', $userId));
                }
            })
            ->orderBy('p.id')
            ->get(['p.id', 'p.legacy_site_id', 'p.code', 'p.name', 'p.status', 'p.target_completion_at', 'p.installed_at', 'p.lead_technician_id', 'p.updated_at'])
            // Ngày hoàn thành lắp đặt: installed_at (dự án cũ) → lúc chuyển "Chờ nghiệm thu" (nhật ký thi công
            // báo hoàn tất) → ngày nghiệm thu. Quy trình /du-an hiện không ghi installed_at cho dự án mới.
            ->map(function (object $p): object {
                $p->completed_at = $p->installed_at ?: $this->duAnCompletedAt((int) $p->id);

                return $p;
            })
            ->filter(fn (object $p) => ($p->completed_at && Carbon::parse($p->completed_at)->betweenIncluded($start, $end))
                || ($p->target_completion_at && Carbon::parse($p->target_completion_at)->betweenIncluded($start, $end))
                // Quá hạn còn mở: hạn trước kỳ, chưa hoàn thành tới cuối kỳ.
                || ($p->target_completion_at && Carbon::parse($p->target_completion_at)->lt($start)
                    && (! $p->completed_at || Carbon::parse($p->completed_at)->gt($end))
                    && ! in_array((string) $p->status, ['cancelled', 'canceled'], true)))
            ->values();

        return $rows->map(function (object $p) use ($userId, $end): array {
            $deadline = $p->target_completion_at;
            $completed = $p->completed_at;
            $onTime = $deadline && $completed ? Carbon::parse($completed)->startOfDay()->lte(Carbon::parse($deadline)->endOfDay()) : null;
            $overdueOpen = $deadline && Carbon::parse($deadline)->endOfDay()->lt($end->copy()->min(now()))
                && (! $completed || Carbon::parse($completed)->gt($end));
            // Đã qua thi công (chờ nghiệm thu/bảo hành/hoàn thành) nhưng không có mốc hoàn thành: báo thiếu.
            $finishedStatus = in_array((string) $p->status, ['acceptance_pending', 'warranty_active', 'completed'], true);

            return [
                'site_id' => (int) ($p->legacy_site_id ?? 0),
                'project_id' => (int) $p->id,
                'code' => (string) ($p->code ?: ('DA-'.$p->id)),
                'name' => (string) ($p->name ?? ('Dự án #'.$p->id)),
                'role' => (int) $p->lead_technician_id === $userId ? 'lead' : 'member',
                'steps' => [],
                'deadline' => $deadline ? Carbon::parse($deadline)->format('d/m/Y') : null,
                'deadline_iso' => $deadline ? Carbon::parse($deadline)->toDateTimeString() : null,
                'completed' => $completed ? Carbon::parse($completed)->format('d/m/Y') : null,
                'completed_iso' => $completed ? Carbon::parse($completed)->toDateTimeString() : null,
                'has_deadline' => (bool) $deadline,
                'on_time' => $onTime,
                'timeline_excluded' => false,
                'timeline_responsible' => true,
                'overdue_open' => (bool) $overdueOpen && ! ($finishedStatus && ! $completed),
                'completion_source' => $completed ? ($p->installed_at ? 'ngày lắp đặt xong (module cũ)' : 'lịch sử chuyển Chờ nghiệm thu') : null,
                'completion_missing' => ! $completed && $finishedStatus ? 'Dự án module cũ đã qua thi công nhưng không có ngày lắp đặt xong — cần bổ sung.' : null,
                'deadline_note' => $deadline ? 'theo hạn hoàn thành dự án (module cũ)' : null,
                'issues' => $deadline && $completed && ! $onTime ? ['Trễ tiến độ'] : [],
                'activity_at' => (string) ($completed ?? $p->updated_at ?? ''),
                'evidence_status' => 'du_an',
                'source' => 'du_an',
            ];
        })->values();
    }

    /** Lần đầu dự án chuyển sang "Chờ nghiệm thu" (hoàn tất thi công); nếu không có thì ngày nghiệm thu. */
    private function duAnCompletedAt(int $projectId): ?string
    {
        if ($this->table('project_test_histories')) {
            $at = DB::table('project_test_histories')
                ->where('project_id', $projectId)
                ->where('to_status', 'acceptance_pending')
                ->where(fn ($q) => $q->whereNull('from_status')->orWhere('from_status', '!=', 'acceptance_pending'))
                ->min('created_at');
            if ($at) {
                return (string) $at;
            }
        }

        if ($this->table('project_test_acceptances')) {
            $accepted = DB::table('project_test_acceptances')->where('project_id', $projectId)->value('accepted_at');
            if ($accepted) {
                return (string) $accepted;
            }
        }

        return null;
    }

    /**
     * Số liệu KPI từ dữ liệu module Công trình cũ (project_test_*) mà kỹ sư tham gia và ĐƯỢC NGHIỆM THU trong tháng.
     * Mỗi dòng có cùng các cột với technical_kpi_project_evidence để dùng chung bộ chấm điểm:
     * - quality_first_pass: null khi luồng cũ chưa có xác nhận phản ánh của chủ nhà.
     * - evn_app_completed: null — module cũ không có kết quả EVN/App được kiểm tra; link/tài khoản App chỉ là
     *   minh chứng hỗ trợ nên tiêu chí báo thiếu (trước đây link App được tính là đạt).
     * - material_waste_percent: |tiêu hao thực tế − bóc tách| ÷ bóc tách × 100 (null nếu không có phiếu vật tư).
     * - hse_pass: KHÔNG có nguồn tự động → null (Trưởng phòng nhập ở trang Nhập số liệu KPI tháng).
     */
    public function duAnAcceptanceMetrics(int $userId, string $month): Collection
    {
        if ($userId <= 0 || ! $this->table('project_test_projects') || ! $this->table('project_test_acceptances')) {
            return collect();
        }

        [$start, $end] = $this->monthBounds($month);
        $hasAssignments = $this->table('project_test_assignments');

        $rows = DB::table('project_test_projects as p')
            ->join('project_test_acceptances as acc', 'acc.project_id', '=', 'p.id')
            ->whereNull('p.deleted_at')
            ->when($this->column('project_test_projects', 'company_id'), fn ($q) => $q->where('p.company_id', \App\Support\EgoCompanyLock::id()))
            ->whereBetween('acc.accepted_at', [$start->toDateString(), $end->toDateString()])
            ->where(function ($q) use ($userId, $hasAssignments) {
                $q->where('p.lead_technician_id', $userId);
                if ($hasAssignments) {
                    $q->orWhereExists(fn ($sub) => $sub->select(DB::raw(1))
                        ->from('project_test_assignments as a')
                        ->whereColumn('a.project_id', 'p.id')
                        ->where('a.user_id', $userId));
                }
            })
            ->orderBy('p.id')
            ->get(['p.id', 'p.legacy_site_id', 'p.code', 'p.name', 'p.monitoring_link as p_link', 'p.monitoring_account as p_account',
                'acc.accepted_at', 'acc.monitoring_link', 'acc.monitoring_account']);

        return $rows->map(function (object $p) use ($userId, $month): object {
            // Link/tài khoản App chỉ là minh chứng hỗ trợ, không phải kết quả EVN/App đã được kiểm tra.
            $hasApp = trim((string) ($p->monitoring_link ?: $p->p_link)) !== '' || trim((string) ($p->monitoring_account ?: $p->p_account)) !== '';
            $waste = $this->materialDeviationPercent((int) $p->id);

            return (object) [
                'site_id' => (int) ($p->legacy_site_id ?? 0),
                'project_id' => (int) $p->id,
                'project_label' => trim(($p->code ?: 'DA-'.$p->id).' · '.$p->name),
                'user_id' => $userId,
                'payroll_month' => $month,
                // Luồng cũ chưa có xác nhận khiếu nại; lịch sử không bị trả lại không chứng minh chất lượng đạt.
                'quality_first_pass' => null,
                'complaint_recorded' => false,
                'quality_missing' => 'Dự án module cũ chưa có xác nhận phản ánh khách hàng',
                'material_waste_percent' => $waste,
                'hse_pass' => null,
                'hse_missing' => 'Dự án module cũ chưa có kết quả HSE đã kiểm tra',
                'evn_app_required' => 1,
                'evn_app_completed' => null,
                'evn_missing' => 'Dự án module cũ chưa có kết quả EVN/App đã kiểm tra'.($hasApp ? ' (có link/tài khoản App — chỉ là minh chứng hỗ trợ)' : ''),
                'timeline_excluded' => 0,
                'penalty_points' => 0,
                'note' => 'Dự án /du-an · nghiệm thu '.Carbon::parse($p->accepted_at)->format('d/m/Y')
                    .($waste !== null ? ' · lệch vật tư '.rtrim(rtrim(number_format($waste, 2, ',', '.'), '0'), ',').'%' : ''),
                'approved_at' => $p->accepted_at,
                'source' => 'du_an',
            ];
        })->values();
    }

    /** Checklist HSE ở bước "Nghiệm thu và bàn giao" (KPI tiêu chí 4): đạt khi tick đủ tất cả. */
    public const ACCEPTANCE_HSE_CHECKS = [
        'kpi_no_incident' => 'Không xảy ra tai nạn, sự cố an toàn',
        'kpi_safety_gear' => 'Đội thi công đeo dây an toàn, đồ bảo hộ đầy đủ',
        'kpi_roof_intact' => 'Không làm hỏng mái, tài sản của chủ nhà',
        'kpi_site_cleaned' => 'Đã dọn vệ sinh công trình 100%',
        'kpi_cable_waste_collected' => 'Đã thu gom rác thải, cáp thừa',
    ];

    /**
     * Số liệu KPI từ quy trình dự án /du-an (khảo sát → … → thi công → nghiệm thu) cho các công trình có
     * bước "Nghiệm thu và bàn giao" được DUYỆT trong tháng, mà kỹ sư được phân công ở bước Khảo sát /
     * Thi công / Nghiệm thu hoặc là kỹ sư phụ trách chính.
     * - quality_first_pass / hse_pass / evn_app_completed: theo acceptanceKpiResult() — chỉ kết quả đã được
     *   Trưởng phòng KT/Admin xác nhận còn hiệu lực; chưa xác nhận = null kèm lý do *_missing.
     * - survey_complete: bước Khảo sát đủ hồ sơ bắt buộc; bước Đề xuất có bản vẽ sơ bộ và bảng khối lượng vật tư.
     * - material_waste_percent: số nhập ở bước nghiệm thu (theo bảng quyết toán vật tư), nếu không nhập thì
     *   Σ vật tư đề xuất BỔ SUNG ÷ Σ vật tư đề xuất BAN ĐẦU × 100.
     */
    public function workflowAcceptanceMetrics(int $userId, string $month): Collection
    {
        if ($userId <= 0 || ! $this->table('sites') || ! $this->table('project_workflow_steps')) {
            return collect();
        }

        [$start, $end] = $this->monthBounds($month);
        $hasAssignments = $this->table('project_workflow_assignments');
        $hasLead = $this->column('sites', 'lead_engineer_id');
        if (! $hasAssignments && ! $hasLead) {
            return collect();
        }

        $rows = DB::table('project_workflow_steps as acc')
            ->join('sites as s', 's.id', '=', 'acc.site_id')
            ->where('acc.step_code', 'acceptance')
            ->where('acc.status', 'approved')
            // Chỉ nghiệm thu có người duyệt; bước tự đánh dấu "approved" khi chuyển dữ liệu cũ không phải nguồn KPI.
            ->whereNotNull('acc.approved_by')
            ->whereBetween('acc.approved_at', [$start, $end])
            ->when($this->column('sites', 'deleted_at'), fn ($q) => $q->whereNull('s.deleted_at'))
            ->when($this->column('sites', 'company_id'), fn ($q) => $q->where('s.company_id', \App\Support\EgoCompanyLock::id()))
            ->where(function ($q) use ($userId, $hasAssignments, $hasLead) {
                if ($hasLead) {
                    $q->where('s.lead_engineer_id', $userId);
                }
                if ($hasAssignments) {
                    $q->orWhereExists(fn ($sub) => $sub->select(DB::raw(1))
                        ->from('project_workflow_assignments as a')
                        ->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
                        ->whereColumn('w.site_id', 's.id')
                        ->whereIn('w.step_code', ['survey', 'construction', 'acceptance'])
                        ->where('a.user_id', $userId));
                }
            })
            ->orderBy('s.id')
            ->get(['s.id as site_id', 's.project_code', 's.name', 'acc.id', 'acc.status', 'acc.approved_at', 'acc.data']);

        return $rows->map(function (object $r) use ($userId, $month): object {
            $siteId = (int) $r->site_id;
            $data = $this->jsonArray($r->data ?? null);
            $steps = DB::table('project_workflow_steps')->where('site_id', $siteId)->get()->keyBy('step_code');
            $kpi = $this->acceptanceKpiResult($r);
            $reworks = $kpi['reworks'];
            $complaint = $kpi['complaint'];

            // Khảo sát & vật tư.
            $surveyMissing = $this->workflowSurveyMissing($siteId, $steps);
            $waste = isset($data['kpi_material_deviation_percent']) && is_numeric($data['kpi_material_deviation_percent'])
                ? round((float) $data['kpi_material_deviation_percent'], 2)
                : $this->workflowMaterialDeviationPercent($siteId);
            $materialService = app(\App\Services\Projects\ManualMaterialKpiService::class);
            $manualMaterial = $materialService->requiresConfirmation($r) ? $materialService->result($r) : null;
            if ($manualMaterial !== null) {
                $waste = $manualMaterial['percent'];
            }

            $notes = ['Dự án /du-an · duyệt nghiệm thu '.Carbon::parse($r->approved_at)->format('d/m/Y')];
            if ($reworks > 0) {
                $notes[] = 'bị trả lại '.$reworks.' lần';
            }
            if ($complaint) {
                $notes[] = 'chủ nhà phàn nàn'.($kpi['complaint_note'] !== '' ? ': '.$kpi['complaint_note'] : '');
            }
            if ($manualMaterial !== null) {
                $notes[] = $manualMaterial['confirmed'];
            } elseif ($waste !== null) {
                $manualWaste = isset($data['kpi_material_deviation_percent']) && is_numeric($data['kpi_material_deviation_percent']);
                $notes[] = 'lệch vật tư '.rtrim(rtrim(number_format($waste, 2, ',', '.'), '0'), ',').'%'
                    .($manualWaste ? ' (nhập tay, chưa đối chiếu kho)' : ' (tự tính từ đề xuất vật tư)');
            }
            if ($surveyMissing !== []) {
                $notes[] = 'khảo sát thiếu: '.implode(', ', $surveyMissing);
            }

            return (object) [
                'site_id' => $siteId,
                'project_id' => null,
                'project_label' => trim(($r->project_code ?: 'CT-'.$siteId).' · '.$r->name),
                'user_id' => $userId,
                'payroll_month' => $month,
                'quality_first_pass' => $kpi['quality_first_pass'],
                'complaint_recorded' => $kpi['complaint_recorded'],
                'quality_missing' => $kpi['quality_missing'],
                'quality_confirmed' => $kpi['quality_confirmed'],
                'material_waste_percent' => $waste,
                'material_status' => $manualMaterial['status'] ?? null,
                'material_missing' => $manualMaterial['missing'] ?? '',
                'material_confirmed' => $manualMaterial['confirmed'] ?? '',
                'material_manual' => $manualMaterial !== null,
                'survey_complete' => $surveyMissing === [],
                'survey_missing' => $surveyMissing,
                'hse_pass' => $kpi['hse_pass'],
                'hse_missing' => $kpi['hse_missing'],
                'hse_confirmed' => $kpi['hse_confirmed'],
                'evn_app_required' => 1,
                'evn_app_completed' => $kpi['evn_app_completed'],
                'evn_missing' => $kpi['evn_missing'],
                'evn_confirmed' => $kpi['evn_confirmed'],
                'quality_status' => $kpi['quality_status'],
                'hse_status' => $kpi['hse_status'],
                'evn_status' => $kpi['evn_status'],
                'timeline_excluded' => 0,
                'penalty_points' => 0,
                'note' => implode(' · ', $notes),
                'approved_at' => $r->approved_at,
                'source' => 'workflow',
            ];
        })->values();
    }

    /**
     * Kết quả KS02 (chất lượng), KS04 (HSE), KS05 (EVN/App) của một bước Nghiệm thu — dùng chung cho bộ chấm KPI
     * và ghi chú tại màn hình Nghiệm thu. 1 = đạt, 0 = không đạt, null = thiếu (kèm lý do *_missing).
     *
     * - Luồng mới (data.kpi_acceptance): chỉ kết quả đã được Trưởng phòng KT/Admin xác nhận và còn hiệu lực
     *   (nội dung/minh chứng/lịch sử trả lại không đổi sau khi xác nhận).
     * - Dữ liệu cũ (kpi_* do form trước ghi): chỉ dùng khi có dấu đánh giá kpi_reviewed_by/at do server ghi; mục
     *   "false" của checkbox cũ không phân biệt được "không đạt" với "chưa ghi nhận" nên báo thiếu, không tự chấm 0.
     *   Link/tài khoản App không còn được coi là đã cài App.
     *
     * @param  object  $acc  dòng project_workflow_steps của bước acceptance (cần id, site_id, status, data)
     * @return array<string, mixed>
     */
    public function acceptanceKpiResult(object $acc): array
    {
        $kpiService = app(\App\Services\Projects\AcceptanceKpiService::class);
        $acc = (object) ['id' => (int) $acc->id, 'site_id' => (int) $acc->site_id, 'status' => (string) ($acc->status ?? ''),
            'approved_at' => $acc->approved_at ?? null, 'data' => $acc->data ?? null];
        $data = $this->jsonArray($acc->data);
        $reworks = $kpiService->revisionCount($acc->site_id);
        $names = fn (array $ids) => DB::table('users')->whereIn('id', array_filter($ids))->pluck('name', 'id');
        $stamp = fn (?string $name, ?string $at, bool $admin = false) => $at
            ? 'xác nhận: '.($name ?: 'người dùng đã xóa').' · '.Carbon::parse($at)->format('d/m/Y H:i').($admin ? ' (ngoại lệ Admin)' : '')
            : null;

        if (isset($data[\App\Services\Projects\AcceptanceKpiService::KEY])) {
            $c = $kpiService->confirmedForKpi($acc);
            $state = $kpiService->state($acc);
            // Trạng thái nhập/xác nhận của từng nhóm (empty/draft/submitted/returned/confirmed/stale) để hiển thị checklist.
            $groupStatus = collect($kpiService->summary($acc))
                ->map(fn ($s) => $s['status'] !== 'confirmed' && $s['stale'] ? 'stale' : $s['status']);
            $who = $names(array_column($c, 'by'));
            $value = fn (array $g) => $g['result'] === null ? null : ($g['result'] === 'pass' ? 1 : 0);
            $confirmed = fn (array $g) => $stamp($g['by'] ? $who->get((int) $g['by']) : null, $g['at'], $g['admin_exception']);
            $complaint = $c['quality']['result'] !== null && $state['values']['complaint'] === 'has';

            return [
                'format' => 'acceptance_kpi',
                'reworks' => $reworks,
                'complaint' => $complaint,
                'complaint_note' => $complaint ? trim((string) $state['values']['complaint_note']) : '',
                'quality_first_pass' => $value($c['quality']),
                'complaint_recorded' => $c['quality']['result'] !== null,
                'quality_missing' => $c['quality']['reason'],
                'quality_confirmed' => $confirmed($c['quality']),
                'hse_pass' => $value($c['hse']),
                'hse_missing' => $c['hse']['reason'],
                'hse_confirmed' => $confirmed($c['hse']),
                'evn_app_completed' => $value($c['evn']),
                'evn_missing' => $c['evn']['reason'],
                'evn_confirmed' => $confirmed($c['evn']),
                'quality_status' => $groupStatus['quality'],
                'hse_status' => $groupStatus['hse'],
                'evn_status' => $groupStatus['evn'],
            ];
        }

        // Dữ liệu cũ: chỉ chấp nhận bản có dấu đánh giá do server ghi.
        $reviewed = ! empty($data['kpi_reviewed_by']) && ! empty($data['kpi_reviewed_at']);
        $reviewer = $reviewed ? ($names([(int) $data['kpi_reviewed_by']])->first()) : null;
        $legacyStamp = $reviewed ? $stamp($reviewer, (string) $data['kpi_reviewed_at']) : null;

        $complaintRecorded = $reviewed && array_key_exists('kpi_customer_complaint', $data)
            && in_array($data['kpi_customer_complaint'], [true, false, 0, 1, '0', '1'], true);
        $complaint = $complaintRecorded && ! empty($data['kpi_customer_complaint']);

        $hseKeys = array_keys(self::ACCEPTANCE_HSE_CHECKS);
        $hseAllTrue = $reviewed && collect($hseKeys)->every(fn ($k) => ($data[$k] ?? null) === true || ($data[$k] ?? null) === 1 || ($data[$k] ?? null) === '1');

        $grid = $kpiService->gridDecision($acc->site_id)['required'];
        $evnMissing = null;
        $evnDone = null;
        if (! $reviewed) {
            $evnMissing = 'Chưa nhập kết quả EVN & App giám sát tại Nghiệm thu';
        } elseif ($grid === null) {
            $evnMissing = 'Chưa xác định công trình có cần đấu nối điện lực (bước Hồ sơ pháp lý)';
        } elseif (empty($data['kpi_app_installed']) || ($grid && empty($data['kpi_evn_grid_tested']))) {
            $evnMissing = 'Dữ liệu cũ chưa ghi nhận đủ EVN/App (ô chưa tick không phân biệt được không đạt hay chưa ghi nhận) — cần nhập lại theo luồng mới';
        } else {
            $evnDone = 1;
        }

        return [
            'format' => 'legacy',
            'reworks' => $reworks,
            'complaint' => $complaint,
            'complaint_note' => $complaint ? trim((string) ($data['kpi_customer_complaint_note'] ?? '')) : '',
            'quality_first_pass' => $complaintRecorded ? ($reworks > 0 || $complaint ? 0 : 1) : null,
            'complaint_recorded' => $complaintRecorded,
            'quality_missing' => $complaintRecorded ? null : 'Chưa xác nhận khách hàng có phản ánh hay không',
            'quality_confirmed' => $complaintRecorded ? $legacyStamp : null,
            'hse_pass' => $hseAllTrue ? 1 : null,
            'hse_missing' => $hseAllTrue ? null : ($reviewed
                ? 'Dữ liệu HSE cũ có mục chưa tick — không phân biệt được không đạt hay chưa ghi nhận; cần nhập lại theo luồng mới'
                : 'Chưa nhập kết quả HSE tại Nghiệm thu'),
            'hse_confirmed' => $hseAllTrue ? $legacyStamp : null,
            'evn_app_completed' => $evnDone,
            'evn_missing' => $evnMissing,
            'evn_confirmed' => $evnDone ? $legacyStamp : null,
            // legacy = đánh giá dạng cũ có dấu người/giờ; legacy_incomplete = dạng cũ nhưng chưa đủ để tính.
            'quality_status' => $complaintRecorded ? 'legacy' : ($reviewed ? 'legacy_incomplete' : 'empty'),
            'hse_status' => $hseAllTrue ? 'legacy' : ($reviewed ? 'legacy_incomplete' : 'empty'),
            'evn_status' => $evnDone ? 'legacy' : ($reviewed ? 'legacy_incomplete' : 'empty'),
        ];
    }

    /** Hồ sơ khảo sát còn thiếu: file bắt buộc bước Khảo sát + bản vẽ sơ bộ, bảng khối lượng vật tư ở bước Đề xuất. */
    public function workflowSurveyMissing(int $siteId, ?Collection $steps = null): array
    {
        $steps ??= DB::table('project_workflow_steps')->where('site_id', $siteId)->get()->keyBy('step_code');
        $site = \App\Models\Projects\Site::query()->find($siteId);
        $survey = $steps->get('survey');
        if (! $site || ! $survey) {
            return ['chưa có bước khảo sát'];
        }

        $documents = fn (object $step) => $this->table('project_workflow_documents')
            ? DB::table('project_workflow_documents')->where('workflow_step_id', $step->id)->get()
            : collect();

        $state = app(\App\Services\Projects\ProjectWorkflowV2Service::class)->documentState($site, 'survey', $survey, $documents($survey));
        $missing = (array) ($state['file_missing'] ?? $state['missing'] ?? []);

        $proposal = $steps->get('proposal');
        $proposalDocs = $proposal ? $documents($proposal) : collect();
        foreach (['preliminary_drawing' => 'Bản vẽ sơ bộ / mô phỏng', 'material_boq' => 'Bảng khối lượng vật tư (dự toán)'] as $code => $label) {
            if ($proposalDocs->where('document_code', $code)->isEmpty()) {
                $missing[] = $label;
            }
        }

        return array_values($missing);
    }

    /**
     * Sai lệch vật tư theo đề xuất vật tư của dự án (%): Σ SL đề xuất BỔ SUNG ÷ Σ SL đề xuất BAN ĐẦU × 100.
     * null khi chưa có đề xuất ban đầu.
     */
    public function workflowMaterialDeviationPercent(int $siteId): ?float
    {
        if (! $this->table('project_material_proposals') || ! $this->table('project_material_proposal_items')
            || ! $this->column('project_material_proposals', 'proposal_type')) {
            return null;
        }

        $sum = fn (string $type) => (float) DB::table('project_material_proposal_items as i')
            ->join('project_material_proposals as p', 'p.id', '=', 'i.proposal_id')
            ->where('p.site_id', $siteId)
            ->whereRaw('UPPER(COALESCE(p.proposal_type, ?)) = ?', ['INITIAL', $type])
            ->whereRaw('UPPER(COALESCE(p.status, ?)) NOT IN (?, ?, ?, ?)', ['', 'DRAFT', 'REJECTED', 'CANCELLED', 'NEEDS_REVISION'])
            ->sum('i.requested_qty');

        $initial = $sum('INITIAL');
        if ($initial <= 0) {
            return null;
        }

        return round($sum('ADDITIONAL') / $initial * 100, 2);
    }

    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) ($value ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Sai lệch vật tư của dự án so với bóc tách khảo sát (%):
     * tiêu hao thực tế = bóc tách (phiếu vật tư chuẩn) + bổ sung/thiếu − thu hồi dư về kho.
     * null khi dự án chưa có phiếu vật tư chuẩn.
     */
    public function materialDeviationPercent(int $projectId): ?float
    {
        if (! $this->table('project_test_material_requests') || ! $this->table('project_test_material_items')) {
            return null;
        }

        $planned = (float) DB::table('project_test_material_items as i')
            ->join('project_test_material_requests as r', 'r.id', '=', 'i.material_request_id')
            ->where('r.project_id', $projectId)
            ->where(fn ($q) => $q->whereNull('r.request_kind')->orWhere('r.request_kind', 'standard'))
            ->whereNotIn('r.status', ['draft', 'rejected', 'cancelled', 'legacy_archived'])
            ->sum('i.quantity');

        if ($planned <= 0) {
            return null;
        }

        $extra = 0.0;
        $returned = 0.0;
        if ($this->table('project_test_material_aftercare_requests') && $this->table('project_test_material_aftercare_items')) {
            $aftercare = fn (array $types, array $statuses) => (float) DB::table('project_test_material_aftercare_items as ai')
                ->join('project_test_material_aftercare_requests as ar', 'ar.id', '=', 'ai.aftercare_request_id')
                ->where('ar.project_id', $projectId)
                ->whereIn('ar.type', $types)
                ->whereIn('ar.status', $statuses)
                ->sum(DB::raw('CASE WHEN ai.processed_quantity > 0 THEN ai.processed_quantity ELSE ai.quantity END'));

            $extra = $aftercare(['shortage', 'additional'], ['approved', 'processing', 'completed']);
            $returned = $aftercare(['return'], ['completed']);
        }

        $actual = $planned + $extra - $returned;

        return round(abs($actual - $planned) / $planned * 100, 2);
    }

    public function projectUsers(int $siteId): Collection
    {
        $ids = collect();

        if ($this->table('project_workflow_assignments') && $this->table('project_workflow_steps')) {
            $ids = DB::table('project_workflow_assignments as a')
                ->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
                ->where('w.site_id', $siteId)
                ->when($this->column('project_workflow_assignments', 'is_active'), fn ($q) => $q->where('a.is_active', 1))
                ->pluck('a.user_id');
        }

        if ($this->table('sites') && $this->column('sites', 'lead_engineer_id')) {
            $lead = (int) (DB::table('sites')->where('id', $siteId)->value('lead_engineer_id') ?? 0);
            if ($lead > 0) {
                $ids->push($lead);
            }
        }

        $ids = $ids->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty() || ! $this->table('users')) {
            return collect();
        }

        return DB::table('users')->whereIn('id', $ids->all())->select('id', 'name', 'email')->orderBy('name')->get();
    }

    public function evidenceForProject(int $siteId, string $month): Collection
    {
        if (! $this->table('technical_kpi_project_evidence')) {
            return collect();
        }

        return DB::table('technical_kpi_project_evidence')
            ->where('site_id', $siteId)
            ->where('payroll_month', $month)
            ->get()
            ->keyBy('user_id');
    }

    public function metricsForUserMonth(int $userId, string $month): array
    {
        $projects = $this->projectsForUserMonth($userId, $month);
        $projectIds = $projects->pluck('site_id')->map(fn ($id) => (int) $id)->filter()->values();
        $evidence = collect();

        if ($projectIds->isNotEmpty() && $this->table('technical_kpi_project_evidence')) {
            $evidence = DB::table('technical_kpi_project_evidence')
                ->where('user_id', $userId)
                ->where('payroll_month', $month)
                ->whereIn('site_id', $projectIds->all())
                ->get();
        }

        [$monthStart, $monthEnd] = $this->monthBounds($month);
        $timelineEligible = $projects->filter(function ($p) use ($monthStart, $monthEnd) {
            if (($p['timeline_excluded'] ?? false) || ! ($p['has_deadline'] ?? false) || empty($p['deadline_iso'])) {
                return false;
            }
            $deadline = Carbon::parse($p['deadline_iso']);

            return $deadline->betweenIncluded($monthStart, $monthEnd);
        });
        $timeline = [
            'available' => $timelineEligible->isNotEmpty(),
            'plan' => (float) $timelineEligible->count(),
            'actual' => (float) $timelineEligible->where('on_time', true)->count(),
            'label' => 'Tự động từ deadline / ngày hoàn thành công trình',
        ];

        // Minh chứng cũ chỉ có nghiệm thu lần đầu, chưa có xác nhận phản ánh: không đưa vào KPI chất lượng.
        $qualityRows = $evidence->filter(fn ($r) => $r->quality_first_pass !== null && ! empty($r->complaint_recorded));
        $quality = [
            'available' => $qualityRows->isNotEmpty(),
            'plan' => (float) $qualityRows->count(),
            'actual' => (float) $qualityRows->filter(fn ($r) => (bool) $r->quality_first_pass)->count(),
            'label' => 'Từ KPI công trình · nghiệm thu lần đầu',
        ];

        $materialRows = $evidence->filter(fn ($r) => $r->material_waste_percent !== null);
        $material = [
            'available' => $materialRows->isNotEmpty(),
            'plan' => 0.0,
            'actual' => $materialRows->isNotEmpty() ? (float) $materialRows->avg('material_waste_percent') : 0.0,
            'label' => 'Từ KPI công trình · % hao hụt vật tư bình quân',
        ];

        $hseRows = $evidence->filter(fn ($r) => $r->hse_pass !== null);
        $hse = [
            'available' => $hseRows->isNotEmpty(),
            'plan' => (float) $hseRows->count(),
            'actual' => (float) $hseRows->filter(fn ($r) => (bool) $r->hse_pass)->count(),
            'label' => 'Từ KPI công trình · checklist HSE',
        ];

        $evnRows = $evidence->filter(fn ($r) => $r->evn_app_required !== null);
        $evnRequired = $evnRows->filter(fn ($r) => (bool) $r->evn_app_required);
        if ($evnRows->isNotEmpty() && $evnRequired->isEmpty()) {
            // Tất cả công trình trong kỳ đều N/A: không trừ KPI.
            $evn = ['available' => true, 'plan' => 1.0, 'actual' => 1.0, 'label' => 'Không có công trình yêu cầu EVN/App trong kỳ (N/A)'];
        } else {
            $evn = [
                'available' => $evnRequired->isNotEmpty(),
                'plan' => (float) $evnRequired->count(),
                'actual' => (float) $evnRequired->filter(fn ($r) => (bool) $r->evn_app_completed)->count(),
                'label' => 'Từ KPI công trình · EVN/App',
            ];
        }

        $penalty = (float) $evidence->sum(fn ($r) => (float) ($r->penalty_points ?? 0));

        return [
            self::SOURCE_TIMELINE => $timeline,
            self::SOURCE_QUALITY => $quality,
            self::SOURCE_MATERIAL => $material,
            self::SOURCE_HSE => $hse,
            self::SOURCE_EVN_APP => $evn,
            'project_penalty_points' => $penalty,
            'project_count' => $projects->count(),
            'issue_count' => $projects->filter(fn ($p) => ! empty($p['issues'] ?? []))->count(),
            'projects' => $projects,
        ];
    }

    public function dashboardForPayrolls(Collection $payrolls, string $month): array
    {
        $map = [];
        foreach ($payrolls as $row) {
            $userId = (int) ($row->user_id ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $metrics = $this->metricsForUserMonth($userId, $month);
            $map[$userId] = $metrics;
        }

        return $map;
    }

    /** Các bước workflow mà người được giao chịu trách nhiệm tiến độ lắp đặt (KS01). */
    public function timelineResponsibleSteps(): array
    {
        return (array) config('technical_kpi_standard.timeline.responsible_steps', ['construction', 'acceptance']);
    }

    /**
     * Mốc tiến độ cấp công trình cho KPI (KS01), dùng chung mọi kỹ sư của công trình:
     *  - Hạn: hạn người giao việc đặt ở bước Nghiệm thu → bước Thi công → hạn hợp đồng (sites.target_completion_at).
     *    `recommitted_due_at` (kỹ sư tự cam kết lại khi chậm / hạn sửa khi trả lại) KHÔNG thay hạn KPI.
     *  - Bàn giao: chỉ khi bước Nghiệm thu được DUYỆT bởi người có ID (approved_by). Bước được hệ thống tự
     *    đánh dấu khi chuyển dữ liệu cũ (approved_by trống) thì dùng ngày hoàn thành/bàn giao ghi ở công trình.
     *    Ngày bàn giao nhập tay khi nghiệm thu chưa duyệt không được tính.
     *
     * @return array{deadline:?string, deadline_source:?string, completed:?string, completion_source:?string,
     *               completion_missing:?string, deadline_note:?string}
     */
    public function siteTimeline(object $site): array
    {
        $steps = $this->table('project_workflow_steps')
            ? DB::table('project_workflow_steps')->where('site_id', $site->site_id ?? $site->id)
                ->whereIn('step_code', ['construction', 'acceptance'])->get()->keyBy('step_code')
            : collect();
        $construction = $steps->get('construction');
        $acceptance = $steps->get('acceptance');

        [$deadline, $deadlineSource] = match (true) {
            ! empty($acceptance?->due_at) => [$acceptance->due_at, 'hạn bước Nghiệm thu'],
            ! empty($construction?->due_at) => [$construction->due_at, 'hạn bước Thi công'],
            ! empty($site->target_completion_at) => [Carbon::parse($site->target_completion_at)->endOfDay()->toDateTimeString(), 'hạn hợp đồng'],
            default => [null, null],
        };

        $completed = null;
        $completionSource = null;
        $completionMissing = null;
        $legacyDate = $site->completed_at ?? $site->handover_at ?? null;
        if ($acceptance && (string) $acceptance->status === 'approved' && ! empty($acceptance->approved_at) && ! empty($acceptance->approved_by)) {
            $completed = $acceptance->approved_at;
            $completionSource = 'ngày duyệt nghiệm thu';
        } elseif ($acceptance && (string) $acceptance->status === 'approved' && empty($acceptance->approved_by)) {
            // Đánh dấu tự động khi chuyển sang quy trình mới: không phải mốc bàn giao thật.
            if ($legacyDate) {
                $completed = Carbon::parse($legacyDate)->endOfDay()->toDateTimeString();
                $completionSource = 'ngày hoàn thành dữ liệu cũ';
            } else {
                $completionMissing = 'Dữ liệu cũ: nghiệm thu không có người duyệt và chưa có ngày hoàn thành/bàn giao — cần bổ sung.';
            }
        } elseif (! $acceptance && $legacyDate) {
            $completed = Carbon::parse($legacyDate)->endOfDay()->toDateTimeString();
            $completionSource = 'ngày hoàn thành ghi ở công trình';
        } elseif (! empty($site->handover_at)) {
            $completionMissing = 'Có ngày bàn giao nhập tay nhưng bước Nghiệm thu chưa được duyệt — chưa tính.';
        }

        $notes = [];
        foreach (array_filter([$acceptance, $construction]) as $step) {
            if (! empty($step->recommitted_due_at)) {
                $notes[] = 'cam kết lại '.Carbon::parse($step->recommitted_due_at)->format('d/m/Y').' (không thay hạn KPI)';
            }
            $changes = $this->dueChanges((int) $step->id);
            if ($changes > 0) {
                $notes[] = 'người giao việc đổi hạn '.$changes.' lần';
            }
        }

        return [
            'deadline' => $deadline ? Carbon::parse($deadline)->toDateTimeString() : null,
            'deadline_source' => $deadlineSource,
            'completed' => $completed ? Carbon::parse($completed)->toDateTimeString() : null,
            'completion_source' => $completionSource,
            'completion_missing' => $completionMissing,
            'deadline_note' => $notes ? implode('; ', array_unique($notes)) : null,
        ];
    }

    /** Số lần người giao việc đổi hạn của bước (sự kiện giao việc có hạn cũ khác hạn mới). */
    private function dueChanges(int $stepId): int
    {
        if (! $this->table('project_workflow_events')) {
            return 0;
        }

        return DB::table('project_workflow_events')
            ->where('workflow_step_id', $stepId)
            ->where('action', 'step_assigned')
            ->pluck('payload')
            ->filter(function ($payload) {
                $p = $this->jsonArray($payload);

                return ! empty($p['previous_due_at']) && ! empty($p['due_at'])
                    && Carbon::parse($p['previous_due_at'])->ne(Carbon::parse($p['due_at']));
            })
            ->count();
    }

    private function summarizeProjectRows(Collection $rows, int $userId, string $month): array
    {
        $first = $rows->first();
        $construction = $rows->firstWhere('step_code', 'construction');
        $acceptance = $rows->firstWhere('step_code', 'acceptance');
        $reference = $acceptance ?: $construction ?: $rows->last();
        $timeline = $this->siteTimeline($first);
        $deadline = $timeline['deadline'];
        $completed = $timeline['completed'];
        $onTime = null;
        if ($deadline && $completed) {
            $onTime = Carbon::parse($completed)->lte(Carbon::parse($deadline));
        }
        [, $monthEnd] = $this->monthBounds($month);
        $asOf = $monthEnd->copy()->min(now());
        $overdueOpen = $deadline && Carbon::parse($deadline)->lt($asOf)
            && (! $completed || Carbon::parse($completed)->gt($monthEnd));
        $responsible = (int) ($first->lead_engineer_id ?? 0) === $userId
            || $rows->contains(fn ($r) => in_array((string) $r->step_code, $this->timelineResponsibleSteps(), true));

        $issues = [];
        if ($deadline && $completed && ! $onTime) {
            $issues[] = 'Trễ tiến độ';
        }
        if ($deadline && ! $completed && Carbon::parse($deadline)->isPast()) {
            $issues[] = 'Đang quá hạn';
        }
        if ($acceptance && in_array((string) $acceptance->step_status, ['revision', 'returned'], true)) {
            $issues[] = 'Nghiệm thu cần bổ sung';
        }
        if ($acceptance && trim((string) ($acceptance->returned_reason ?? '')) !== '') {
            $issues[] = 'Có lý do trả nghiệm thu';
        }

        $evidence = $this->evidenceRow((int) $first->site_id, $userId, $month);
        if ($evidence) {
            if ($evidence->quality_first_pass === 0) {
                $issues[] = 'Không đạt nghiệm thu lần đầu';
            }
            if ($evidence->hse_pass === 0) {
                $issues[] = 'HSE chưa đạt';
            }
            if ((float) ($evidence->material_waste_percent ?? 0) > 4) {
                $issues[] = 'Hao hụt vật tư cao';
            }
            if ((float) ($evidence->penalty_points ?? 0) > 0) {
                $issues[] = 'Có điểm phạt';
            }
        }

        return [
            'site_id' => (int) $first->site_id,
            'code' => (string) ($first->project_code ?: ('DA-SITE-'.$first->site_id)),
            'name' => (string) ($first->name ?: ('Công trình #'.$first->site_id)),
            'role' => (string) ($rows->first()->assignment_role ?? 'collaborator'),
            'steps' => $rows->pluck('step_code')->filter()->unique()->values()->all(),
            'deadline' => $deadline ? Carbon::parse($deadline)->format('d/m/Y') : null,
            'deadline_iso' => $deadline ? Carbon::parse($deadline)->toDateTimeString() : null,
            'completed' => $completed ? Carbon::parse($completed)->format('d/m/Y') : null,
            'completed_iso' => $completed ? Carbon::parse($completed)->toDateTimeString() : null,
            'has_deadline' => (bool) $deadline,
            'on_time' => $onTime,
            'timeline_excluded' => (bool) ($evidence->timeline_excluded ?? false),
            'timeline_responsible' => $responsible,
            'overdue_open' => (bool) $overdueOpen,
            'completion_source' => $timeline['completion_source'],
            'completion_missing' => $completed ? null : $timeline['completion_missing'],
            'deadline_note' => trim(($timeline['deadline_source'] ? 'theo '.$timeline['deadline_source'] : '').($timeline['deadline_note'] ? '; '.$timeline['deadline_note'] : ''), '; ') ?: null,
            'issues' => array_values(array_unique($issues)),
            'activity_at' => (string) ($completed ?? $reference->approved_at ?? $reference->submitted_at ?? $first->completed_at ?? ''),
            'evidence_status' => $evidence ? 'configured' : 'missing',
            'source' => 'workflow',
        ];
    }

    private function summarizeLegacySite(object $site, int $userId, string $month): array
    {
        $timeline = $this->siteTimeline($site);
        $deadline = $timeline['deadline'];
        $completed = $timeline['completed'];
        $onTime = $deadline && $completed ? Carbon::parse($completed)->lte(Carbon::parse($deadline)) : null;
        [, $monthEnd] = $this->monthBounds($month);
        $overdueOpen = $deadline && Carbon::parse($deadline)->lt($monthEnd->copy()->min(now()))
            && (! $completed || Carbon::parse($completed)->gt($monthEnd));
        $evidence = $this->evidenceRow((int) $site->id, $userId, $month);
        $issues = [];
        if ($deadline && $completed && ! $onTime) {
            $issues[] = 'Trễ tiến độ';
        }
        if ($evidence && $evidence->hse_pass === 0) {
            $issues[] = 'HSE chưa đạt';
        }
        if ($evidence && (float) ($evidence->penalty_points ?? 0) > 0) {
            $issues[] = 'Có điểm phạt';
        }

        return [
            'site_id' => (int) $site->id,
            'code' => (string) ($site->project_code ?? ('DA-SITE-'.$site->id)),
            'name' => (string) ($site->name ?? ('Công trình #'.$site->id)),
            'role' => 'lead',
            'steps' => [],
            'deadline' => $deadline ? Carbon::parse($deadline)->format('d/m/Y') : null,
            'deadline_iso' => $deadline ? Carbon::parse($deadline)->toDateTimeString() : null,
            'completed' => $completed ? Carbon::parse($completed)->format('d/m/Y') : null,
            'completed_iso' => $completed ? Carbon::parse($completed)->toDateTimeString() : null,
            'has_deadline' => (bool) $deadline,
            'on_time' => $onTime,
            'timeline_excluded' => (bool) ($evidence->timeline_excluded ?? false),
            'timeline_responsible' => true,
            'overdue_open' => (bool) $overdueOpen,
            'completion_source' => $timeline['completion_source'],
            'completion_missing' => $completed ? null : $timeline['completion_missing'],
            'deadline_note' => trim(($timeline['deadline_source'] ? 'theo '.$timeline['deadline_source'] : '').($timeline['deadline_note'] ? '; '.$timeline['deadline_note'] : ''), '; ') ?: null,
            'issues' => $issues,
            'activity_at' => (string) ($completed ?? $site->updated_at ?? ''),
            'evidence_status' => $evidence ? 'configured' : 'missing',
            'source' => 'site_lead',
        ];
    }

    private function evidenceRow(int $siteId, int $userId, string $month): ?object
    {
        if (! $this->table('technical_kpi_project_evidence')) {
            return null;
        }

        return DB::table('technical_kpi_project_evidence')
            ->where('site_id', $siteId)
            ->where('user_id', $userId)
            ->where('payroll_month', $month)
            ->first();
    }
}
