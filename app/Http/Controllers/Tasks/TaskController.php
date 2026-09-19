<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Tasks\Task;
use App\Models\ProjectTest\Project;
use App\Models\User;
use App\Services\Workspace\WorkspaceContextService;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Controller quản lý giao việc và theo dõi công việc nội bộ.
 */
class TaskController extends Controller
{
    /**
     * Kiểm tra người dùng có quyền giao việc/quản lý công việc.
     */
    private function canAssign($user): bool
    {
        if (! $user) {
            return false;
        }

        /*
         * EGO_TECHNICAL_TASK_ACCESS:
         * Tài khoản có role kỹ thuật nhưng mang chức danh Trưởng phòng,
         * Manager hoặc Leader vẫn được quyền giao và quản lý việc của phòng.
         */
        if ($this->isTechnicalManager($user)) {
            return true;
        }

        $roles = [
            'admin', 'manager', 'management', 'director', 'general_director',
            'ban_giam_doc', 'giam_doc', 'sales_manager', 'marketing_manager',
            'accounting', 'technical_manager', 'technical_leader', 'truong_phong_ky_thuat',
        ];

        if (method_exists($user, 'hasAnyRole')) {
            try {
                return $user->hasAnyRole($roles);
            } catch (\Throwable) {
            }
        }

        $role = strtolower((string) ($user->role ?? $user->type ?? ''));

        return in_array($role, $roles, true);
    }

