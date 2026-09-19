<?php

namespace App\Http\Controllers\TechnicalKpi;

use App\Http\Controllers\Controller;
use App\Models\Projects\Site;
use App\Services\TechnicalKpi\ProjectKpiLinkService;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TechnicalProjectKpiController extends Controller
{
    public function __construct(private readonly ProjectKpiLinkService $projectKpi) {}

    public function show(Request $request, Site $site)
    {
        $month = $this->month($request);
        $users = $this->projectKpi->projectUsers((int) $site->id);
        $evidence = $this->projectKpi->evidenceForProject((int) $site->id, $month);
        $canManage = auth()->check() && auth()->user()->hasAnyRole(['admin', 'manager', 'management', 'technical_manager']);

        $signals = [];
        foreach ($users as $user) {
            $projects = $this->projectKpi->projectsForUserMonth((int) $user->id, $month);
            $signals[$user->id] = $projects->firstWhere('site_id', (int) $site->id);
        }

        return view('kythuat.kpi_project', compact('site', 'month', 'users', 'evidence', 'signals', 'canManage'));
    }

    public function save(Request $request, Site $site)
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'manager', 'management', 'technical_manager']), 403);
        abort_unless(SchemaCache::hasTable('technical_kpi_project_evidence'), 503, 'Chưa có bảng technical_kpi_project_evidence.');

        $month = $this->month($request);
        $data = $request->validate([
            'evidence' => ['nullable', 'array'],
            'evidence.*.timeline_excluded' => ['nullable', 'boolean'],
            'evidence.*.timeline_exclusion_reason' => ['nullable', 'string', 'max:1000'],
            'evidence.*.quality_first_pass' => ['nullable', 'in:0,1'],
            'evidence.*.material_waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'evidence.*.hse_pass' => ['nullable', 'in:0,1'],
            'evidence.*.evn_app_required' => ['nullable', 'in:0,1'],
            'evidence.*.evn_app_completed' => ['nullable', 'in:0,1'],
            'evidence.*.penalty_points' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'evidence.*.note' => ['nullable', 'string', 'max:3000'],
        ]);

        $allowedIds = $this->projectKpi->projectUsers((int) $site->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $now = now();

        DB::transaction(function () use ($data, $allowedIds, $site, $month, $now) {
            foreach (($data['evidence'] ?? []) as $userId => $row) {
                $userId = (int) $userId;
                if (! in_array($userId, $allowedIds, true)) {
                    continue;
                }

                DB::table('technical_kpi_project_evidence')->updateOrInsert(
                    ['site_id' => (int) $site->id, 'user_id' => $userId, 'payroll_month' => $month],
                    [
                        'timeline_excluded' => (bool) ($row['timeline_excluded'] ?? false),
                        'timeline_exclusion_reason' => trim((string) ($row['timeline_exclusion_reason'] ?? '')) ?: null,
                        'quality_first_pass' => array_key_exists('quality_first_pass', $row) && $row['quality_first_pass'] !== '' ? (bool) $row['quality_first_pass'] : null,
                        'material_waste_percent' => ($row['material_waste_percent'] ?? '') !== '' ? (float) $row['material_waste_percent'] : null,
                        'hse_pass' => array_key_exists('hse_pass', $row) && $row['hse_pass'] !== '' ? (bool) $row['hse_pass'] : null,
                        'evn_app_required' => array_key_exists('evn_app_required', $row) && $row['evn_app_required'] !== '' ? (bool) $row['evn_app_required'] : null,
                        'evn_app_completed' => array_key_exists('evn_app_completed', $row) && $row['evn_app_completed'] !== '' ? (bool) $row['evn_app_completed'] : null,
                        'penalty_points' => (float) ($row['penalty_points'] ?? 0),
                        'note' => trim((string) ($row['note'] ?? '')) ?: null,
                        'recorded_by' => auth()->id(),
                        'approved_by' => auth()->id(),
                        'approved_at' => $now,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        });

        return back()->with('success', 'Đã cập nhật dữ liệu KPI công trình. Dashboard và kỳ KPI sẽ lấy dữ liệu này khi có nguồn liên kết tương ứng.');
    }

    private function month(Request $request): string
    {
        $month = (string) $request->input('month', $request->query('month', now()->format('Y-m')));

        return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : now()->format('Y-m');
    }
}
