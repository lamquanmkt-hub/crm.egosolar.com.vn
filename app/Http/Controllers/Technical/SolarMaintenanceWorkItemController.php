<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\SolarMaintenanceSchedule;
use App\Models\SolarMaintenanceWorkItem;
use App\Models\User;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SolarMaintenanceWorkItemController extends Controller
{
    public function store(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $schedule);

        if ($request->boolean('checklist_setting')) {
            abort_unless(SolarMaintenanceAccess::isAdmin($request->user()), 403, 'Chỉ Admin được cài đặt hạng mục hồ sơ.');
        }

        $data = $this->validated($request);
        $data = $this->applyChecklistSettings($request, $data);
        $assigneeId = $this->resolveAssignee($request, $schedule, $data['assignee_id'] ?? null);
        $values = $this->normalizeValues($data, null);

        $workItem = $schedule->workItems()->create(array_merge($values, [
            'company_id' => $schedule->company_id ?: $schedule->site?->company_id,
            'assignee_id' => $assigneeId,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'sort_order' => (int) ($schedule->workItems()->max('sort_order') ?? 0) + 10,
        ]));

        $schedule->auditLogs()->create([
            'action' => 'work_item_created',
            'new_values' => ['work_item_id' => $workItem->id, 'title' => $workItem->title],
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm công việc kỹ thuật.');
    }

    public function update(
        Request $request,
        SolarMaintenanceSchedule $schedule,
        SolarMaintenanceWorkItem $workItem
    ): RedirectResponse {
        $this->assertBelongsToSchedule($schedule, $workItem);
        $this->authorize('update', $schedule);
        $this->assertCanEditItem($request, $workItem, $schedule);

        if ($request->boolean('checklist_setting')) {
            abort_unless(SolarMaintenanceAccess::isAdmin($request->user()), 403, 'Chỉ Admin được cài đặt hạng mục hồ sơ.');
        }

        $data = $this->validated($request, true);
        $data = $this->applyChecklistSettings($request, $data, $workItem);
        if (($data['status'] ?? '') === 'completed') {
            $configuration = $this->checklistConfiguration($workItem);
            $filesCount = $workItem->attachments()->count();
            if ($configuration['required'] && $filesCount < $configuration['min']) {
                throw ValidationException::withMessages([
                    'status' => 'Hạng mục cần tối thiểu '.$configuration['min'].' tệp minh chứng trước khi hoàn thành.',
                ]);
            }
        }
        $assigneeId = array_key_exists('assignee_id', $data)
            ? $this->resolveAssignee($request, $schedule, $data['assignee_id'])
            : $workItem->assignee_id;

        $old = $workItem->only(['title', 'status', 'progress_percent', 'assignee_id']);
        $values = $this->normalizeValues($data, $workItem);
        $values['assignee_id'] = $assigneeId;
        $values['updated_by'] = $request->user()->id;
        $workItem->update($values);

        $schedule->auditLogs()->create([
            'action' => 'work_item_updated',
            'old_values' => $old,
            'new_values' => $workItem->fresh()->only(['id', 'title', 'status', 'progress_percent', 'assignee_id']),
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Đã cập nhật công việc kỹ thuật.');
    }

    public function destroy(
        Request $request,
        SolarMaintenanceSchedule $schedule,
        SolarMaintenanceWorkItem $workItem
    ): RedirectResponse {
        $this->assertBelongsToSchedule($schedule, $workItem);
        $this->authorize('update', $schedule);

        if (str_starts_with((string) $workItem->description, '__ego_checklist__')) {
            abort_unless(SolarMaintenanceAccess::isAdmin($request->user()), 403, 'Chỉ Admin được xóa hạng mục hồ sơ.');
        }

        $isManager = SolarMaintenanceAccess::isManager($request->user());
        abort_unless($isManager || (int) $workItem->created_by === (int) $request->user()->id, 403);
        if ($workItem->status === 'completed' && ! $isManager) {
            abort(403, 'Chỉ Trưởng phòng kỹ thuật hoặc Admin được xóa công việc đã hoàn thành.');
        }

        $workItem->delete();

        return back()->with('success', 'Đã xóa công việc kỹ thuật.');
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $prefix = $sometimes ? ['sometimes'] : ['required'];

        return $request->validate([
            'title' => array_merge($prefix, ['string', 'max:255']),
            'description' => ['nullable', 'string', 'max:5000'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => array_merge($prefix, [Rule::in(array_keys(SolarMaintenanceWorkItem::STATUSES))]),
            'progress_percent' => array_merge($prefix, ['integer', 'min:0', 'max:100']),
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'actual_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'result_note' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function resolveAssignee(Request $request, SolarMaintenanceSchedule $schedule, mixed $requested): ?int
    {
        if (SolarMaintenanceAccess::isTechnicianOnly($request->user())) {
            return (int) $request->user()->id;
        }

        if ($requested === null || $requested === '') {
            return null;
        }

        abort_unless(SolarMaintenanceAccess::isManager($request->user()), 403, 'Chỉ quản lý được phân công lại công việc.');
        $user = User::find((int) $requested);
        if (! $user || ! SolarMaintenanceAccess::isSelectableTechnician($user)) {
            throw ValidationException::withMessages(['assignee_id' => 'Người thực hiện phải là nhân sự kỹ thuật đang hoạt động.']);
        }

        return (int) $user->id;
    }

    private function normalizeValues(array $data, ?SolarMaintenanceWorkItem $item): array
    {
        $values = $data;
        $status = (string) ($values['status'] ?? $item?->status ?? 'pending');
        $progress = (int) ($values['progress_percent'] ?? $item?->progress_percent ?? 0);

        if ($status === 'completed' || $progress >= 100) {
            $values['status'] = 'completed';
            $values['progress_percent'] = 100;
            $values['started_at'] = $values['started_at'] ?? $item?->started_at ?? now();
            $values['completed_at'] = $values['completed_at'] ?? $item?->completed_at ?? now();
        } elseif ($status === 'in_progress' || $progress > 0) {
            $values['status'] = $status === 'pending' ? 'in_progress' : $status;
            $values['started_at'] = $values['started_at'] ?? $item?->started_at ?? now();
            $values['completed_at'] = null;
        } else {
            $values['completed_at'] = null;
        }

        if (! empty($values['started_at']) && ! empty($values['completed_at']) && empty($values['actual_minutes'])) {
            $start = \Carbon\Carbon::parse($values['started_at']);
            $end = \Carbon\Carbon::parse($values['completed_at']);
            $values['actual_minutes'] = max(0, $start->diffInMinutes($end));
        }

        return $values;
    }

    private function assertBelongsToSchedule(SolarMaintenanceSchedule $schedule, SolarMaintenanceWorkItem $workItem): void
    {
        abort_unless((int) $workItem->maintenance_schedule_id === (int) $schedule->id, 404);
    }

    private function assertCanEditItem(Request $request, SolarMaintenanceWorkItem $workItem, SolarMaintenanceSchedule $schedule): void
    {
        if (SolarMaintenanceAccess::isManager($request->user())) {
            return;
        }

        abort_unless(
            (int) $workItem->assignee_id === (int) $request->user()->id
            || (int) $workItem->created_by === (int) $request->user()->id
            || $schedule->assignees()->where('user_id', $request->user()->id)->whereNotNull('accepted_at')->exists(),
            403,
            'Bạn chỉ được cập nhật công việc do mình phụ trách.'
        );
    }

    private function applyChecklistSettings(Request $request, array $data, ?SolarMaintenanceWorkItem $item = null): array
    {
        if (! $request->boolean('checklist_setting')) {
            return $data;
        }

        $settings = $request->validate([
            'setting_required' => ['nullable', 'boolean'],
            'setting_min_files' => ['required', 'integer', 'min:0', 'max:100'],
            'setting_max_files' => ['nullable', 'integer', 'min:1', 'max:100'],
            'setting_extensions' => ['nullable', 'string', 'max:255'],
        ]);

        $minimum = (int) $settings['setting_min_files'];
        $maximum = isset($settings['setting_max_files']) ? (int) $settings['setting_max_files'] : 0;
        if ($maximum > 0 && $maximum < $minimum) {
            throw ValidationException::withMessages(['setting_max_files' => 'Số tệp tối đa không được nhỏ hơn số tối thiểu.']);
        }

        $data['description'] = '__ego_checklist__'.json_encode([
            'required' => (bool) ($settings['setting_required'] ?? false),
            'min' => $minimum,
            'max' => $maximum,
            'extensions' => strtolower(trim((string) ($settings['setting_extensions'] ?? 'jpg,jpeg,png,pdf'))),
        ], JSON_UNESCAPED_UNICODE);

        return $data;
    }

    private function checklistConfiguration(SolarMaintenanceWorkItem $item): array
    {
        $description = (string) $item->description;
        if (! str_starts_with($description, '__ego_checklist__')) {
            return ['required' => true, 'min' => 1, 'max' => 0, 'extensions' => ''];
        }

        $configuration = json_decode(substr($description, 17), true);

        return is_array($configuration)
            ? array_merge(['required' => true, 'min' => 1, 'max' => 0, 'extensions' => ''], $configuration)
            : ['required' => true, 'min' => 1, 'max' => 0, 'extensions' => ''];
    }
}
