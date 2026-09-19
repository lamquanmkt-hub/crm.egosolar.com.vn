<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\SolarMaintenanceChecklistTemplate;
use App\Models\SolarMaintenanceSchedule;
use Illuminate\Support\Facades\DB;
use App\Services\Technical\SolarMaintenanceChecklistTemplateService;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SolarMaintenanceChecklistSettingsController extends Controller
{
    public function __construct(private readonly SolarMaintenanceChecklistTemplateService $templates) {}

    public function index(Request $request): View
    {
        $this->assertAdmin($request);
        $group = (string) $request->input('group', 'periodic');
        if (! array_key_exists($group, SolarMaintenanceChecklistTemplateService::GROUPS)) {
            $group = 'periodic';
        }

        $companyId = $group === 'periodic'
            ? null
            : $this->templates->currentCompanyId();

        return view('technical.maintenance.checklist-settings', [
            'group' => $group,
            'groups' => SolarMaintenanceChecklistTemplateService::GROUPS,
            'templates' => $this->templates->settings($companyId, $group),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertAdmin($request);
        $data = $this->validateTemplate($request);
        $companyId = $group === 'periodic'
            ? null
            : $this->templates->currentCompanyId();

        SolarMaintenanceChecklistTemplate::create([
            ...$data,
            'company_id' => $companyId,
            'item_key' => 'custom_'.Str::lower(Str::random(12)),
            'is_required' => $request->boolean('is_required'),
            'requires_evidence' => $request->boolean('requires_evidence'),
            'min_evidence' => $request->boolean('requires_evidence')
                ? max(1, (int) ($data['min_evidence'] ?? 1))
                : 0,
            'is_active' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->syncOpenSchedules($companyId, $data['maintenance_type']);

        return back()->with('success', 'Đã thêm mục checklist và đồng bộ các đợt chưa hoàn thành.');
    }

    public function update(
        Request $request,
        SolarMaintenanceChecklistTemplate $template
    ): RedirectResponse {
        $this->assertAdmin($request);
        $this->assertSameCompany($template);
        $data = $this->validateTemplate($request);

        if ($template->is_required
            && ! $request->boolean('is_required')
            && $this->isLastRequiredTemplate($template)) {
            return back()->with('error', 'Mỗi loại công việc phải còn ít nhất một mục checklist bắt buộc.');
        }

        $template->update([
            ...$data,
            'maintenance_type' => $template->maintenance_type,
            'is_required' => $request->boolean('is_required'),
            'requires_evidence' => $request->boolean('requires_evidence'),
            'min_evidence' => $request->boolean('requires_evidence')
                ? max(1, (int) ($data['min_evidence'] ?? 1))
                : 0,
            'updated_by' => $request->user()->id,
        ]);

        $this->syncOpenSchedules((int) $template->company_id, $template->maintenance_type);

        return back()->with('success', 'Đã cập nhật checklist và đồng bộ các đợt chưa hoàn thành.');
    }

    public function destroy(
        Request $request,
        SolarMaintenanceChecklistTemplate $template
    ): RedirectResponse {
        $this->assertAdmin($request);
        $this->assertSameCompany($template);

        if ($template->is_required && $this->isLastRequiredTemplate($template)) {
            return back()->with('error', 'Không thể bỏ mục bắt buộc cuối cùng của loại công việc này.');
        }

        $companyId = (int) ($template->company_id ?? 0);
        $maintenanceType = $template->maintenance_type;

        $template->delete();

        $this->syncOpenSchedules(
            $companyId,
            $maintenanceType
        );

        return back()->with(
            'success',
            'Đã bỏ mục và đồng bộ các đợt chưa hoàn thành. Hồ sơ đã hoàn thành vẫn giữ lịch sử.'
        );
    }


    private function syncOpenSchedules(
        int $companyId,
        string $maintenanceType
    ): void {
        $templates = SolarMaintenanceChecklistTemplate::query()
            ->where('maintenance_type', $maintenanceType)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when(
                $companyId > 0,
                fn ($q) => $q->where('company_id', $companyId)
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $keys = $templates
            ->pluck('item_key')
            ->filter()
            ->values();

        $schedules = SolarMaintenanceSchedule::withoutGlobalScopes()
            ->when(
                $maintenanceType === 'incident',
                fn ($q) => $q->where('type', 'incident'),
                fn ($q) => $q->where(function ($sub) {
                    $sub->whereNull('type')
                        ->orWhere('type', '<>', 'incident');
                })
            )
            // Checklist O&M áp dụng chung cho toàn bộ công trình,
            // không giới hạn theo company.
            ->whereNotIn('status', [
                'completed',
                'approved',
                'cancelled',
            ])
            ->get();

        foreach ($schedules as $schedule) {

            DB::transaction(
                function () use (
                    $schedule,
                    $templates,
                    $keys
                ): void {

                    $items = DB::table(
                        'solar_maintenance_checklist_items'
                    )
                        ->where(
                            'maintenance_schedule_id',
                            $schedule->id
                        )
                        ->get();

                    foreach ($items as $item) {

                        if (
                            ! $keys->contains(
                                (string) $item->item_key
                            )
                        ) {

                            if (
                                \Illuminate\Support\Facades\Schema::hasTable(
                                    'solar_maintenance_attachments'
                                )
                                && \Illuminate\Support\Facades\Schema::hasColumn(
                                    'solar_maintenance_attachments',
                                    'checklist_item_id'
                                )
                            ) {
                                DB::table(
                                    'solar_maintenance_attachments'
                                )
                                    ->where(
                                        'checklist_item_id',
                                        $item->id
                                    )
                                    ->update([
                                        'checklist_item_id' => null,
                                    ]);
                            }

                            DB::table(
                                'solar_maintenance_checklist_items'
                            )
                                ->where('id', $item->id)
                                ->delete();
                        }
                    }

                    foreach ($templates as $template) {

                        $row = DB::table(
                            'solar_maintenance_checklist_items'
                        )
                            ->where(
                                'maintenance_schedule_id',
                                $schedule->id
                            )
                            ->where(
                                'item_key',
                                $template->item_key
                            )
                            ->first();

                        $payload = [
                            'checklist_template_id' =>
                                $template->id,

                            'label' =>
                                $template->label,

                            'sort_order' =>
                                $template->sort_order,

                            'is_required' =>
                                $template->is_required,

                            'requires_evidence' =>
                                $template->requires_evidence,

                            'min_evidence' =>
                                $template->min_evidence,

                            'updated_at' =>
                                now(),
                        ];

                        if ($row) {

                            DB::table(
                                'solar_maintenance_checklist_items'
                            )
                                ->where('id', $row->id)
                                ->update($payload);

                        } else {

                            DB::table(
                                'solar_maintenance_checklist_items'
                            )->insert(
                                $payload + [
                                    'maintenance_schedule_id' =>
                                        $schedule->id,

                                    'item_key' =>
                                        $template->item_key,

                                    'is_done' =>
                                        false,

                                    'note' =>
                                        null,

                                    'completed_by' =>
                                        null,

                                    'completed_at' =>
                                        null,

                                    'created_at' =>
                                        now(),
                                ]
                            );
                        }
                    }
                }
            );
        }
    }
    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'maintenance_type' => ['required', Rule::in(array_keys(SolarMaintenanceChecklistTemplateService::GROUPS))],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_required' => ['nullable', 'boolean'],
            'requires_evidence' => ['nullable', 'boolean'],
            'min_evidence' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);
    }

    private function assertAdmin(Request $request): void
    {
        abort_unless(SolarMaintenanceAccess::isAdmin($request->user()), 403);
    }

    private function assertSameCompany(SolarMaintenanceChecklistTemplate $template): void
    {
        if (
            $template->maintenance_type === 'periodic'
            && $template->company_id === null
        ) {
            return;
        }

        abort_unless(
            (int) ($template->company_id ?? 0)
                ===
            (int) ($this->templates->currentCompanyId() ?? 0),
            403
        );
    }

    private function isLastRequiredTemplate(SolarMaintenanceChecklistTemplate $template): bool
    {
        return SolarMaintenanceChecklistTemplate::query()
            ->where('maintenance_type', $template->maintenance_type)
            ->where('is_required', true)
            ->when($template->company_id, fn ($query) => $query->where('company_id', $template->company_id))
            ->when(! $template->company_id, fn ($query) => $query->whereNull('company_id'))
            ->count() <= 1;
    }
}
