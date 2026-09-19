<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Services\Synced\Technical\SolarMaintenanceQueryService;
use App\Support\Synced\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TechnicalWorkController extends Controller
{
    public function overview(Request $request): View
    {
        return $this->workspace($request, 'overview');
    }

    public function plan(Request $request): View
    {
        return $this->workspace($request, 'plan');
    }

    public function reports(Request $request): View
    {
        return $this->workspace($request, 'report');
    }

    public function completion(Request $request): View
    {
        return $this->workspace($request, 'completion');
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless(Schema::hasTable('technical_work_records'), 503, 'Chưa chạy migration Kỹ thuật.');

        $data = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'work_date' => ['required', 'date'],
            'work_type' => ['required', 'string', 'max:100'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'device_info' => ['nullable', 'string', 'max:10000'],
            'requirement' => ['required', 'string', 'max:20000'],
            'technical_note' => ['nullable', 'string', 'max:10000'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => ['file', 'max:51200'],
        ]);

        $this->assertSiteInScope((int) $data['site_id']);
        $files = $this->storeFiles($request, 'files', 'technical-work/plans');

        DB::table('technical_work_records')->insert([
            'site_id' => (int) $data['site_id'],
            'created_by' => (int) $request->user()->id,
            'work_date' => $data['work_date'],
            'work_type' => trim($data['work_type']),
            'priority' => $data['priority'],
            'device_info' => $data['device_info'] ?? null,
            'requirement' => $data['requirement'],
            'technical_note' => $data['technical_note'] ?? null,
            'assignee_ids' => json_encode(array_values(array_map('intval', $data['assignee_ids'] ?? []))),
            'status' => 'planned',
            'plan_files' => json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('ky-thuat.ke-hoach')->with('success', 'Đã lưu kế hoạch Kỹ thuật.');
    }

    public function saveReport(Request $request, int $record): RedirectResponse
    {
        $this->authorizeAccess($request);
        $row = $this->findRecord($record);

        $data = $request->validate([
            'report_title' => ['required', 'string', 'max:255'],
            'report_date' => ['required', 'date'],
            'result_text' => ['required', 'string', 'max:30000'],
            'report_note' => ['nullable', 'string', 'max:10000'],
            'files' => ['nullable', 'array', 'max:30'],
            'files.*' => ['file', 'max:51200'],
        ]);

        $files = array_merge($this->decodeFiles($row->report_files ?? null), $this->storeFiles($request, 'files', 'technical-work/reports/'.$record));
        DB::table('technical_work_records')->where('id', $record)->update([
            'report_title' => $data['report_title'],
            'report_date' => $data['report_date'],
            'result_text' => $data['result_text'],
            'report_note' => $data['report_note'] ?? null,
            'report_files' => json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'reported',
            'updated_at' => now(),
        ]);

        return redirect()->route('ky-thuat.bao-cao', ['item' => $record])->with('success', 'Đã lưu báo cáo Kỹ thuật.');
    }

    public function saveCompletion(Request $request, int $record): RedirectResponse
    {
        $this->authorizeAccess($request);
        $row = $this->findRecord($record);

        $data = $request->validate([
            'remaining_work' => ['nullable', 'string', 'max:30000'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'completion_report' => ['required', 'string', 'max:30000'],
            'completion_state' => ['required', 'in:completed,continue'],
            'files' => ['nullable', 'array', 'max:30'],
            'files.*' => ['file', 'max:51200'],
        ]);

        $files = array_merge($this->decodeFiles($row->completion_files ?? null), $this->storeFiles($request, 'files', 'technical-work/completion/'.$record));
        DB::table('technical_work_records')->where('id', $record)->update([
            'remaining_work' => $data['remaining_work'] ?? null,
            'assignee_ids' => json_encode(array_values(array_map('intval', $data['assignee_ids'] ?? $this->decodeIds($row->assignee_ids ?? null)))),
            'due_date' => $data['due_date'] ?? null,
            'completion_report' => $data['completion_report'],
            'completion_files' => json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => $data['completion_state'] === 'completed' ? 'completed' : 'reported',
            'completed_at' => $data['completion_state'] === 'completed' ? now() : null,
            'updated_at' => now(),
        ]);

        return redirect()->route('ky-thuat.hoan-thien', ['item' => $record])->with('success', 'Đã lưu phần Hoàn thiện Kỹ thuật.');
    }

    private function workspace(Request $request, string $mode): View
    {
        $this->authorizeAccess($request);
        $records = collect();
        if (Schema::hasTable('technical_work_records')) {
            $query = DB::table('technical_work_records as r')
                ->leftJoin('sites as s', 's.id', '=', 'r.site_id')
                ->leftJoin('users as c', 'c.id', '=', 'r.created_by')
                ->select('r.*', 's.name as site_name', 's.project_code', 's.system_kwp', 'c.name as creator_name');

            $companyId = EgoCompanyScope::currentId();
            if ($companyId > 0 && Schema::hasColumn('sites', 'company_id')) {
                $query->where('s.company_id', $companyId);
            }

            if (SolarMaintenanceAccess::isTechnicianOnly($request->user())) {
                $userId = (int) $request->user()->id;
                $query->where(function ($q) use ($userId): void {
                    $q->where('r.created_by', $userId)
                        ->orWhere('r.assignee_ids', 'like', '%"'.$userId.'"%')
                        ->orWhere('r.assignee_ids', 'like', '%['.$userId.',%')
                        ->orWhere('r.assignee_ids', 'like', '%,'.$userId.',%')
                        ->orWhere('r.assignee_ids', 'like', '%,'.$userId.']%')
                        ->orWhere('r.assignee_ids', '['.$userId.']');
                });
            }

            $keyword = trim((string) $request->query('q', ''));
            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword): void {
                    $q->where('s.name', 'like', '%'.$keyword.'%')
                        ->orWhere('r.work_type', 'like', '%'.$keyword.'%')
                        ->orWhere('r.requirement', 'like', '%'.$keyword.'%')
                        ->orWhere('r.report_title', 'like', '%'.$keyword.'%');
                });
            }

            $records = $query->orderByDesc('r.work_date')->orderByDesc('r.id')->limit(300)->get();
        }

        $selectedId = (int) $request->query('item', 0);
        $selected = $selectedId > 0 ? $records->firstWhere('id', $selectedId) : $records->first();
        $users = $this->technicalUsers();
        $sites = $this->sites();
        $userMap = $users->keyBy('id');
        foreach ($records as $row) {
            $row->assignee_names = collect($this->decodeIds($row->assignee_ids ?? null))
                ->map(fn ($id) => optional($userMap->get($id))->name)
                ->filter()->values()->all();
            $row->plan_files_list = $this->decodeFiles($row->plan_files ?? null);
            $row->report_files_list = $this->decodeFiles($row->report_files ?? null);
            $row->completion_files_list = $this->decodeFiles($row->completion_files ?? null);
        }

        return view('technical.work.index', [
            'mode' => $mode,
            'records' => $records,
            'selected' => $selected,
            'users' => $users,
            'sites' => $sites,
            'kpis' => [
                'total' => $records->count(),
                'planned' => $records->where('status', 'planned')->count(),
                'reported' => $records->where('status', 'reported')->count(),
                'completed' => $records->where('status', 'completed')->count(),
            ],
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && (SolarMaintenanceAccess::isTechnician($user) || SolarMaintenanceAccess::isExecutive($user)), 403);
    }

    private function assertSiteInScope(int $siteId): void
    {
        $companyId = EgoCompanyScope::currentId();
        if ($companyId <= 0 || ! Schema::hasColumn('sites', 'company_id')) {
            return;
        }
        abort_unless(DB::table('sites')->where('id', $siteId)->where('company_id', $companyId)->exists(), 403);
    }

    private function findRecord(int $id): object
    {
        abort_unless(Schema::hasTable('technical_work_records'), 503);
        $row = DB::table('technical_work_records')->where('id', $id)->first();
        abort_unless($row, 404);
        $this->assertSiteInScope((int) $row->site_id);

        return $row;
    }

    private function technicalUsers(): Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        return app(SolarMaintenanceQueryService::class)->technicalUsers();
    }

    private function sites(): Collection
    {
        $query = DB::table('sites')->select('id', 'name');
        if (Schema::hasColumn('sites', 'project_code')) {
            $query->addSelect('project_code');
        }
        $companyId = EgoCompanyScope::currentId();
        if ($companyId > 0 && Schema::hasColumn('sites', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query->orderBy('name')->get();
    }

    private function storeFiles(Request $request, string $field, string $folder): array
    {
        $result = [];
        foreach ((array) $request->file($field, []) as $file) {
            if (! $file) {
                continue;
            }
            $result[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store($folder, 'public'),
                'size' => (int) $file->getSize(),
            ];
        }

        return $result;
    }

    private function decodeFiles(?string $value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function decodeIds(?string $value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_map('intval', $decoded)) : [];
    }
}
