<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\ProjectTest\Project;
use App\Models\Technical\TechnicalScheduleEvent;
use App\Models\User;
use App\Support\EgoCompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TechnicalWorkspaceController extends Controller
{
    /** @var list<string> */
    private const TECHNICAL_ROLES = [
        'ky_thuat', 'technical', 'technician', 'technical_staff',
        'technical_leader', 'technical_manager', 'truong_phong_ky_thuat',
    ];

    private const ACTIVE_PROJECT_STATUSES = [
        'survey_pending', 'survey_reschedule', 'survey_confirmed', 'survey_in_progress',
        'sales_review', 'proposal_revision', 'installation_pending', 'installation_reschedule',
        'materials_pending', 'materials_admin_review', 'materials_revision', 'warehouse_preparing',
        'warehouse_issued', 'assignment_pending', 'ready_install', 'installing', 'acceptance_pending',
    ];

    /** @var array<string, list<string>> */
    private const PROJECT_STAGE_STATUSES = [
        'waiting-survey' => ['survey_pending', 'survey_reschedule', 'survey_confirmed'],
        'waiting-design' => ['survey_in_progress', 'sales_review', 'proposal_revision'],
        'waiting-materials' => ['materials_pending', 'materials_admin_review', 'materials_revision', 'warehouse_preparing'],
        'waiting-installation' => ['installation_pending', 'installation_reschedule', 'warehouse_issued', 'assignment_pending', 'ready_install'],
        'installing' => ['installing'],
        'waiting-acceptance' => ['acceptance_pending'],
        'warranty' => ['warranty_active'],
        'survey-schedule' => ['survey_pending', 'survey_reschedule', 'survey_confirmed', 'survey_in_progress'],
        'installation-schedule' => ['installation_pending', 'installation_reschedule', 'assignment_pending', 'ready_install', 'installing'],
        'assignments' => ['warehouse_issued', 'assignment_pending', 'ready_install'],
    ];

    public function overview(Request $request): View
    {
        $date = $this->safeDate($request->query('date')) ?? today();
        $rows = $this->dailyOperationRows($request, $date);
        $summary = $this->dailySummary($rows);
        $sourceSummary = $this->dailySourceSummary($rows);
        $generatedAt = now();
        $user = $request->user();
        $projectQuery = $this->visibleProjectQuery($user);

        $projectStats = [
            'active' => (clone $projectQuery)->whereIn('status', self::ACTIVE_PROJECT_STATUSES)->count(),
            'survey' => (clone $projectQuery)->whereIn('status', self::PROJECT_STAGE_STATUSES['survey-schedule'])->count(),
            'installation' => (clone $projectQuery)->whereIn('status', self::PROJECT_STAGE_STATUSES['installation-schedule'])->count(),
            'warranty' => (clone $projectQuery)->where('status', 'warranty_active')->count(),
        ];

        $materialSummary = ['pending' => 0, 'warehouse' => 0, 'issued' => 0, 'shortage' => 0];
        if (Schema::hasTable('project_test_material_requests')) {
            $projectIds = $projectQuery->pluck('id')->all() ?: [0];
            $base = DB::table('project_test_material_requests')->whereIn('project_id', $projectIds);
            $materialSummary = [
                'pending' => (clone $base)->whereIn('status', ['pending_admin', 'pending', 'submitted', 'materials_admin_review'])->count(),
                'warehouse' => (clone $base)->whereIn('status', ['approved', 'preparing', 'warehouse_preparing', 'reserved'])->count(),
                'issued' => (clone $base)->whereIn('status', ['issued', 'warehouse_issued', 'completed'])->count(),
                'shortage' => (clone $base)->whereIn('status', ['shortage', 'waiting_import', 'partial'])->count(),
            ];
        }

        $upcomingEvents = $this->scheduleEvents(
            $request,
            $date->copy()->startOfDay(),
            $date->copy()->addDays(7)->endOfDay()
        )->take(12);

        return view('technical.workspace.overview', compact(
            'date', 'rows', 'summary', 'sourceSummary', 'generatedAt',
            'projectStats', 'materialSummary', 'upcomingEvents'
        ));
    }

    public function dailyReport(Request $request): View
    {
        $date = $this->safeDate($request->query('date')) ?? today();
        $rows = $this->dailyOperationRows($request, $date);
        $summary = $this->dailySummary($rows);
        $sourceSummary = $this->dailySourceSummary($rows);
        $generatedAt = now();

        return view('technical.workspace.daily-report', compact(
            'date', 'rows', 'summary', 'sourceSummary', 'generatedAt'
        ));
    }

    public function weeklyPlan(Request $request): View
    {
        $anchor = $this->safeDate($request->query('week')) ?? today();
        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $allowedTypes = ['survey', 'installation', 'maintenance'];
        $selectedType = in_array($request->query('type'), $allowedTypes, true)
            ? (string) $request->query('type')
            : '';
        $keyword = Str::lower(trim((string) $request->query('q')));
        $teamIds = $this->technicalTeamIds($request->user());
        $teamMembers = User::query()
            ->whereIn('id', $teamIds)
            ->orderBy('name')
            ->get(['id', 'name']);
        $selectedUserId = (int) $request->query('user_id', 0);
        if ($selectedUserId > 0 && ! $teamIds->contains($selectedUserId)) {
            $selectedUserId = 0;
        }

        $events = $this->scheduleEvents(
            $request,
            $weekStart->copy()->startOfDay(),
            $weekEnd->copy()->endOfDay(),
            $selectedType !== '' ? [$selectedType] : $allowedTypes,
        );

        if ($selectedUserId > 0) {
            $events = $events->filter(
                fn ($event): bool => $event->participants->contains(
                    fn ($participant): bool => (int) $participant->user_id === $selectedUserId
                )
            )->values();
        }

        if ($keyword !== '') {
            $events = $events->filter(function ($event) use ($keyword): bool {
                $haystack = Str::lower(implode(' ', [
                    (string) $event->title,
                    (string) $event->address,
                    (string) ($event->project?->code ?? ''),
                    (string) ($event->project?->name ?? ''),
                    $event->participants->pluck('user.name')->filter()->join(' '),
                ]));

                return Str::contains($haystack, $keyword);
            })->values();
        }

        $days = collect(range(0, 6))->map(fn (int $offset): Carbon => $weekStart->copy()->addDays($offset));
        $eventsByDate = $events->groupBy(fn ($event): string => Carbon::parse($event->starts_at)->toDateString());
        $conflicts = $this->scheduleConflicts($events);
        $conflictEventIds = $conflicts->pluck('event_ids')->flatten()->map(fn ($id): int => (int) $id)->unique()->values();
        $summary = [
            'total' => $events->count(),
            'survey' => $events->where('event_type', 'survey')->count(),
            'installation' => $events->where('event_type', 'installation')->count(),
            'maintenance' => $events->where('event_type', 'maintenance')->count(),
            'unassigned' => $events->filter(fn ($event): bool => $event->participants->isEmpty())->count(),
            'conflicts' => $conflicts->count(),
        ];
        $sourceSummary = [
            'project' => $events->where('source_type', 'project')->count(),
            'maintenance' => $events->where('source_type', 'maintenance')->count(),
        ];
        $generatedAt = now();

        return view('technical.workspace.weekly-plan', compact(
            'weekStart', 'weekEnd', 'days', 'events', 'eventsByDate', 'conflicts',
            'conflictEventIds', 'summary', 'sourceSummary', 'generatedAt',
            'teamMembers', 'selectedUserId', 'selectedType'
        ));
    }

    public function installationCalendar(Request $request): View
    {
        return $this->renderScheduleCalendar($request, 'installation');
    }

    public function maintenanceCalendar(Request $request): View
    {
        return $this->renderScheduleCalendar($request, 'maintenance');
    }

    public function materialRequests(Request $request): View
    {
        $keyword = trim((string) $request->query('q'));
        $status = trim((string) $request->query('status'));
        $allowedAlerts = ['shortage', 'unmapped', 'late'];
        $alert = in_array($request->query('alert'), $allowedAlerts, true)
            ? (string) $request->query('alert')
            : '';
        $projectQuery = $this->visibleProjectQuery($request->user());
        $projectIds = (clone $projectQuery)->pluck('id')->all() ?: [0];
        $proposalProjects = (clone $projectQuery)
            ->whereIn('status', self::ACTIVE_PROJECT_STATUSES)
            ->orderByDesc('updated_at')
            ->limit(150)
            ->get(['id', 'code', 'name'])
            ->map(function (Project $project): array {
                return [
                    'id' => (int) $project->id,
                    'label' => trim(($project->code ? $project->code.' · ' : '').$project->name),
                    'url' => Route::has('project-test.show')
                        ? route('project-test.show', $project->id).'?tab=materials'
                        : url('/ky-thuat/cong-trinh/'.$project->id.'?tab=materials'),
                ];
            });

        if (! Schema::hasTable('project_test_material_requests')) {
            return view('technical.workspace.materials', [
                'requests' => $this->emptyPaginator($request),
                'summary' => [
                    'total' => 0, 'pending' => 0, 'warehouse' => 0,
                    'issued' => 0, 'shortage' => 0, 'unmapped' => 0,
                ],
                'statuses' => [],
                'proposalProjects' => $proposalProjects,
                'alert' => $alert,
            ]);
        }

        $requestMetrics = collect();
        if (Schema::hasTable('project_test_material_items')) {
            $needRows = DB::table('project_test_material_items as mi')
                ->join('project_test_material_requests as mr', 'mr.id', '=', 'mi.material_request_id')
                ->whereIn('mr.project_id', $projectIds)
                ->selectRaw('mi.material_request_id, mi.product_id, mr.warehouse_id, mr.status as request_status, COUNT(mi.id) as item_count')
                ->selectRaw('SUM(GREATEST(COALESCE(mi.quantity, 0) - COALESCE(mi.issued_quantity, 0), 0)) as required_qty')
                ->groupBy('mi.material_request_id', 'mi.product_id', 'mr.warehouse_id', 'mr.status')
                ->get();

            $productIds = $needRows->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
            $stockRows = collect();
            if (Schema::hasTable('crm_product_stock') && $productIds->isNotEmpty()) {
                $stockQuery = DB::table('crm_product_stock')->whereIn('product_id', $productIds);
                $companyId = EgoCompanyScope::currentId();
                if ($companyId > 0 && Schema::hasColumn('crm_product_stock', 'company_id')) {
                    $stockQuery->where('company_id', $companyId);
                }
                $stockRows = $stockQuery
                    ->selectRaw('product_id, warehouse_id, SUM(qty) as available_qty')
                    ->groupBy('product_id', 'warehouse_id')
                    ->get();
            }

            $stockByProduct = $stockRows->groupBy('product_id')->map(
                fn (Collection $rows): float => (float) $rows->sum('available_qty')
            );
            $stockByWarehouseProduct = $stockRows->mapWithKeys(
                fn ($row): array => [((int) $row->warehouse_id).':'.((int) $row->product_id) => (float) $row->available_qty]
            );

            $requestMetrics = $needRows->groupBy('material_request_id')->map(function (Collection $rows) use ($stockByProduct, $stockByWarehouseProduct): array {
                $metrics = [
                    'item_count' => 0,
                    'required_qty' => 0.0,
                    'shortage_item_count' => 0,
                    'shortage_qty' => 0.0,
                    'unmapped_item_count' => 0,
                ];

                foreach ($rows as $row) {
                    $required = max(0.0, (float) $row->required_qty);
                    $metrics['item_count'] += (int) $row->item_count;
                    $metrics['required_qty'] += $required;

                    if (in_array($row->request_status, ['issued', 'warehouse_issued', 'completed', 'cancelled', 'rejected'], true)) {
                        continue;
                    }

                    if (! $row->product_id) {
                        $metrics['unmapped_item_count'] += (int) $row->item_count;
                        continue;
                    }

                    $productId = (int) $row->product_id;
                    $warehouseId = (int) ($row->warehouse_id ?? 0);
                    $available = $warehouseId > 0
                        ? (float) $stockByWarehouseProduct->get($warehouseId.':'.$productId, 0)
                        : (float) $stockByProduct->get($productId, 0);
                    if ($available < $required) {
                        $metrics['shortage_item_count']++;
                        $metrics['shortage_qty'] += $required - $available;
                    }
                }

                return $metrics;
            });
        }

        $metricShortageRequestIds = $requestMetrics
            ->filter(fn (array $metrics): bool => $metrics['shortage_item_count'] > 0)
            ->keys()->map(fn ($id): int => (int) $id)->all();
        $statusShortageRequestIds = DB::table('project_test_material_requests')
            ->whereIn('project_id', $projectIds)
            ->where(function (Builder $builder): void {
                $builder->whereIn('status', ['shortage', 'waiting_import', 'waiting_replenishment', 'partial'])
                    ->orWhereIn('warehouse_status', ['shortage', 'waiting_import', 'waiting_replenishment', 'partial']);
            })
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $shortageRequestIds = array_values(array_unique(array_merge(
            $metricShortageRequestIds,
            $statusShortageRequestIds
        )));
        $unmappedRequestIds = $requestMetrics
            ->filter(fn (array $metrics): bool => $metrics['unmapped_item_count'] > 0)
            ->keys()->map(fn ($id): int => (int) $id)->all();

        $query = DB::table('project_test_material_requests as mr')
            ->join('project_test_projects as p', 'p.id', '=', 'mr.project_id')
            ->leftJoin('users as requester', 'requester.id', '=', 'mr.requested_by')
            ->leftJoin('users as reviewer', 'reviewer.id', '=', 'mr.reviewed_by')
            ->whereIn('mr.project_id', $projectIds)
            ->select([
                'mr.*', 'p.code as project_code', 'p.name as project_name', 'p.address as project_address',
                'p.proposed_installation_at', 'p.installation_confirmed_at',
                'requester.name as requester_name', 'reviewer.name as reviewer_name',
            ]);

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('mr.code', 'like', "%{$keyword}%")
                    ->orWhere('p.code', 'like', "%{$keyword}%")
                    ->orWhere('p.name', 'like', "%{$keyword}%")
                    ->orWhere('p.address', 'like', "%{$keyword}%")
                    ->orWhere('requester.name', 'like', "%{$keyword}%");
            });
        }
        if ($status !== '') {
            $query->where('mr.status', $status);
        }
        if ($alert === 'shortage') {
            $query->whereIn('mr.id', $shortageRequestIds ?: [0]);
        } elseif ($alert === 'unmapped') {
            $query->whereIn('mr.id', $unmappedRequestIds ?: [0]);
        } elseif ($alert === 'late') {
            $query->whereNotNull('mr.needed_at')
                ->whereDate('mr.needed_at', '<', today()->toDateString())
                ->whereNotIn('mr.status', ['issued', 'warehouse_issued', 'completed']);
        }

        $requests = $query->orderByRaw('CASE WHEN mr.needed_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('mr.needed_at')->orderByDesc('mr.id')->paginate(25)->withQueryString();

        $requests->getCollection()->transform(function ($item) use ($requestMetrics, $shortageRequestIds, $unmappedRequestIds) {
            $metrics = $requestMetrics->get((int) $item->id, [
                'item_count' => 0,
                'required_qty' => 0.0,
                'shortage_item_count' => 0,
                'shortage_qty' => 0.0,
                'unmapped_item_count' => 0,
            ]);
            foreach ($metrics as $key => $value) {
                $item->{$key} = $value;
            }
            $item->has_shortage_alert = in_array((int) $item->id, $shortageRequestIds, true);
            $item->has_unmapped_alert = in_array((int) $item->id, $unmappedRequestIds, true);
            return $item;
        });

        $base = DB::table('project_test_material_requests')->whereIn('project_id', $projectIds);
        $summary = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->whereIn('status', ['pending_admin', 'pending_manager', 'pending', 'submitted', 'materials_admin_review'])->count(),
            'warehouse' => (clone $base)->whereIn('status', ['approved', 'preparing', 'warehouse_preparing', 'reserved', 'partial'])->count(),
            'issued' => (clone $base)->whereIn('status', ['issued', 'warehouse_issued', 'completed'])->count(),
            'shortage' => count($shortageRequestIds),
            'unmapped' => count($unmappedRequestIds),
        ];
        $statuses = DB::table('project_test_material_requests')->whereIn('project_id', $projectIds)
            ->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->all();

        return view('technical.workspace.materials', compact(
            'requests', 'summary', 'statuses', 'proposalProjects', 'alert'
        ));
    }

    public function tasks(Request $request): RedirectResponse
    {
        return redirect()->route($this->isManager($request->user()) ? 'tasks.index' : 'tasks.my');
    }

    // 2. Công trình
    public function projectsFromSales(): RedirectResponse
    {
        return redirect()->route('technical-projects.from-sales');
    }

    public function projectsTechnical(): RedirectResponse
    {
        return redirect()->route('technical-projects.created');
    }

    public function projectsAll(): RedirectResponse
    {
        return redirect()->route('technical-projects.all');
    }

    public function deploymentPlan(): RedirectResponse
    {
        return redirect()->route('technical-projects.deployment');
    }

    public function acceptanceHandover(): RedirectResponse
    {
        return redirect()->route('technical-projects.handover');
    }

    public function projectsWaitingSurvey(): RedirectResponse
    {
        return $this->projectStage('waiting-survey');
    }

    public function projectsWaitingDesign(): RedirectResponse
    {
        return $this->projectStage('waiting-design');
    }

    public function projectsWaitingMaterials(): RedirectResponse
    {
        return $this->projectStage('waiting-materials');
    }

    public function projectsWaitingInstallation(): RedirectResponse
    {
        return $this->projectStage('waiting-installation');
    }

    public function projectsInstalling(): RedirectResponse
    {
        return $this->projectStage('installing');
    }

    public function projectsWaitingAcceptance(): RedirectResponse
    {
        return $this->projectStage('waiting-acceptance');
    }

    public function projectsWarranty(): RedirectResponse
    {
        return $this->projectStage('warranty');
    }

    // 3. Điều phối kỹ thuật
    public function surveySchedule(): RedirectResponse
    {
        return $this->projectStage('survey-schedule');
    }

    public function installationSchedule(): RedirectResponse
    {
        return $this->projectStage('installation-schedule');
    }

    public function assignments(): RedirectResponse
    {
        return $this->projectStage('assignments');
    }

    public function teamTasks(Request $request): RedirectResponse
    {
        return redirect()->route($this->isManager($request->user()) ? 'tasks.index' : 'tasks.my');
    }

    public function myTasks(): RedirectResponse
    {
        return redirect()->route('tasks.my');
    }

    // 4. Hồ sơ kỹ thuật
    public function surveyDocuments(Request $request): View
    {
        $request->query->set('kind', 'survey');
        return $this->documents($request);
    }

    public function designDocuments(Request $request): View
    {
        $request->query->set('kind', 'design');
        return $this->documents($request);
    }

    public function materialDocuments(): RedirectResponse
    {
        return $this->projectStage('waiting-materials', ['tech_menu' => 'documents-materials']);
    }

    public function dailyLogDocuments(Request $request): View
    {
        $request->query->set('kind', 'daily_log');
        return $this->documents($request);
    }

    public function acceptanceDocuments(): RedirectResponse
    {
        return $this->projectStage('waiting-acceptance', ['tech_menu' => 'documents-acceptance']);
    }

    // 5. Bảo trì & Bảo hành
    public function maintenanceSchedule(): RedirectResponse
    {
        if (Route::has('ky-thuat.maintenance.index')) {
            return redirect()->route('ky-thuat.maintenance.index');
        }

        return redirect()->route('technical-workspace.warranty');
    }

    public function warrantyIssues(Request $request): RedirectResponse
    {
        return redirect()->route('projects-unified.maintenance.index', ['view' => 'claims']);
    }

    public function replacementDevices(Request $request): RedirectResponse
    {
        return redirect()->route('ky-thuat.warranty-exchange.index', array_merge(
            $request->query(),
            ['bucket' => 'open']
        ));
    }

    public function warrantyHistory(Request $request): RedirectResponse
    {
        return redirect()->route('projects-unified.maintenance.index', ['view' => 'claims']);
    }

    // 6. Báo cáo
    public function projectProgressReport(Request $request): View
    {
        return $this->renderReport($request, 'progress');
    }

    public function performanceReport(Request $request): View
    {
        return $this->renderReport($request, 'performance');
    }

    public function overdueTasksReport(Request $request): View
    {
        return $this->renderReport($request, 'overdue');
    }

    public function incidentReport(Request $request): View
    {
        return $this->renderReport($request, 'incidents');
    }

    public function report(Request $request): View
    {
        return $this->renderReport($request, 'progress');
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $mode = $this->safeReportMode((string) $request->query('mode', 'progress'));
        $payload = $this->reportExportPayload($request, $mode);
        $filename = 'bao-cao-ky-thuat-'.$mode.'-'.now()->format('Ymd-His').'.xlsx';

        return $this->streamReportWorkbook($payload, $filename);
    }

    public function storeReportSnapshot(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('technical_report_snapshots')) {
            return back()->with('error', 'Chưa có bảng lịch sử báo cáo. Vui lòng chạy migration V15.5.');
        }

        $mode = $this->safeReportMode((string) $request->input('mode', 'progress'));
        $payload = $this->reportExportPayload($request, $mode);
        $user = $request->user();
        $companyId = EgoCompanyScope::currentId();
        $reportCode = 'KT-BC-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
        $snapshotId = DB::table('technical_report_snapshots')->insertGetId([
            'company_id' => $companyId > 0 ? $companyId : null,
            'report_code' => $reportCode,
            'report_type' => $mode,
            'title' => $payload['title'],
            'period_from' => $payload['period_from'],
            'period_to' => $payload['period_to'],
            'filters' => json_encode($payload['filters'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'summary' => json_encode($payload['summary'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'columns' => json_encode($payload['columns'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'rows' => json_encode($payload['rows'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'generated_by' => $user?->id,
            'generated_name' => $user?->name ?: 'Hệ thống',
            'generated_at' => now(),
            'notification_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notificationCount = $this->notifyReportSaved($request, $snapshotId, $reportCode, $payload);
        DB::table('technical_report_snapshots')->where('id', $snapshotId)->update([
            'notification_count' => $notificationCount,
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('technical-workspace.reports.history.show', $snapshotId)
            ->with('success', "Đã lưu báo cáo {$reportCode} và gửi {$notificationCount} thông báo.");
    }

    public function reportHistory(Request $request): View
    {
        $mode = trim((string) $request->query('mode'));
        $keyword = trim((string) $request->query('q'));
        if (! Schema::hasTable('technical_report_snapshots')) {
            $snapshots = $this->emptyPaginator($request);
            return view('technical.workspace.report-history', compact('snapshots', 'mode', 'keyword'));
        }
        $query = DB::table('technical_report_snapshots');

        $this->scopeReportSnapshotQuery($query, $request->user());
        if (in_array($mode, ['daily', 'progress', 'performance', 'overdue', 'incidents'], true)) {
            $query->where('report_type', $mode);
        }
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('report_code', 'like', "%{$keyword}%")
                    ->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('generated_name', 'like', "%{$keyword}%");
            });
        }

        $snapshots = $query->orderByDesc('generated_at')->paginate(20)->withQueryString();

        return view('technical.workspace.report-history', compact('snapshots', 'mode', 'keyword'));
    }

    public function showReportSnapshot(Request $request, int $snapshot): View
    {
        $row = $this->visibleReportSnapshot($request, $snapshot);
        $payload = $this->snapshotPayload($row);

        return view('technical.workspace.report-snapshot', compact('row', 'payload'));
    }

    public function exportReportSnapshot(Request $request, int $snapshot): StreamedResponse
    {
        $row = $this->visibleReportSnapshot($request, $snapshot);
        $payload = $this->snapshotPayload($row);
        $filename = Str::slug((string) $row->report_code).'.xlsx';

        return $this->streamReportWorkbook($payload, $filename);
    }

    /**
     * Trang cũ vẫn được giữ để tương thích link đã phát hành.
     */
    public function warranty(Request $request): View
    {
        $user = $request->user();
        $projectIds = $this->visibleProjectQuery($user)->pluck('id');
        $keyword = trim((string) $request->query('q'));
        $status = trim((string) $request->query('status'));

        if (! Schema::hasTable('project_test_warranties') || ! Schema::hasTable('project_test_projects')) {
            return view('technical.workspace.warranty', [
                'warranties' => $this->emptyPaginator($request),
                'summary' => ['active' => 0, 'due_30' => 0, 'overdue' => 0, 'expired' => 0],
            ]);
        }

        $query = DB::table('project_test_warranties as w')
            ->join('project_test_projects as p', 'p.id', '=', 'w.project_id')
            ->leftJoin('users as u', 'u.id', '=', 'w.assigned_to')
            ->whereIn('w.project_id', $projectIds->all() ?: [0])
            ->select([
                'w.id', 'w.project_id', 'w.starts_at', 'w.ends_at', 'w.next_maintenance_at',
                'w.status', 'w.note', 'p.code', 'p.name', 'p.address', 'p.contact_name',
                'p.contact_phone', 'u.name as assigned_name',
            ]);

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('p.code', 'like', "%{$keyword}%")
                    ->orWhere('p.name', 'like', "%{$keyword}%")
                    ->orWhere('p.address', 'like', "%{$keyword}%")
                    ->orWhere('p.contact_name', 'like', "%{$keyword}%")
                    ->orWhere('p.contact_phone', 'like', "%{$keyword}%");
            });
        }

        if ($status !== '') {
            $query->where('w.status', $status);
        }

        $warranties = $query
            ->orderByRaw('CASE WHEN w.next_maintenance_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('w.next_maintenance_at')
            ->paginate(20)
            ->withQueryString();

        $summaryBase = DB::table('project_test_warranties')->whereIn('project_id', $projectIds->all() ?: [0]);
        $summary = [
            'active' => (clone $summaryBase)->where('status', 'active')->count(),
            'due_30' => (clone $summaryBase)->where('status', 'active')
                ->whereNotNull('next_maintenance_at')
                ->whereBetween('next_maintenance_at', [now()->toDateString(), now()->copy()->addDays(30)->toDateString()])
                ->count(),
            'overdue' => (clone $summaryBase)->where('status', 'active')
                ->whereNotNull('next_maintenance_at')
                ->whereDate('next_maintenance_at', '<', now()->toDateString())
                ->count(),
            'expired' => (clone $summaryBase)->whereDate('ends_at', '<', now()->toDateString())->count(),
        ];

        return view('technical.workspace.warranty', compact('warranties', 'summary'));
    }

    public function documents(Request $request): View
    {
        $user = $request->user();
        $keyword = trim((string) $request->query('q'));
        $kind = trim((string) $request->query('kind'));

        $projects = $this->visibleProjectQuery($user)
            ->with([
                'survey:id,project_id,attachment_file,design_3d_file,completed_at',
                'dailyLogs:id,project_id,created_by,log_date,attachment_file,content',
                'dailyLogs.author:id,name',
                'acceptance:id,project_id,accepted_at,report_file,note',
            ])
            ->latest('id')
            ->limit(160)
            ->get(['id', 'code', 'name', 'address', 'status']);

        $documents = collect();
        foreach ($projects as $project) {
            if ($project->survey?->attachment_file) {
                $documents->push($this->documentRow($project, 'survey', 'Khảo sát & hình ảnh', $project->survey->attachment_file, $project->survey->completed_at));
            }
            if ($project->survey?->design_3d_file) {
                $documents->push($this->documentRow($project, 'design', 'File 3D/CAD', $project->survey->design_3d_file, $project->survey->completed_at));
            }
            foreach ($project->dailyLogs as $log) {
                if ($log->attachment_file) {
                    $documents->push($this->documentRow(
                        $project,
                        'daily_log',
                        'Nhật ký thi công',
                        $log->attachment_file,
                        $log->log_date,
                        $log->author?->name,
                        Str::limit((string) $log->content, 100)
                    ));
                }
            }
            if ($project->acceptance?->report_file) {
                $documents->push($this->documentRow($project, 'acceptance', 'Hồ sơ nghiệm thu', $project->acceptance->report_file, $project->acceptance->accepted_at, null, $project->acceptance->note));
            }
        }

        $documents = $documents
            ->when($keyword !== '', function (Collection $items) use ($keyword): Collection {
                $needle = Str::lower($keyword);
                return $items->filter(fn (array $item): bool => Str::contains(Str::lower(implode(' ', [
                    $item['project_code'], $item['project_name'], $item['address'], $item['label'], $item['note'],
                ])), $needle));
            })
            ->when($kind !== '', fn (Collection $items): Collection => $items->where('kind', $kind))
            ->sortByDesc('sort_date')
            ->values();

        $summary = [
            'total' => $documents->count(),
            'survey' => $documents->where('kind', 'survey')->count(),
            'design' => $documents->where('kind', 'design')->count(),
            'daily_log' => $documents->where('kind', 'daily_log')->count(),
            'acceptance' => $documents->where('kind', 'acceptance')->count(),
        ];

        $documentTitle = match ($kind) {
            'survey' => 'Khảo sát & hình ảnh',
            'design' => 'File 3D/CAD',
            'daily_log' => 'Nhật ký thi công',
            'acceptance' => 'Hồ sơ nghiệm thu',
            default => 'Hồ sơ / Biên bản',
        };

        return view('technical.workspace.documents', compact('documents', 'summary', 'documentTitle'));
    }

    private function projectStage(string $stage, array $extra = []): RedirectResponse
    {
        return redirect()->route('project-test.index', array_merge(['workspace_stage' => $stage], $extra));
    }

    private function renderReport(Request $request, string $mode): View
    {
        return view('technical.workspace.report', $this->reportContext($request, $mode));
    }

    /** @return array<string, mixed> */
    private function reportContext(Request $request, string $mode): array
    {
        $user = $request->user();
        $range = $this->dateRange($request);
        $teamIds = $this->technicalTeamIds($user);
        $projectIds = $this->visibleProjectQuery($user)->pluck('id');
        $projectQuery = $this->visibleProjectQuery($user);
        $projectsInRange = (clone $projectQuery)->whereBetween('created_at', [$range['from'], $range['to']]);

        $stats = [
            'projects_total' => (clone $projectsInRange)->count(),
            'projects_running' => (clone $projectQuery)->whereIn('status', self::ACTIVE_PROJECT_STATUSES)->count(),
            'surveys_done' => $this->countScoped('project_test_surveys', function (Builder $query) use ($projectIds, $range): void {
                $query->whereIn('project_id', $projectIds->all() ?: [0])
                    ->whereNotNull('completed_at')
                    ->whereBetween('completed_at', [$range['from'], $range['to']]);
            }),
            'logs_count' => $this->countScoped('project_test_daily_logs', function (Builder $query) use ($projectIds, $range): void {
                $query->whereIn('project_id', $projectIds->all() ?: [0])
                    ->whereBetween('log_date', [$range['from']->toDateString(), $range['to']->toDateString()]);
            }),
            'acceptances' => $this->countScoped('project_test_acceptances', function (Builder $query) use ($projectIds, $range): void {
                $query->whereIn('project_id', $projectIds->all() ?: [0])
                    ->whereBetween('accepted_at', [$range['from']->toDateString(), $range['to']->toDateString()]);
            }),
            'warranties_active' => $this->countScoped('project_test_warranties', function (Builder $query) use ($projectIds): void {
                $query->whereIn('project_id', $projectIds->all() ?: [0])->where('status', 'active');
            }),
            'tasks_overdue' => $this->technicalTaskQuery($user, $teamIds)
                ->whereNotIn('status', ['approved', 'completed', 'done', 'cancelled'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];

        return [
            'stats' => $stats,
            'performance' => $this->buildPerformance($user, $teamIds, $range),
            'recentLogs' => $this->recentLogs($projectIds, 20),
            'range' => $range,
            'mode' => $mode,
            'projects' => $this->progressProjects($user, 35),
            'overdueTasks' => $this->overdueTasks($user, $teamIds, 40),
            'incidents' => $this->incidentRows($projectIds, $teamIds, $range, 40),
        ];
    }

    private function safeReportMode(string $mode): string
    {
        return in_array($mode, ['daily', 'progress', 'performance', 'overdue', 'incidents'], true)
            ? $mode
            : 'progress';
    }

    /** @return array<string, mixed> */
    private function reportExportPayload(Request $request, string $mode): array
    {
        if ($mode === 'daily') {
            return $this->dailyReportExportPayload($request);
        }

        $context = $this->reportContext($request, $mode);
        $range = $context['range'];
        $stats = $context['stats'];
        $titles = [
            'progress' => 'Tiến độ công trình',
            'performance' => 'Hiệu suất nhân sự Kỹ thuật',
            'overdue' => 'Công việc Kỹ thuật quá hạn',
            'incidents' => 'Phát sinh và sự cố Kỹ thuật',
        ];
        $columns = [];
        $rows = [];

        if ($mode === 'performance') {
            $columns = ['Nhân sự', 'Công trình', 'Nhật ký', 'Việc hoàn tất', 'Tổng việc', 'Quá hạn', 'Hiệu suất (%)'];
            $rows = $context['performance']->map(fn (array $member): array => [
                $member['name'],
                (int) $member['projects'],
                (int) $member['logs'],
                (int) $member['tasks_approved'],
                (int) $member['tasks_total'],
                (int) $member['overdue'],
                (float) $member['completion_rate'],
            ])->values()->all();
        } elseif ($mode === 'overdue') {
            $columns = ['Công việc', 'Công trình', 'Người thực hiện', 'Ưu tiên', 'Tiến độ (%)', 'Deadline', 'Trễ'];
            $rows = $context['overdueTasks']->map(function ($task): array {
                $dueAt = $task->due_at ? Carbon::parse($task->due_at) : null;
                return [
                    (string) $task->title,
                    trim(((string) ($task->project_code ?? '')).' '.((string) ($task->project_name ?? ''))) ?: '—',
                    (string) ($task->assignee_name ?: 'Chưa phân công'),
                    str_replace('_', ' ', (string) $task->priority),
                    (int) $task->progress_percent,
                    $dueAt?->format('d/m/Y H:i') ?: '—',
                    $dueAt ? $dueAt->diffForHumans(now(), true) : '—',
                ];
            })->values()->all();
        } elseif ($mode === 'incidents') {
            $columns = ['Thời gian', 'Nguồn', 'Công trình/Công việc', 'Người báo cáo', 'Nội dung', 'Trạng thái'];
            $rows = $context['incidents']->map(fn (array $incident): array => [
                ! empty($incident['occurred_at']) ? Carbon::parse($incident['occurred_at'])->format('d/m/Y H:i') : '—',
                (string) $incident['source'],
                trim(((string) ($incident['project_code'] ?? '')).' '.((string) ($incident['project_name'] ?? ''))) ?: 'Không rõ',
                (string) ($incident['reporter_name'] ?: 'Không rõ'),
                (string) $incident['description'],
                str_replace('_', ' ', (string) $incident['status']),
            ])->values()->all();
        } else {
            $statusLabels = \App\Http\Controllers\Projects\ProjectTestController::STATUSES;
            $columns = ['Mã', 'Công trình', 'Địa chỉ', 'Giai đoạn', 'Tiến độ (%)', 'Phụ trách', 'Mốc gần nhất', 'Cập nhật'];
            $rows = $context['projects']->map(function (Project $project) use ($statusLabels): array {
                $status = $statusLabels[$project->status] ?? ['label' => str_replace('_', ' ', (string) $project->status)];
                $milestone = $project->proposed_installation_at ?: $project->proposed_survey_at ?: $project->target_completion_at;
                return [
                    (string) $project->code,
                    (string) $project->name,
                    (string) ($project->address ?: 'Chưa có địa chỉ'),
                    (string) $status['label'],
                    (int) $project->progress,
                    (string) ($project->leadTechnician?->name ?: $project->salesUser?->name ?: 'Chưa phân công'),
                    $milestone ? Carbon::parse($milestone)->format('d/m/Y H:i') : 'Chưa có lịch',
                    $project->updated_at?->format('d/m/Y H:i') ?: '—',
                ];
            })->values()->all();
        }

        return [
            'mode' => $mode,
            'title' => $titles[$mode] ?? 'Báo cáo Kỹ thuật',
            'range_label' => $range['label'],
            'period_from' => $range['from']->toDateString(),
            'period_to' => $range['to']->toDateString(),
            'filters' => [
                'from' => $range['from']->toDateString(),
                'to' => $range['to']->toDateString(),
            ],
            'summary' => [
                'Công trình phát sinh' => (int) $stats['projects_total'],
                'Công trình đang chạy' => (int) $stats['projects_running'],
                'Khảo sát hoàn tất' => (int) $stats['surveys_done'],
                'Nhật ký thi công' => (int) $stats['logs_count'],
                'Nghiệm thu' => (int) $stats['acceptances'],
                'Bảo hành đang hiệu lực' => (int) $stats['warranties_active'],
                'Việc quá hạn' => (int) $stats['tasks_overdue'],
                'Dòng chi tiết' => count($rows),
            ],
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function dailyReportExportPayload(Request $request): array
    {
        $date = $this->safeDate((string) $request->input('date')) ?? today();
        $rows = $this->dailyOperationRows($request, $date);
        $summary = $this->dailySummary($rows);
        $sourceSummary = $this->dailySourceSummary($rows);
        $statusLabels = [
            'office' => 'Tại văn phòng',
            'installation' => 'Đi thi công',
            'maintenance' => 'Đi bảo trì',
            'survey' => 'Đi khảo sát',
            'field_work' => 'Công tác ngoài',
            'leave' => 'Nghỉ phép',
            'absent' => 'Cần xác minh',
            'unplanned' => 'Chưa có kế hoạch',
        ];

        $detailRows = $rows->map(function (array $row) use ($statusLabels): array {
            $events = $row['events']->map(function ($event): string {
                $type = [
                    'survey' => 'Khảo sát',
                    'installation' => 'Thi công',
                    'maintenance' => 'Bảo trì',
                ][$event->event_type] ?? (string) $event->event_type;
                return trim(($event->starts_at?->format('H:i') ?: '').' · '.$type.' · '.$event->title, ' ·');
            })->join("\n");
            $attendance = $row['attendance'];
            $attendanceText = 'Chưa có dữ liệu';
            if ($attendance) {
                $checkIn = $attendance->check_in_at ? Carbon::parse($attendance->check_in_at)->format('H:i:s') : '—';
                $checkOut = $attendance->check_out_at ? Carbon::parse($attendance->check_out_at)->format('H:i:s') : 'Chưa ra';
                $attendanceText = $checkIn.' → '.$checkOut;
            }
            $check = $row['has_conflict']
                ? 'Trùng giờ'
                : ($row['needs_verification'] ? 'Cần xác nhận' : ($row['status'] === 'unplanned' ? 'Chưa đến ngày' : 'Hợp lệ'));

            return [
                (int) $row['user_id'],
                (string) $row['name'],
                $statusLabels[$row['status']] ?? (string) $row['status'],
                $events ?: 'Không có lịch ngoài công trình',
                (string) ($row['primary_event']?->address ?: '—'),
                $attendanceText,
                $check,
            ];
        })->values()->all();

        return [
            'mode' => 'daily',
            'title' => 'Báo cáo ngày Kỹ thuật '.$date->format('d/m/Y'),
            'range_label' => $date->format('d/m/Y'),
            'period_from' => $date->toDateString(),
            'period_to' => $date->toDateString(),
            'filters' => ['date' => $date->toDateString()],
            'summary' => [
                'Tổng Kỹ thuật' => (int) $summary['total'],
                'Có mặt' => (int) $summary['present'],
                'Tại văn phòng' => (int) $summary['office'],
                'Thi công' => (int) $summary['installation'],
                'Bảo trì' => (int) $summary['maintenance'],
                'Khảo sát' => (int) $summary['survey'],
                'Nghỉ phép' => (int) $summary['leave'],
                'Vắng/Cần xác minh' => (int) ($summary['absent'] + $summary['unconfirmed']),
                'Trùng lịch' => (int) $summary['conflicts'],
                'Nguồn chấm công' => (int) $sourceSummary['attendance'],
                'Nguồn lịch công trình' => (int) $sourceSummary['schedules'],
            ],
            'columns' => ['ID', 'Nhân sự', 'Trạng thái', 'Phân công trong ngày', 'Địa điểm', 'Chấm công', 'Kiểm tra'],
            'rows' => $detailRows,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function streamReportWorkbook(array $payload, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($payload): void {
            $spreadsheet = new Spreadsheet;
            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Tổng quan');
            $this->setSpreadsheetValue($summarySheet, 'A1', (string) $payload['title']);
            $summarySheet->mergeCells('A1:D1');
            $summarySheet->setCellValue('A2', 'Kỳ báo cáo');
            $this->setSpreadsheetValue($summarySheet, 'B2', (string) $payload['range_label']);
            $summarySheet->setCellValue('A3', 'Xuất lúc');
            $summarySheet->setCellValue('B3', now()->format('d/m/Y H:i:s'));
            $summarySheet->setCellValue('A5', 'Chỉ số');
            $summarySheet->setCellValue('B5', 'Giá trị');
            $rowNumber = 6;
            foreach ($payload['summary'] as $label => $value) {
                $this->setSpreadsheetValue($summarySheet, 'A'.$rowNumber, (string) $label);
                $this->setSpreadsheetValue($summarySheet, 'B'.$rowNumber, $value);
                $rowNumber++;
            }
            $summarySheet->getStyle('A1:D1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
            $summarySheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF087F8C');
            $summarySheet->getStyle('A5:B5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $summarySheet->getStyle('A5:B5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B63CE');
            $summarySheet->getColumnDimension('A')->setWidth(34);
            $summarySheet->getColumnDimension('B')->setWidth(24);

            $detailSheet = $spreadsheet->createSheet();
            $detailSheet->setTitle('Chi tiết');
            foreach ($payload['columns'] as $index => $column) {
                $this->setSpreadsheetValue($detailSheet, Coordinate::stringFromColumnIndex($index + 1).'1', (string) $column);
            }
            foreach ($payload['rows'] as $rowIndex => $values) {
                foreach (array_values($values) as $columnIndex => $value) {
                    $this->setSpreadsheetValue(
                        $detailSheet,
                        Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 2),
                        $value,
                    );
                }
            }
            $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($payload['columns'])));
            $lastRow = max(1, count($payload['rows']) + 1);
            $detailSheet->freezePane('A2');
            $detailSheet->setAutoFilter('A1:'.$lastColumn.'1');
            $detailSheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $detailSheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B63CE');
            $detailSheet->getStyle('A1:'.$lastColumn.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9E2EC');
            $detailSheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            for ($column = 1; $column <= count($payload['columns']); $column++) {
                $detailSheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($column <= 2 ? 24 : 20);
            }
            $detailSheet->getRowDimension(1)->setRowHeight(28);

            $spreadsheet->setActiveSheetIndex(0);
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-store, no-cache, must-revalidate',
        ]);
    }

    private function setSpreadsheetValue(Worksheet $sheet, string $coordinate, mixed $value): void
    {
        if (is_int($value) || is_float($value)) {
            $sheet->setCellValue($coordinate, $value);
            return;
        }

        $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
    }

    /** @param array<string, mixed> $payload */
    private function notifyReportSaved(Request $request, int $snapshotId, string $reportCode, array $payload): int
    {
        if (! Schema::hasTable('task_notifications')) {
            return 0;
        }

        $user = $request->user();
        $recipientIds = User::query()
            ->whereHas('roles', function (EloquentBuilder $builder): void {
                $builder->whereIn('name', [
                    'admin', 'management', 'manager',
                    'technical_manager', 'technical_leader', 'truong_phong_ky_thuat',
                ])->where('guard_name', 'web');
            })
            ->when(Schema::hasColumn('users', 'is_active'), fn (EloquentBuilder $builder) => $builder->where('is_active', true));

        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && Schema::hasColumn('users', 'company_id')) {
            $recipientIds->where(function (EloquentBuilder $builder) use ($companyId): void {
                $builder->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        $ids = $recipientIds->pluck('id')->map(fn ($id): int => (int) $id);
        if ($user) {
            $ids->push((int) $user->id);
        }
        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return 0;
        }

        $link = Route::has('technical-workspace.reports.history.show')
            ? route('technical-workspace.reports.history.show', $snapshotId)
            : url('/ky-thuat/dieu-hanh/bao-cao-ky-thuat/lich-su/'.$snapshotId);
        $now = now();
        $rows = $ids->map(fn (int $userId): array => [
            'task_id' => null,
            'user_id' => $userId,
            'created_by' => $user?->id,
            'type' => 'technical_report_saved',
            'title' => 'Báo cáo Kỹ thuật mới: '.$reportCode,
            'message' => ($user?->name ?: 'Hệ thống').' đã lưu '.$payload['title'].' ('.$payload['range_label'].').',
            'link' => $link,
            'is_read' => false,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('task_notifications')->insert($chunk);
        }

        return count($rows);
    }

    private function scopeReportSnapshotQuery(Builder $query, ?User $user): void
    {
        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && Schema::hasColumn('technical_report_snapshots', 'company_id')) {
            $query->where(function (Builder $builder) use ($companyId): void {
                $builder->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }
        if (! $this->isManager($user)) {
            $query->where('generated_by', $user?->id ?? 0);
        }
    }

    private function visibleReportSnapshot(Request $request, int $snapshot): object
    {
        abort_unless(Schema::hasTable('technical_report_snapshots'), 404);
        $query = DB::table('technical_report_snapshots')->where('id', $snapshot);
        $this->scopeReportSnapshotQuery($query, $request->user());
        $row = $query->first();
        abort_unless($row, 404);

        return $row;
    }

    /** @return array<string, mixed> */
    private function snapshotPayload(object $row): array
    {
        return [
            'mode' => (string) $row->report_type,
            'title' => (string) $row->title,
            'range_label' => $this->snapshotRangeLabel($row),
            'period_from' => $row->period_from,
            'period_to' => $row->period_to,
            'filters' => json_decode((string) ($row->filters ?: '{}'), true) ?: [],
            'summary' => json_decode((string) ($row->summary ?: '{}'), true) ?: [],
            'columns' => json_decode((string) ($row->columns ?: '[]'), true) ?: [],
            'rows' => json_decode((string) ($row->rows ?: '[]'), true) ?: [],
        ];
    }

    private function snapshotRangeLabel(object $row): string
    {
        $from = $this->safeDate((string) $row->period_from);
        $to = $this->safeDate((string) $row->period_to);
        if ($from && $to && $from->isSameDay($to)) {
            return $from->format('d/m/Y');
        }

        return ($from?->format('d/m/Y') ?: '—').' - '.($to?->format('d/m/Y') ?: '—');
    }

    private function renderScheduleCalendar(Request $request, string $type): View
    {
        $month = $this->safeDate($request->query('month')) ?? today();
        $from = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $to = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $events = $this->scheduleEvents($request, $from, $to, [$type]);
        $keyword = Str::lower(trim((string) $request->query('q')));
        $teamIds = $this->technicalTeamIds($request->user());
        $teamMembers = User::query()->whereIn('id', $teamIds)->orderBy('name')->get(['id', 'name']);
        $selectedUserId = (int) $request->query('user_id', 0);
        if ($selectedUserId > 0 && ! $teamIds->contains($selectedUserId)) {
            $selectedUserId = 0;
        }
        if ($selectedUserId > 0) {
            $events = $events->filter(
                fn ($event): bool => $event->participants->contains(
                    fn ($participant): bool => (int) $participant->user_id === $selectedUserId
                )
            )->values();
        }
        if ($keyword !== '') {
            $events = $events->filter(function ($event) use ($keyword): bool {
                $haystack = Str::lower(implode(' ', [
                    (string) $event->title,
                    (string) $event->address,
                    (string) ($event->project?->code ?? ''),
                    (string) ($event->project?->name ?? ''),
                    $event->participants->pluck('user.name')->filter()->join(' '),
                ]));
                return Str::contains($haystack, $keyword);
            })->values();
        }
        $days = collect();
        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $days->push($cursor->copy());
        }
        $eventsByDate = $events->groupBy(fn ($event): string => Carbon::parse($event->starts_at)->toDateString());
        $calendarMode = $type;
        $completedStatuses = ['completed', 'done', 'approved', 'finished'];
        $summary = [
            'total' => $events->count(),
            'today' => $events->filter(fn ($event): bool => Carbon::parse($event->starts_at)->isToday())->count(),
            'upcoming' => $events->filter(fn ($event): bool => Carbon::parse($event->starts_at)->isFuture())->count(),
            'unassigned' => $events->filter(fn ($event): bool => $event->participants->isEmpty())->count(),
            'completed' => $events->whereIn('status', $completedStatuses)->count(),
            'material_alerts' => $type === 'installation'
                ? $events->filter(fn ($event): bool => in_array($event->material_status, ['shortage', 'waiting_import', 'partial'], true))->count()
                : 0,
        ];
        $agendaEvents = $events->sortBy('starts_at')->values();

        foreach ($agendaEvents as $event) {
            $event->workspace_url = null;
            if ($event->source_type === 'maintenance' && Route::has('ky-thuat.maintenance.show')) {
                $event->workspace_url = route('ky-thuat.maintenance.show', $event->source_id);
            } elseif ($event->project_id && Route::has('project-test.show')) {
                $event->workspace_url = route('project-test.show', $event->project_id);
            }
        }

        return view('technical.workspace.calendar', compact(
            'month', 'from', 'to', 'days', 'events', 'eventsByDate', 'calendarMode',
            'teamMembers', 'selectedUserId', 'keyword', 'summary', 'agendaEvents'
        ));
    }

    private function scheduleEvents(
        Request $request,
        Carbon $from,
        Carbon $to,
        ?array $types = null,
    ): Collection {
        if (! Schema::hasTable('technical_schedule_events')) {
            return collect();
        }

        $query = TechnicalScheduleEvent::query()
            ->between($from, $to)
            ->with(['participants.user:id,name', 'project:id,code,name,status']);

        /*
         * EGO_TECHNICAL_CALENDAR_CROSS_COMPANY_V1
         *
         * Nhân sự Kỹ thuật/Tech Manager xem lịch kỹ thuật của tất cả
         * công ty. Các phòng ban khác vẫn giữ company scope.
         */
        $companyId = EgoCompanyScope::currentId();

        $technicalCrossCompany =
            \App\Support\SolarMaintenanceAccess::isTechnician(
                $request->user()
            )
            || \App\Support\SolarMaintenanceAccess::isManager(
                $request->user()
            );

        if (
            ! $technicalCrossCompany
            && $companyId > 0
            && Schema::hasColumn(
                'technical_schedule_events',
                'company_id'
            )
        ) {
            $query->where(
                function (
                    EloquentBuilder $builder
                ) use ($companyId): void {
                    $builder
                        ->where(
                            'company_id',
                            $companyId
                        )
                        ->orWhereNull(
                            'company_id'
                        );
                }
            );
        }

        if ($types) {
            $query->whereIn('event_type', $types);
        }

        if (! $this->isManager($request->user())) {
            $query->whereHas('participants', fn (EloquentBuilder $builder) => $builder->where('user_id', $request->user()->id));
        }

        $events = $query->orderBy('starts_at')->get();
        $projectIds = $events->pluck('project_id')->filter()->unique()->values();
        $materialStatusByProject = collect();
        if (Schema::hasTable('project_test_material_requests') && $projectIds->isNotEmpty()) {
            $materialStatusByProject = DB::table('project_test_material_requests')
                ->whereIn('project_id', $projectIds)
                ->orderByDesc('id')
                ->get(['project_id', 'status'])
                ->unique('project_id')
                ->pluck('status', 'project_id');
        }

        return $events->map(function ($event) use ($materialStatusByProject) {
            $event->material_status = $event->project_id ? $materialStatusByProject->get($event->project_id) : null;
            return $event;
        });
    }

    private function dailyOperationRows(Request $request, Carbon $date): Collection
    {
        $teamIds = $this->technicalTeamIds($request->user());
        $members = User::query()->whereIn('id', $teamIds)->orderBy('name')->get(['id', 'name', 'department_id']);
        $events = $this->scheduleEvents($request, $date->copy()->startOfDay(), $date->copy()->endOfDay());

        $eventsByUser = collect();
        foreach ($events as $event) {
            foreach ($event->participants as $participant) {
                $eventsByUser->push(['user_id' => (int) $participant->user_id, 'event' => $event]);
            }
        }
        $eventsByUser = $eventsByUser->groupBy('user_id')->map(fn (Collection $items): Collection => $items->pluck('event'));

        $attendance = Schema::hasTable('attendance_records')
            ? DB::table('attendance_records')->whereIn('user_id', $teamIds)->whereDate('work_date', $date->toDateString())->get()->keyBy('user_id')
            : collect();

        $leaves = Schema::hasTable('leave_requests')
            ? DB::table('leave_requests')->whereIn('user_id', $teamIds)->where('status', 'approved')
                ->whereDate('start_date', '<=', $date->toDateString())
                ->whereDate('end_date', '>=', $date->toDateString())->get()->keyBy('user_id')
            : collect();

        $isFutureDate = $date->copy()->startOfDay()->gt(today());

        return $members->map(function (User $member) use ($eventsByUser, $attendance, $leaves, $isFutureDate): array {
            $memberEvents = $eventsByUser->get((int) $member->id, collect());
            $record = $attendance->get((int) $member->id);
            $leave = $leaves->get((int) $member->id);
            $primary = $memberEvents->sortBy(function ($event): int {
                return match ($event->event_type) {
                    'installation' => 1,
                    'maintenance' => 2,
                    'survey' => 3,
                    default => 9,
                };
            })->first();

            $status = $isFutureDate ? 'unplanned' : 'absent';
            $needsVerification = ! $isFutureDate;
            if ($leave) {
                $status = 'leave';
                $needsVerification = false;
            } elseif ($primary) {
                $status = match ($primary->event_type) {
                    'installation' => 'installation',
                    'maintenance' => 'maintenance',
                    'survey' => 'survey',
                    default => 'field_work',
                };
                $needsVerification = ! $isFutureDate
                    && ! $record
                    && ! in_array($primary->status, ['in_progress', 'completed'], true);
            } elseif ($record) {
                $status = 'office';
                $needsVerification = false;
            }

            $hasConflict = $this->hasScheduleConflict($memberEvents);

            return [
                'user_id' => (int) $member->id,
                'name' => $member->name,
                'status' => $status,
                'events' => $memberEvents,
                'primary_event' => $primary,
                'attendance' => $record,
                'leave' => $leave,
                'needs_verification' => $needsVerification,
                'has_conflict' => $hasConflict,
            ];
        });
    }

    private function dailySummary(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'present' => $rows->whereIn('status', ['office', 'installation', 'maintenance', 'survey', 'field_work'])->count(),
            'office' => $rows->where('status', 'office')->count(),
            'installation' => $rows->where('status', 'installation')->count(),
            'maintenance' => $rows->where('status', 'maintenance')->count(),
            'survey' => $rows->where('status', 'survey')->count(),
            'leave' => $rows->where('status', 'leave')->count(),
            'unconfirmed' => $rows->where('needs_verification', true)
                ->where('status', '!=', 'absent')->count(),
            'absent' => $rows->where('status', 'absent')->count(),
            'unplanned' => $rows->where('status', 'unplanned')->count(),
            'conflicts' => $rows->where('has_conflict', true)->count(),
        ];
    }

    private function dailySourceSummary(Collection $rows): array
    {
        $events = $rows->pluck('events')->flatten(1)->filter()->unique('id');

        return [
            'attendance' => $rows->filter(fn (array $row): bool => (bool) $row['attendance'])->count(),
            'schedules' => $events->count(),
            'installation' => $events->where('event_type', 'installation')->count(),
            'maintenance' => $events->where('event_type', 'maintenance')->count(),
            'survey' => $events->where('event_type', 'survey')->count(),
            'leave' => $rows->filter(fn (array $row): bool => (bool) $row['leave'])->count(),
        ];
    }

    private function hasScheduleConflict(Collection $events): bool
    {
        $ordered = $events->filter(fn ($event): bool => (bool) $event->starts_at)
            ->sortBy(fn ($event): int => Carbon::parse($event->starts_at)->timestamp)
            ->values();

        for ($index = 0; $index < $ordered->count(); $index++) {
            $current = $ordered[$index];
            $currentStart = Carbon::parse($current->starts_at);
            $currentEnd = $current->ends_at
                ? Carbon::parse($current->ends_at)
                : $currentStart->copy()->addMinute();

            for ($nextIndex = $index + 1; $nextIndex < $ordered->count(); $nextIndex++) {
                $next = $ordered[$nextIndex];
                $nextStart = Carbon::parse($next->starts_at);
                if ($nextStart->gte($currentEnd)) {
                    break;
                }

                $nextEnd = $next->ends_at
                    ? Carbon::parse($next->ends_at)
                    : $nextStart->copy()->addMinute();
                if ($currentStart->lt($nextEnd) && $nextStart->lt($currentEnd)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function scheduleConflicts(Collection $events): Collection
    {
        $rows = collect();
        foreach ($events as $event) {
            foreach ($event->participants as $participant) {
                $rows->push([
                    'user_id' => (int) $participant->user_id,
                    'user_name' => $participant->user?->name ?: 'Không rõ',
                    'event' => $event,
                ]);
            }
        }

        $conflicts = collect();
        foreach ($rows->groupBy('user_id') as $items) {
            $ordered = $items->sortBy(
                fn (array $item): int => Carbon::parse($item['event']->starts_at)->timestamp
            )->values();

            for ($index = 0; $index < $ordered->count(); $index++) {
                $current = $ordered[$index]['event'];
                $currentStart = Carbon::parse($current->starts_at);
                $currentEnd = $current->ends_at
                    ? Carbon::parse($current->ends_at)
                    : $currentStart->copy()->addMinute();

                for ($nextIndex = $index + 1; $nextIndex < $ordered->count(); $nextIndex++) {
                    $next = $ordered[$nextIndex]['event'];
                    $nextStart = Carbon::parse($next->starts_at);
                    if ($nextStart->gte($currentEnd)) {
                        break;
                    }

                    $nextEnd = $next->ends_at
                        ? Carbon::parse($next->ends_at)
                        : $nextStart->copy()->addMinute();
                    if (! $currentStart->lt($nextEnd) || ! $nextStart->lt($currentEnd)) {
                        continue;
                    }

                    $conflicts->push([
                        'user_id' => (int) $ordered[$index]['user_id'],
                        'user_name' => $ordered[$index]['user_name'],
                        'date' => $currentStart->toDateString(),
                        'starts_at' => $nextStart->gt($currentStart) ? $nextStart->copy() : $currentStart->copy(),
                        'events' => collect([$current, $next]),
                        'event_ids' => [(int) $current->id, (int) $next->id],
                    ]);
                }
            }
        }

        return $conflicts->unique(
            fn (array $conflict): string => $conflict['user_id'].'|'.collect($conflict['event_ids'])->sort()->join('-')
        )->values();
    }

    private function renderWarrantyClaims(Request $request, bool $replacementOnly): View
    {
        $keyword = trim((string) $request->query('q'));
        $status = trim((string) $request->query('status'));

        if (! Schema::hasTable('crm_serial_warranty_claims')) {
            return view('technical.workspace.warranty-issues', [
                'claims' => $this->emptyPaginator($request),
                'summary' => ['open' => 0, 'processing' => 0, 'resolved' => 0, 'cost' => 0],
                'replacementOnly' => $replacementOnly,
            ]);
        }

        $base = DB::table('crm_serial_warranty_claims as cl')
            ->leftJoin('crm_serial_units as su', 'su.id', '=', 'cl.serial_unit_id')
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'su.product_id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'cl.customer_id')
            ->leftJoin('crm_orders as o', 'o.id', '=', 'cl.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'cl.created_by')
            ->select([
                'cl.id', 'cl.serial_unit_id', 'cl.serial_code', 'cl.customer_id', 'cl.order_id',
                'cl.status', 'cl.received_at', 'cl.resolved_at', 'cl.issue_description',
                'cl.resolution', 'cl.cost', 'cl.created_at', 'p.name as product_name',
                'c.name as customer_name', 'o.order_code', 'u.name as creator_name',
            ]);

        if ($replacementOnly) {
            $base->whereNotIn('cl.status', ['resolved', 'completed', 'closed', 'rejected', 'cancelled'])
                ->where(function (Builder $query): void {
                    $query->whereIn('cl.status', [
                        'pending_replacement', 'replacement_pending', 'approved_replacement',
                        'waiting_device', 'processing', 'received',
                    ])->orWhere('cl.resolution', 'like', '%đổi%')
                        ->orWhere('cl.resolution', 'like', '%thay%')
                        ->orWhere('cl.issue_description', 'like', '%đổi%')
                        ->orWhere('cl.issue_description', 'like', '%thay%');
                });
        }

        if ($keyword !== '') {
            $base->where(function (Builder $builder) use ($keyword): void {
                $builder->where('cl.serial_code', 'like', "%{$keyword}%")
                    ->orWhere('p.name', 'like', "%{$keyword}%")
                    ->orWhere('c.name', 'like', "%{$keyword}%")
                    ->orWhere('o.order_code', 'like', "%{$keyword}%")
                    ->orWhere('cl.issue_description', 'like', "%{$keyword}%");
            });
        }

        if ($status !== '') {
            $base->where('cl.status', $status);
        }

        $claims = $base->orderByDesc('cl.id')->paginate(25)->withQueryString();
        $summaryBase = DB::table('crm_serial_warranty_claims');
        $summary = [
            'open' => (clone $summaryBase)->whereNotIn('status', ['resolved', 'completed', 'closed', 'rejected', 'cancelled'])->count(),
            'processing' => (clone $summaryBase)->whereIn('status', ['processing', 'pending_replacement', 'replacement_pending', 'approved_replacement'])->count(),
            'resolved' => (clone $summaryBase)->whereIn('status', ['resolved', 'completed', 'closed'])->count(),
            'cost' => (float) (clone $summaryBase)->sum('cost'),
        ];

        return view('technical.workspace.warranty-issues', compact('claims', 'summary', 'replacementOnly'));
    }

    private function progressProjects(User $user, int $limit): Collection
    {
        return $this->visibleProjectQuery($user)
            ->whereNotIn('status', ['cancelled'])
            ->with(['leadTechnician:id,name', 'salesUser:id,name'])
            ->orderByRaw("CASE WHEN status IN ('completed','warranty_active') THEN 1 ELSE 0 END")
            ->orderBy('progress')
            ->limit($limit)
            ->get([
                'id', 'code', 'name', 'address', 'status', 'progress', 'lead_technician_id', 'sales_user_id',
                'proposed_survey_at', 'proposed_installation_at', 'target_completion_at', 'updated_at',
            ]);
    }

    private function overdueTasks(User $user, Collection $teamIds, int $limit): Collection
    {
        if (! Schema::hasTable('tasks')) {
            return collect();
        }

        $query = $this->technicalTaskQuery($user, $teamIds)
            ->leftJoin('users as assignee', 'assignee.id', '=', 'tasks.assignee_id')
            ->whereNotIn('tasks.status', ['approved', 'completed', 'done', 'cancelled'])
            ->whereNotNull('tasks.due_at')
            ->where('tasks.due_at', '<', now());

        if (Schema::hasColumn('tasks', 'project_id') && Schema::hasTable('project_test_projects')) {
            $query->leftJoin('project_test_projects as p', 'p.id', '=', 'tasks.project_id');
        }

        $select = [
            'tasks.id', 'tasks.title', 'tasks.status', 'tasks.priority', 'tasks.progress_percent',
            'tasks.due_at', 'tasks.assignee_id', 'assignee.name as assignee_name',
        ];
        if (Schema::hasColumn('tasks', 'project_id')) {
            $select[] = 'tasks.project_id';
            if (Schema::hasTable('project_test_projects')) {
                $select[] = 'p.code as project_code';
                $select[] = 'p.name as project_name';
            }
        }

        return $query->orderBy('tasks.due_at')->limit($limit)->get($select);
    }

    private function incidentRows(Collection $projectIds, Collection $teamIds, array $range, int $limit): Collection
    {
        $rows = collect();

        if (Schema::hasTable('project_test_daily_logs') && Schema::hasTable('project_test_projects')) {
            $logs = DB::table('project_test_daily_logs as l')
                ->join('project_test_projects as p', 'p.id', '=', 'l.project_id')
                ->leftJoin('users as u', 'u.id', '=', 'l.created_by')
                ->whereIn('l.project_id', $projectIds->all() ?: [0])
                ->whereBetween('l.log_date', [$range['from']->toDateString(), $range['to']->toDateString()])
                ->where(function (Builder $query): void {
                    $query->where('l.content', 'like', '%phát sinh%')
                        ->orWhere('l.content', 'like', '%sự cố%')
                        ->orWhere('l.content', 'like', '%rủi ro%')
                        ->orWhereNotIn('l.status', ['working', 'done', 'completed']);
                })
                ->orderByDesc('l.log_date')
                ->limit($limit)
                ->get([
                    'l.id', 'l.project_id', 'l.log_date as occurred_at', 'l.status',
                    'l.content as description', 'l.attachment_file', 'p.code as project_code',
                    'p.name as project_name', 'u.name as reporter_name',
                ])
                ->map(fn ($row): array => [
                    'source' => 'Nhật ký công trình',
                    'id' => $row->id,
                    'project_id' => $row->project_id,
                    'project_code' => $row->project_code,
                    'project_name' => $row->project_name,
                    'reporter_name' => $row->reporter_name,
                    'occurred_at' => $row->occurred_at,
                    'status' => $row->status,
                    'description' => $row->description,
                    'url' => route('project-test.show', $row->project_id),
                ]);
            $rows = $rows->concat($logs);
        }

        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'issue_description')) {
            $tasks = DB::table('tasks as t')
                ->leftJoin('users as u', 'u.id', '=', 't.assignee_id')
                ->whereIn('t.assignee_id', $teamIds->all() ?: [0])
                ->whereNotNull('t.issue_description')
                ->where('t.issue_description', '<>', '')
                ->whereBetween('t.updated_at', [$range['from'], $range['to']])
                ->orderByDesc('t.updated_at')
                ->limit($limit)
                ->get([
                    't.id', 't.title', 't.status', 't.issue_description', 't.updated_at',
                    'u.name as reporter_name',
                ])
                ->map(fn ($row): array => [
                    'source' => 'Công việc kỹ thuật',
                    'id' => $row->id,
                    'project_id' => null,
                    'project_code' => null,
                    'project_name' => $row->title,
                    'reporter_name' => $row->reporter_name,
                    'occurred_at' => $row->updated_at,
                    'status' => $row->status,
                    'description' => $row->issue_description,
                    'url' => route('tasks.show', $row->id),
                ]);
            $rows = $rows->concat($tasks);
        }

        return $rows->sortByDesc('occurred_at')->take($limit)->values();
    }

    private function visibleProjectQuery(User $user): EloquentBuilder
    {
        $query = Project::query()->visibleTo($user);
        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && Schema::hasColumn('project_test_projects', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    private function dateRange(Request $request): array
    {
        $from = $this->safeDate($request->input('from')) ?? now()->startOfMonth();
        $to = $this->safeDate($request->input('to')) ?? now()->endOfMonth();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from->copy()->startOfDay(),
            'to' => $to->copy()->endOfDay(),
            'label' => $from->format('d/m/Y').' - '.$to->format('d/m/Y'),
        ];
    }

    private function safeDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isManager(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'management', 'manager', 'technical_manager', 'technical_leader', 'truong_phong_ky_thuat'])) {
            return true;
        }

        $position = Str::of((string) ($user->position?->name ?? ''))->ascii()->lower()->value();
        return Str::contains($position, ['truong phong', 'quan ly', 'manager', 'leader']);
    }

    private function technicalTeamIds(User $user): Collection
    {
        // Không lấy theo department_id vì một phòng ban có thể chứa Sales/Marketing/Admin.
        // Chấp nhận các alias role Kỹ thuật đang dùng ở những module cũ và mới.
        if (! $this->isManager($user)) {
            return $user->hasAnyRole(self::TECHNICAL_ROLES)
                ? collect([(int) $user->id])
                : collect();
        }

        $query = User::query()
            ->whereHas('roles', function (EloquentBuilder $builder): void {
                $builder->whereIn('name', self::TECHNICAL_ROLES)
                    ->where('guard_name', 'web');
            })
            ->where('is_active', true);

        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && Schema::hasColumn('users', 'company_id')) {
            $query->where(function (EloquentBuilder $builder) use ($companyId): void {
                $builder->where('company_id', $companyId)
                    ->orWhereNull('company_id');
            });
        }

        return $query->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function technicalTaskQuery(User $user, Collection $teamIds): Builder
    {
        $query = DB::table('tasks');
        if (Schema::hasColumn('tasks', 'company_id') && EgoCompanyScope::currentId() > 0) {
            $query->where('tasks.company_id', EgoCompanyScope::currentId());
        }
        if (Schema::hasColumn('tasks', 'task_type')) {
            $query->where(function (Builder $scope): void {
                $scope->where('tasks.task_type', 'technical')->orWhereNull('tasks.task_type');
            });
        }
        $query->whereIn('tasks.assignee_id', $teamIds->all() ?: [(int) $user->id]);

        return $query;
    }

    private function buildPerformance(User $user, Collection $teamIds, array $range): Collection
    {
        $users = User::query()->whereIn('id', $teamIds)->orderBy('name')->get(['id', 'name']);

        return $users->map(function (User $member) use ($range): array {
            $assignments = Schema::hasTable('project_test_assignments')
                ? DB::table('project_test_assignments')->where('user_id', $member->id)->distinct()->count('project_id')
                : 0;

            $logs = Schema::hasTable('project_test_daily_logs')
                ? DB::table('project_test_daily_logs')
                    ->where('created_by', $member->id)
                    ->whereBetween('log_date', [$range['from']->toDateString(), $range['to']->toDateString()])
                    ->count()
                : 0;

            $tasks = DB::table('tasks')
                ->where('assignee_id', $member->id)
                ->whereBetween('created_at', [$range['from'], $range['to']]);
            $taskTotal = (clone $tasks)->count();
            $taskApproved = (clone $tasks)->whereIn('status', ['approved', 'completed', 'done'])->count();
            $overdue = (clone $tasks)
                ->whereNotIn('status', ['approved', 'completed', 'done', 'cancelled'])
                ->whereNotNull('due_at')->where('due_at', '<', now())->count();

            return [
                'id' => $member->id,
                'name' => $member->name,
                'projects' => $assignments,
                'logs' => $logs,
                'tasks_total' => $taskTotal,
                'tasks_approved' => $taskApproved,
                'overdue' => $overdue,
                'completion_rate' => $taskTotal > 0 ? round($taskApproved / $taskTotal * 100, 1) : 0,
            ];
        })->sortByDesc('completion_rate')->values();
    }

    private function recentLogs(Collection $projectIds, int $limit): Collection
    {
        if (! Schema::hasTable('project_test_daily_logs') || $projectIds->isEmpty()) {
            return collect();
        }

        return DB::table('project_test_daily_logs as l')
            ->join('project_test_projects as p', 'p.id', '=', 'l.project_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.created_by')
            ->whereIn('l.project_id', $projectIds)
            ->orderByDesc('l.log_date')->orderByDesc('l.id')->limit($limit)
            ->get([
                'l.id', 'l.log_date', 'l.progress', 'l.status', 'l.content', 'l.attachment_file',
                'p.id as project_id', 'p.code', 'p.name as project_name', 'u.name as author_name',
            ]);
    }

    private function countScoped(string $table, callable $callback): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $callback($query);
        return (int) $query->count();
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, max(1, (int) $request->query('page', 1)), [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    private function documentRow(
        Project $project,
        string $kind,
        string $label,
        string $path,
        mixed $date,
        ?string $author = null,
        ?string $note = null,
    ): array {
        $sortDate = $this->safeDate(is_string($date) ? $date : (string) $date)?->timestamp ?? 0;

        return [
            'project_id' => $project->id,
            'project_code' => $project->code,
            'project_name' => $project->name,
            'address' => $project->address,
            'kind' => $kind,
            'label' => $label,
            'path' => $path,
            'url' => asset('storage/'.ltrim($path, '/')),
            'date' => $sortDate > 0 ? Carbon::createFromTimestamp($sortDate)->format('d/m/Y') : 'Chưa rõ ngày',
            'sort_date' => $sortDate,
            'author' => $author ?: 'Hệ thống',
            'note' => $note,
            'project_url' => route('project-test.show', $project),
        ];
    }
}