    /**
     * Kiểm tra bảng có tồn tại trong database.
     */
    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * Lọc dữ liệu chỉ giữ các cột có trong bảng.
     */
    private function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        return collect($data)
            ->only(Schema::getColumnListing($table))
            ->toArray();
    }

    /* EGO_TASK_NOTIFY_START */
    /**
     * Tạo bảng task_notifications nếu chưa tồn tại.
     */
    private function ensureTaskNotificationTable(): void
    {
        if (Schema::hasTable('task_notifications')) {
            return;
        }

        DB::statement("CREATE TABLE IF NOT EXISTS `task_notifications` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `task_id` BIGINT UNSIGNED NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `type` VARCHAR(50) NOT NULL DEFAULT 'assigned',
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT NULL,
            `link` VARCHAR(500) NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `read_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP NULL DEFAULT NULL,
            `updated_at` TIMESTAMP NULL DEFAULT NULL,
            KEY `task_notifications_user_read_idx` (`user_id`,`is_read`,`created_at`),
            KEY `task_notifications_task_idx` (`task_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Lấy link chi tiết công việc dùng trong thông báo.
     */
    private function taskNotificationLink(Task $task): string
    {
        try {
            if (Route::has('tasks.show')) {
                return route('tasks.show', $task);
            }
        } catch (\Throwable $e) {
        }

        return url('/chat/tasks/'.$task->id);
    }

    /**
     * Ghi thông báo giao việc cho người được giao.
     */
    private function notifyTaskAssigned(Task $task): void
    {
        try {
            if (empty($task->assignee_id)) {
                return;
            }

            $this->ensureTaskNotificationTable();

            $assigner = optional(auth()->user())->name ?: 'Hệ thống';
            $dueText = '';

            if (! empty($task->due_at)) {
                try {
                    $dueText = ' - Hạn: '.Carbon::parse($task->due_at)->format('d/m/Y H:i');
                } catch (\Throwable $e) {
                    $dueText = ' - Hạn: '.(string) $task->due_at;
                }
            }

            DB::table('task_notifications')->insert($this->filterColumns('task_notifications', [
                'task_id' => $task->id,
                'user_id' => (int) $task->assignee_id,
                'created_by' => auth()->id(),
                'type' => 'assigned',
                'title' => '📌 Công việc mới được giao',
                'message' => $assigner.' đã giao cho bạn công việc: '.$task->title.$dueText,
                'link' => $this->taskNotificationLink($task),
                'is_read' => 0,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
    }
    /* EGO_TASK_NOTIFY_END */

    /**
     * Danh sách mức độ ưu tiên công việc.
     */
    private function priorities(): array
    {
        return [
            'low' => 'Thấp',
            'medium' => 'Bình thường',
            'high' => 'Cao',
        ];
    }

    /**
     * Danh sách trạng thái công việc.
     */
    private function statuses(): array
    {
        return [
            'new' => 'Mới giao',
            'in_progress' => 'Đang làm',
            'submitted' => 'Đã nộp',
            'revision' => 'Cần sửa / bổ sung',
            'rejected' => 'Bị từ chối',
            'approved' => 'Đã duyệt',
        ];
    }

    /**
     * Lưu lịch sử thay đổi công việc để người giao, người duyệt và nhân viên cùng đối soát.
     */
    private function logTaskActivity(
        Task $task,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $note = null,
        array $meta = []
    ): void {
        try {
            if (! Schema::hasTable('task_activity_logs')) {
                return;
            }

            DB::table('task_activity_logs')->insert($this->filterColumns('task_activity_logs', [
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'action' => $action,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
                'meta' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Lưu file đính kèm của công việc theo loại (giao việc/kết quả/trả hồ sơ).
     */
    private function isExecutiveTaskManager($user): bool
    {
        if (! $user) {
            return false;
        }

        $roles = [
            'admin', 'manager', 'management', 'director', 'general_director',
            'ban_giam_doc', 'giam_doc', 'ceo',
        ];

        return method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole($roles)
            : in_array(strtolower((string) ($user->role ?? '')), $roles, true);
    }

    private function isTechnicalManager($user): bool
    {
        if (! $user) {
            return false;
        }

        $roles = ['technical_manager', 'technical_leader', 'truong_phong_ky_thuat'];
        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole($roles)) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        $position = mb_strtolower((string) optional($user->position)->name);
        $department = mb_strtolower((string) optional($user->department)->name);

        return (str_contains($position, 'trưởng') || str_contains($position, 'manager') || str_contains($position, 'leader'))
            && (str_contains($department, 'kỹ thuật') || str_contains($department, 'technical'));
    }

    private function isTechnicalTaskContext($user, ?Task $task = null): bool
    {
        if ($task && (string) ($task->task_type ?? '') === 'technical') {
            return true;
        }

        try {
            return $user && app(WorkspaceContextService::class)->current($user) === 'technical';
        } catch (\Throwable) {
            return false;
        }
    }

    private function assignableUsers($user)
    {
        $query = User::query()->with(['department', 'position'])->orderBy('name');
        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        if ($this->isTechnicalManager($user) && ! $this->isExecutiveTaskManager($user)) {
            if (! empty($user->department_id)) {
                $query->where('department_id', $user->department_id);
            } elseif (method_exists(User::class, 'role')) {
                $query->role(['technical', 'technical_staff', 'technician', 'ky_thuat']);
            }
        }

        return $query->get();
    }

    private function technicalFormData($user, ?Task $task = null): array
    {
        $isTechnicalTaskContext = $this->isTechnicalTaskContext($user, $task);
        $technicalProjects = collect();
        $technicalApprovers = collect();

        if ($isTechnicalTaskContext && Schema::hasTable('project_test_projects')) {
            $technicalProjects = Project::query()
                ->where('company_id', EgoCompanyLock::id())
                ->visibleTo($user)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->latest('id')
                ->get(['id', 'code', 'name', 'address', 'status']);

            $technicalApprovers = User::query()
                ->with(['department', 'position'])
                ->when(! $this->isExecutiveTaskManager($user) && ! empty($user->department_id), fn ($q) => $q->where('department_id', $user->department_id))
                ->where(function ($query): void {
                    if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
                        $query->whereHas('roles', fn ($role) => $role->whereIn('name', [
                            'admin', 'management', 'manager', 'technical_manager',
                            'technical_leader', 'truong_phong_ky_thuat',
                        ]));
                    } else {
                        $query->whereNotNull('id');
                    }
                })
                ->orderBy('name')
                ->get();

            if ($technicalApprovers->isEmpty()) {
                $technicalApprovers = collect([$user]);
            }
        }

        return compact('isTechnicalTaskContext', 'technicalProjects', 'technicalApprovers');
    }

    private function assertAssignableUsers($user, $assigneeIds): void
    {
        if (! $this->isTechnicalManager($user) || $this->isExecutiveTaskManager($user)) {
            return;
        }

        $allowed = $this->assignableUsers($user)->pluck('id')->map(fn ($id) => (int) $id);
        abort_unless(collect($assigneeIds)->every(fn ($id) => $allowed->contains((int) $id)), 403, 'Trưởng phòng chỉ được giao việc cho nhân sự thuộc phòng Kỹ thuật.');
    }

    /* EGO_TASK_FLEX_PROJECT_V1 */
    private function assertTechnicalTaskData($user, array $data): void
    {
        $linkType = (string) ($data['project_link_type'] ?? 'none');
        $projectId = (int) ($data['project_id'] ?? 0);

        if (in_array($linkType, ['existing', 'new'], true) || $projectId > 0) {
            $project = Project::query()
                ->where('company_id', EgoCompanyLock::id())
                ->visibleTo($user)
                ->find($projectId);

            abort_unless($project, 403, 'Công trình không thuộc phạm vi Kỹ thuật được phép xem.');
        }

        $approverId = (int) ($data['approver_id'] ?? 0);
        abort_unless($approverId > 0 && User::query()->whereKey($approverId)->exists(), 422, 'Người duyệt không hợp lệ.');
    }

    /**
     * Tạo nhanh công trình ngay trong phiếu giao việc.
     * Chỉ tạo hồ sơ tối thiểu, không làm mất dữ liệu đang nhập trên form giao việc.
     */
    public function quickProjectStore(Request $request)
    {
        $user = auth()->user();
        abort_unless($this->canAssign($user) && $this->isTechnicalTaskContext($user), 403);
        abort_unless(Schema::hasTable('project_test_projects'), 422, 'Chưa có bảng dữ liệu công trình.');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:700',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:60',
            'customer_need' => 'required|string|max:5000',
            'priority' => 'nullable|in:low,normal,medium,high,urgent',
            'target_completion_at' => 'nullable|date',
        ]);

        $prefix = 'CT-KT-'.now()->format('Ym').'-';
        $lastId = (int) DB::table('project_test_projects')->max('id');
        $sequence = max(1, $lastId + 1);
        do {
            $code = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $exists = DB::table('project_test_projects')->where('code', $code)->exists();
            $sequence++;
        } while ($exists);

        $payload = $this->filterColumns('project_test_projects', [
            'code' => $code,
            'company_id' => EgoCompanyLock::id(),
            'created_by' => $user->id,
            'technical_manager_id' => $this->isTechnicalManager($user) ? $user->id : null,
            'name' => $data['name'],
            'address' => $data['address'],
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'customer_need' => $data['customer_need'],
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'survey_pending',
            'current_owner_role' => 'technical',
            'target_completion_at' => $data['target_completion_at'] ?? null,
            'progress' => 5,
            'note' => 'Tạo nhanh từ phiếu giao việc kỹ thuật.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $projectId = DB::table('project_test_projects')->insertGetId($payload);

        return response()->json([
            'ok' => true,
            'project' => [
                'id' => $projectId,
                'code' => $code,
                'name' => $data['name'],
                'address' => $data['address'],
            ],
        ]);
    }

    private function storeAttachments(Request $request, Task $task, string $inputName, string $type): void
    {
        if (! $request->hasFile($inputName)) {
            return;
        }

        foreach ($request->file($inputName) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('task_attachments/'.$task->id, 'public');

            if ($this->tableExists('task_attachments')) {
                DB::table('task_attachments')->insert([
                    'task_id' => $task->id,
                    'uploaded_by' => auth()->id(),
                    'type' => $type,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_mime' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Lấy danh sách file đính kèm của công việc.
     */
    private function attachmentsFor(Task $task)
    {
        if (! $this->tableExists('task_attachments')) {
            return collect();
        }

        return DB::table('task_attachments')
            ->where('task_id', $task->id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Hiển thị toàn bộ công việc gom theo phòng ban (chỉ quản lý).
     */
    public function index()
    {
        $user = auth()->user();

        /*
         * Nhân viên không có quyền quản lý vẫn được sử dụng module Công việc.
         * Khi mở danh sách tổng, tự chuyển về danh sách việc được giao.
         */
        if (! $this->canAssign($user)) {
            return redirect()->route('tasks.my');
        }

        $taskQuery = Task::with(['assignee.department', 'requester', 'project', 'approver']);
        if ($this->isTechnicalManager($user) && ! $this->isExecutiveTaskManager($user)) {
            $taskQuery->where(function ($query) use ($user): void {
                if (! empty($user->department_id)) {
                    $query->whereHas('assignee', fn ($assignee) => $assignee->where('department_id', $user->department_id));
                }
                if (Schema::hasColumn('tasks', 'task_type')) {
                    $query->orWhere('task_type', 'technical');
                }
            });
        }

        $allTasks = $taskQuery->latest()->get();
        $summary = [
            'total' => $allTasks->count(),
            'new' => $allTasks->where('status', 'new')->count(),
            'in_progress' => $allTasks->where('status', 'in_progress')->count(),
            'submitted' => $allTasks->where('status', 'submitted')->count(),
            'approved' => $allTasks->where('status', 'approved')->count(),
        ];

        $departments = collect();
        if (Schema::hasTable('departments')) {
            $departments = Department::query()
                ->when($this->isTechnicalManager($user) && ! $this->isExecutiveTaskManager($user) && $user->department_id,
                    fn ($query) => $query->whereKey($user->department_id))
                ->orderBy('name')
                ->get();
        }

        $taskGroups = collect();
        foreach ($departments as $department) {
            $departmentTasks = $allTasks->filter(fn ($task) => (int) optional($task->assignee)->department_id === (int) $department->id)->values();
            $taskGroups->push([
                'id' => $department->id,
                'name' => $department->name,
                'tasks' => $departmentTasks,
                'total' => $departmentTasks->count(),
                'new' => $departmentTasks->where('status', 'new')->count(),
                'in_progress' => $departmentTasks->where('status', 'in_progress')->count(),
                'submitted' => $departmentTasks->where('status', 'submitted')->count(),
                'approved' => $departmentTasks->where('status', 'approved')->count(),
            ]);
        }

        $noDepartmentTasks = $allTasks->filter(fn ($task) => empty(optional($task->assignee)->department_id))->values();
        if ($noDepartmentTasks->isNotEmpty() || $departments->isEmpty()) {
            $taskGroups->push([
                'id' => 0, 'name' => 'Chưa có phòng ban', 'tasks' => $noDepartmentTasks,
                'total' => $noDepartmentTasks->count(), 'new' => $noDepartmentTasks->where('status', 'new')->count(),
                'in_progress' => $noDepartmentTasks->where('status', 'in_progress')->count(),
                'submitted' => $noDepartmentTasks->where('status', 'submitted')->count(),
                'approved' => $noDepartmentTasks->where('status', 'approved')->count(),
            ]);
        }

        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $canBossEdit = true;

        return view('tasks.index', compact('allTasks', 'taskGroups', 'summary', 'priorities', 'statuses', 'canBossEdit'));
    }

    /**
     * Hiển thị danh sách công việc được giao cho người dùng hiện tại.
     */
    public function my()
    {
        $tasks = Task::with(['requester'])
            ->where('assignee_id', auth()->id())
            ->latest()
            ->paginate(30);

        $summary = [
            'total' => Task::where('assignee_id', auth()->id())->count(),
            'new' => Task::where('assignee_id', auth()->id())->where('status', 'new')->count(),
            'in_progress' => Task::where('assignee_id', auth()->id())->where('status', 'in_progress')->count(),
            'submitted' => Task::where('assignee_id', auth()->id())->where('status', 'submitted')->count(),
            'approved' => Task::where('assignee_id', auth()->id())->where('status', 'approved')->count(),
        ];

        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $canBossEdit = $this->canAssign(auth()->user());

        return view('tasks.my', compact('tasks', 'summary', 'priorities', 'statuses', 'canBossEdit'));
    }

    /**
     * Hiển thị form giao việc.
     */
    public function create()
    {
        $user = auth()->user();
        if (! $this->canAssign($user)) {
            abort(403);
        }

        $users = $this->assignableUsers($user);
        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $context = $this->technicalFormData($user);

        return view('tasks.create', array_merge(compact('users', 'priorities', 'statuses'), $context));
    }

    /**
     * Tạo công việc cho từng người được giao và gửi thông báo.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (! $this->canAssign($user)) {
            abort(403);
        }

        $technical = $this->isTechnicalTaskContext($user);
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignee_ids' => 'required|array|min:1',
            'assignee_ids.*' => 'required|integer|exists:users,id',
            'priority' => 'required|in:low,medium,high',
            'due_at' => ($technical ? 'required' : 'nullable').'|date',
            'link_url' => 'nullable|url|max:2048',
            'attachments.*' => 'nullable|file|max:51200',
            'project_link_type' => $technical ? 'required|in:none,existing,new' : 'nullable|in:none,existing,new',
            'project_id' => $technical ? 'nullable|integer|required_if:project_link_type,existing,new' : 'nullable|integer',
            'work_item' => 'nullable|string|max:255',
            'work_location' => 'nullable|string|max:700',
            'approver_id' => $technical ? 'required|integer|exists:users,id' : 'nullable|integer|exists:users,id',
        ];
        $data = $request->validate($rules);

        if ($technical) {
            $data['project_link_type'] = (string) ($data['project_link_type'] ?? 'none');
            if ($data['project_link_type'] === 'none') {
                $data['project_id'] = null;
                $data['work_location'] = $data['work_location'] ?? null;
                $data['work_item'] = $data['work_item'] ?? null;
            }
        }

        $assigneeIds = collect($data['assignee_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $this->assertAssignableUsers($user, $assigneeIds);
        if ($technical) {
            $this->assertTechnicalTaskData($user, $data);
            $data['task_type'] = 'technical';
        }

        $createdTasks = collect();
        foreach ($assigneeIds as $assigneeId) {
            $taskData = $data;
            unset($taskData['assignee_ids']);
            $taskData['assignee_id'] = $assigneeId;
            $taskData['requester_id'] = $user->id;
            $taskData['status'] = 'new';
            $taskData['progress_percent'] = 0;

            $task = Task::create($this->filterColumns('tasks', $taskData));
            $createdTasks->push($task);
            $this->storeAttachments($request, $task, 'attachments', 'task');
            $this->notifyTaskAssigned($task);
            $this->logTaskActivity(
                $task,
                'assigned',
                null,
                'new',
                'Đã giao công việc cho '.(optional($task->assignee)->name ?: 'nhân viên'),
                ['assignee_id' => $assigneeId, 'project_id' => $task->project_id]
            );
        }

        if ($createdTasks->count() === 1) {
            return redirect()->route('tasks.show', $createdTasks->first())->with('success', 'Đã giao việc thành công.');
        }

        return redirect()->route('tasks.index')->with('success', 'Đã giao việc cho '.$createdTasks->count().' người.');
    }

    /**
     * Hiển thị chi tiết công việc kèm các task cùng nhóm giao nhiều người.
     */
    public function show(Task $task)
    {
        $isOwner = (int) $task->assignee_id === (int) auth()->id();
        $isRequester = (int) $task->requester_id === (int) auth()->id();
        $isApprover = (int) ($task->approver_id ?? 0) === (int) auth()->id();

        if (! $isOwner && ! $isRequester && ! $isApprover && ! $this->canAssign(auth()->user())) {
            abort(403);
        }

        $task->load(['assignee', 'requester', 'project', 'approver']);
        $relatedTasksQuery = Task::with(['assignee', 'project', 'approver']);
        if (Schema::hasColumn('tasks', 'task_group_id') && ! empty($task->task_group_id)) {
            $relatedTasksQuery->where('task_group_id', $task->task_group_id);
        } else {
            $relatedTasksQuery
                ->where('title', $task->title)
                ->where('requester_id', $task->requester_id)
                ->when($task->project_id, fn ($query) => $query->where('project_id', $task->project_id))
                ->where(function ($query) use ($task): void {
                    empty($task->due_at) ? $query->whereNull('due_at') : $query->where('due_at', $task->due_at);
                })
                ->where(function ($query) use ($task): void {
                    empty($task->description)
                        ? $query->whereNull('description')->orWhere('description', '')
                        : $query->where('description', $task->description);
                });
        }

        $relatedTasks = $relatedTasksQuery->orderBy('id')->get();
        if ($relatedTasks->isEmpty()) {
            $relatedTasks = collect([$task]);
        }

        $attachments = $this->attachmentsFor($task);
        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $canApprove = $isApprover || $isRequester || $this->canAssign(auth()->user());
        $canUpdate = $isOwner;
        $activityLogs = collect();
        if (Schema::hasTable('task_activity_logs')) {
            $activityLogs = DB::table('task_activity_logs as logs')
                ->leftJoin('users', 'users.id', '=', 'logs.user_id')
                ->where('logs.task_id', $task->id)
                ->orderByDesc('logs.id')
                ->limit(50)
                ->get([
                    'logs.id', 'logs.action', 'logs.from_status', 'logs.to_status',
                    'logs.note', 'logs.meta', 'logs.created_at', 'users.name as user_name',
                ]);
        }

        return view('tasks.show', compact('task', 'relatedTasks', 'attachments', 'priorities', 'statuses', 'canApprove', 'canUpdate', 'activityLogs'));
    }

    /**
     * Hiển thị form sửa công việc.
     */
    public function edit(Task $task)
    {
        $user = auth()->user();
        if (! $this->canAssign($user) && (int) $task->requester_id !== (int) $user->id) {
            abort(403);
        }

        if ($this->isTechnicalManager($user) && ! $this->isExecutiveTaskManager($user) && (int) optional($task->assignee)->department_id !== (int) $user->department_id) {
            abort(403);
        }

        $users = $this->assignableUsers($user);
        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $context = $this->technicalFormData($user, $task);

        return view('tasks.edit', array_merge(compact('task', 'users', 'priorities', 'statuses'), $context));
    }

    /**
     * Cập nhật công việc và giao thêm cho người nhận mới nếu có.
     */
    public function update(Request $request, Task $task)
    {
        $user = auth()->user();
        if (! $this->canAssign($user) && (int) $task->requester_id !== (int) $user->id) {
            abort(403);
        }

        $technical = $this->isTechnicalTaskContext($user, $task);
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignee_ids' => 'required|array|min:1',
            'assignee_ids.*' => 'required|integer|exists:users,id',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:new,in_progress,submitted,revision,rejected,approved',
            'due_at' => ($technical ? 'required' : 'nullable').'|date',
            'link_url' => 'nullable|url|max:2048',
            'attachments.*' => 'nullable|file|max:51200',
            'project_link_type' => $technical ? 'required|in:none,existing,new' : 'nullable|in:none,existing,new',
            'project_id' => $technical ? 'nullable|integer|required_if:project_link_type,existing,new' : 'nullable|integer',
            'work_item' => 'nullable|string|max:255',
            'work_location' => 'nullable|string|max:700',
            'approver_id' => $technical ? 'required|integer|exists:users,id' : 'nullable|integer|exists:users,id',
        ];
        $data = $request->validate($rules);

        if ($technical) {
            $data['project_link_type'] = (string) ($data['project_link_type'] ?? 'none');
            if ($data['project_link_type'] === 'none') {
                $data['project_id'] = null;
                $data['work_location'] = $data['work_location'] ?? null;
                $data['work_item'] = $data['work_item'] ?? null;
            }
        }

        $assigneeIds = collect($data['assignee_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $this->assertAssignableUsers($user, $assigneeIds);
        if ($technical) {
            $this->assertTechnicalTaskData($user, $data);
            $data['task_type'] = 'technical';
        }

        $mainAssigneeId = (int) $assigneeIds->first();
        $oldAssigneeId = (int) ($task->assignee_id ?? 0);
        $oldStatus = (string) ($task->status ?? '');
        $updateData = $data;
        unset($updateData['assignee_ids']);
        $updateData['assignee_id'] = $mainAssigneeId;
        $task->update($this->filterColumns('tasks', $updateData));
        $this->storeAttachments($request, $task, 'attachments', 'task');
        if ($oldAssigneeId !== $mainAssigneeId) {
            $this->notifyTaskAssigned($task->fresh());
        }
        $this->logTaskActivity(
            $task,
            'updated',
            $oldStatus,
            (string) $task->status,
            'Đã cập nhật nội dung, người phụ trách hoặc thời hạn công việc.',
            ['old_assignee_id' => $oldAssigneeId, 'assignee_id' => $mainAssigneeId]
        );

        $createdCount = 0;
        foreach ($assigneeIds->skip(1) as $assigneeId) {
            $taskData = $updateData;
            $taskData['assignee_id'] = $assigneeId;
            $taskData['requester_id'] = $task->requester_id ?: $user->id;
            $taskData['progress_percent'] = 0;
            $newTask = Task::create($this->filterColumns('tasks', $taskData));
            $this->storeAttachments($request, $newTask, 'attachments', 'task');
            $this->notifyTaskAssigned($newTask);
            $this->logTaskActivity($newTask, 'assigned', null, (string) $newTask->status, 'Giao thêm công việc cùng nội dung cho nhân viên khác.');
            $createdCount++;
        }

        return $createdCount > 0
            ? redirect()->route('tasks.index')->with('success', 'Đã cập nhật công việc và giao thêm cho '.$createdCount.' người.')
            : redirect()->route('tasks.show', $task)->with('success', 'Đã cập nhật công việc.');
    }

    /**
     * Người nhận việc cập nhật trạng thái và tiến độ.
     */
    public function updateStatus(Request $request, Task $task)
    {
        if ((int) $task->assignee_id !== (int) auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'status' => 'required|in:new,in_progress',
            'progress_percent' => 'nullable|integer|min:0|max:100',
        ]);

        $oldStatus = (string) ($task->status ?? '');
        $task->status = $data['status'];
        $task->progress_percent = (int) ($data['progress_percent'] ?? $task->progress_percent);
        $task->save();
        $this->logTaskActivity(
            $task,
            'progress_updated',
            $oldStatus,
            (string) $task->status,
            'Nhân viên cập nhật tiến độ '.$task->progress_percent.'%.',
            ['progress_percent' => $task->progress_percent]
        );

        return back()->with('success', 'Đã cập nhật tiến độ công việc.');
    }

    /* EGO_BOSS_TASK_PERMISSION_START */
    /**
     * Kiểm tra sếp/quản lý có quyền chỉnh sửa công việc.
     */
    private function canBossEditTask(Task $task): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((int) ($task->requester_id ?? 0) === (int) $user->id) {
            return true;
        }

        if ($this->canAssign($user)) {
            return true;
        }

        $bossRoles = [
            'admin',
            'manager',
            'management',
            'director',
            'general_director',
            'ban_giam_doc',
            'giam_doc',
            'ceo',
            'assistant',
            'tro_ly',
            'sales_manager',
            'marketing_manager',
            'technical_manager',
            'technical_leader',
            'truong_phong_ky_thuat',
            'accounting',
            'ke_toan',
        ];

        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole($bossRoles)) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($user, 'hasRole')) {
            try {
                foreach ($bossRoles as $role) {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($user, 'getRoleNames')) {
            try {
                $roleNames = $user->getRoleNames()->map(fn ($r) => strtolower((string) $r))->toArray();

                foreach ($bossRoles as $role) {
                    if (in_array($role, $roleNames, true)) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $roleValue = strtolower((string) ($user->role ?? $user->type ?? $user->position ?? ''));
        $textValue = strtolower(
            (string) ($user->email ?? '').' '.
            (string) ($user->name ?? '').' '.
            $roleValue
        );

        foreach ($bossRoles as $role) {
            if ($roleValue === $role || str_contains($textValue, $role)) {
                return true;
            }
        }

        if (
            str_contains($textValue, 'giám đốc') ||
            str_contains($textValue, 'giam doc') ||
            str_contains($textValue, 'ban giám đốc') ||
            str_contains($textValue, 'ban giam doc')
        ) {
            return true;
        }

        return false;
    }
    /* EGO_BOSS_TASK_PERMISSION_END */

    /**
     * Nộp kết quả công việc để chờ duyệt (hỗ trợ nộp lại sau khi bị trả).
     */
    public function submitResult(Request $request, Task $task)
    {
        if ($request->isMethod('get')) {
            return redirect()->route('tasks.show', $task)->with('error', 'Vui lòng nộp kết quả bằng form trong trang chi tiết công việc.');
        }

        $authId = (int) auth()->id();
        $isAssignee = (int) ($task->assignee_id ?? 0) === $authId;
        $isRequester = (int) ($task->requester_id ?? 0) === $authId;
        $canManage = $isRequester || $this->canAssign(auth()->user());
        abort_unless($isAssignee || $canManage, 403);

        $oldStatus = (string) ($task->status ?? '');
        $technical = (string) ($task->task_type ?? '') === 'technical';
        $data = $request->validate([
            'result_note' => $technical ? 'required|string' : 'nullable|string',
            'issue_note' => 'nullable|string|max:10000',
            'actual_minutes' => $technical ? 'required|integer|min:1|max:100000' : 'nullable|integer|min:1|max:100000',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'result_attachments.*' => 'nullable|file|max:51200',
            'clear_result_attachments' => 'nullable|boolean',
        ]);

        $task->result_note = $data['result_note'] ?? null;
        if (Schema::hasColumn('tasks', 'issue_note')) {
            $task->issue_note = $data['issue_note'] ?? null;
        }
        if (Schema::hasColumn('tasks', 'actual_minutes')) {
            $task->actual_minutes = $data['actual_minutes'] ?? null;
        }
        $task->progress_percent = (int) ($data['progress_percent'] ?? 100);
        $task->status = 'submitted';
        $task->completed_at = now();
        if (in_array($oldStatus, ['revision', 'rejected'], true) && Schema::hasColumn('tasks', 'resubmitted_at')) {
            $task->resubmitted_at = now();
        }
        $task->save();

        if ($request->boolean('clear_result_attachments') && $this->tableExists('task_attachments')) {
            $oldFiles = DB::table('task_attachments')->where('task_id', $task->id)->where('type', 'result')->get();
            foreach ($oldFiles as $oldFile) {
                if (! empty($oldFile->file_path)) {
                    Storage::disk('public')->delete($oldFile->file_path);
                }
            }
            DB::table('task_attachments')->where('task_id', $task->id)->where('type', 'result')->delete();
        }

        $this->storeAttachments($request, $task, 'result_attachments', 'result');
        $this->logTaskActivity(
            $task,
            in_array($oldStatus, ['revision', 'rejected'], true) ? 'resubmitted' : 'submitted',
            $oldStatus,
            'submitted',
            $data['result_note'] ?? 'Nhân viên đã nộp kết quả công việc.',
            ['actual_minutes' => $data['actual_minutes'] ?? null, 'progress_percent' => $task->progress_percent]
        );
        return back()->with('success', in_array($oldStatus, ['revision', 'rejected'], true)
            ? 'Đã nộp lại kết quả, chờ người duyệt xác nhận.'
            : 'Đã nộp kết quả, chờ người duyệt xác nhận.');
    }

    /* EGO_TASK_DELETE_ATTACHMENT_START */

    /**
     * Thay file đính kèm cũ bằng file mới.
     */
    public function replaceAttachment(Request $request, Task $task, $attachment)
    {
        if (! $this->tableExists('task_attachments')) {
            abort(404);
        }

        $file = DB::table('task_attachments')
            ->where('id', (int) $attachment)
            ->where('task_id', $task->id)
            ->first();

        abort_unless($file, 404);

        $isRequester = (int) ($task->requester_id ?? 0) === (int) auth()->id();
        $isUploader = (int) ($file->uploaded_by ?? 0) === (int) auth()->id();
        $canManage = $isRequester || $this->canAssign(auth()->user());

        abort_unless($canManage || $isUploader, 403);

        $data = $request->validate([
            'file' => 'required|file|max:51200',
        ], [
            'file.required' => 'Vui lòng chọn file mới.',
            'file.file' => 'File không hợp lệ.',
            'file.max' => 'File tối đa 50MB.',
        ]);

        $uploadedFile = $request->file('file');

        if (! empty($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        $path = $uploadedFile->store('task_attachments/'.$task->id, 'public');

        DB::table('task_attachments')
            ->where('id', (int) $attachment)
            ->where('task_id', $task->id)
            ->update($this->filterColumns('task_attachments', [
                'uploaded_by' => auth()->id(),
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $path,
                'file_mime' => $uploadedFile->getClientMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'updated_at' => now(),
            ]));

        return back()->with('success', 'Đã thay file thành công.');
    }

    /**
     * Xóa file đính kèm theo quyền của người dùng.
     */
    public function destroyAttachment(Task $task, $attachment)
    {
        if (! $this->tableExists('task_attachments')) {
            abort(404);
        }

        $file = DB::table('task_attachments')
            ->where('id', (int) $attachment)
            ->where('task_id', $task->id)
            ->first();

        abort_unless($file, 404);

        $isOwner = (int) $task->assignee_id === (int) auth()->id();
        $isRequester = (int) $task->requester_id === (int) auth()->id();
        $canManage = $isRequester || $this->canAssign(auth()->user());

        $fileType = (string) ($file->type ?? 'task');
        $isResultFile = $fileType === 'result';

        /*
         * Người giao việc / Admin / Quản lý: được xóa mọi file của công việc.
         * Người nhận việc: chỉ được xóa file kết quả của mình khi công việc chưa duyệt.
         */
        if (! $canManage) {
            abort_unless($isOwner && $isResultFile && $task->status !== 'approved', 403);
        }

        if (! $canManage && $isOwner && $task->status === 'approved') {
            return back()->with('error', 'Công việc đã được duyệt nên không thể xóa file kết quả.');
        }

        if (! empty($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        DB::table('task_attachments')
            ->where('id', (int) $attachment)
            ->where('task_id', $task->id)
            ->delete();

        return back()->with('success', $isResultFile ? 'Đã xóa file kết quả.' : 'Đã xóa file giao việc.');
    }
    /* EGO_TASK_DELETE_ATTACHMENT_END */

    /**
     * Trả hồ sơ yêu cầu nhân viên sửa đổi/bổ sung và nộp lại.
     */
    public function returnRevision(Request $request, Task $task)
    {
        if ((int) $task->requester_id !== (int) auth()->id() && ! $this->canAssign(auth()->user())) {
            abort(403);
        }

        $data = $request->validate([
            'revision_reason' => 'required|string|max:5000',
            'revision_attachments.*' => 'nullable|file|max:51200',
        ], [
            'revision_reason.required' => 'Vui lòng nhập lý do từ chối / yêu cầu sửa đổi bổ sung.',
            'revision_attachments.*.file' => 'File đính kèm không hợp lệ.',
            'revision_attachments.*.max' => 'File đính kèm tối đa 50MB/file.',
        ]);

        $reason = trim((string) $data['revision_reason']);
        $oldStatus = (string) ($task->status ?? '');

        $task->status = 'revision';
        $task->completed_at = null;

        if ((int) ($task->progress_percent ?? 0) >= 100) {
            $task->progress_percent = 90;
        }

        if (Schema::hasColumn('tasks', 'revision_reason')) {
            $task->revision_reason = $reason;
        }

        if (Schema::hasColumn('tasks', 'revision_requested_by')) {
            $task->revision_requested_by = auth()->id();
        }

        if (Schema::hasColumn('tasks', 'revision_requested_at')) {
            $task->revision_requested_at = now();
        }

        if (Schema::hasColumn('tasks', 'rejection_reason')) {
            $task->rejection_reason = $reason;
        }

        if (Schema::hasColumn('tasks', 'rejected_by')) {
            $task->rejected_by = auth()->id();
        }

        if (Schema::hasColumn('tasks', 'rejected_at')) {
            $task->rejected_at = now();
        }

        $task->save();

        // File Admin/Sếp gắn khi trả hồ sơ
        if ($request->hasFile('revision_attachments')) {
            $this->storeAttachments($request, $task, 'revision_attachments', 'revision');
        }
        $this->logTaskActivity($task, 'revision_requested', $oldStatus, 'revision', $reason);

        return back()->with('success', 'Đã trả hồ sơ để nhân viên sửa đổi / bổ sung và nộp lại.');
    }

    /**
     * Duyệt hoàn thành công việc đã nộp.
     */
    public function approve(Task $task)
    {
        $user = auth()->user();
        $isSpecificApprover = (int) ($task->approver_id ?? 0) === (int) $user->id;
        $isRequester = (int) $task->requester_id === (int) $user->id;
        $isExecutive = $this->isExecutiveTaskManager($user);
        abort_unless($isSpecificApprover || $isRequester || $isExecutive || ($this->canAssign($user) && empty($task->approver_id)), 403);

        if ($task->status !== 'submitted') {
            return back()->with('error', 'Công việc chưa được nộp kết quả.');
        }

        $oldStatus = (string) ($task->status ?? '');
        $task->status = 'approved';
        $task->progress_percent = 100;
        $task->save();
        $this->logTaskActivity($task, 'approved', $oldStatus, 'approved', 'Người duyệt xác nhận công việc hoàn thành.');

        return back()->with('success', 'Đã duyệt hoàn thành công việc.');
    }

    /**
     * Xóa công việc và toàn bộ file đính kèm.
     */
    public function destroy(Task $task)
    {
        if ((int) $task->requester_id !== (int) auth()->id() && ! $this->canAssign(auth()->user())) {
            abort(403);
        }

        if ($this->tableExists('task_attachments')) {
            $attachments = DB::table('task_attachments')
                ->where('task_id', $task->id)
                ->get();

            foreach ($attachments as $file) {
                Storage::disk('public')->delete($file->file_path);
            }

            DB::table('task_attachments')
                ->where('task_id', $task->id)
                ->delete();
        }

        if (! empty($task->attachment_path)) {
            Storage::disk('public')->delete($task->attachment_path);
        }

        if (! empty($task->result_attachment_path)) {
            Storage::disk('public')->delete($task->result_attachment_path);
        }

        $task->delete();

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Đã xóa công việc.');
    }
}
