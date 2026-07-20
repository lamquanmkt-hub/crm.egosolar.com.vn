<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Tasks\Task;
use App\Models\User;
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

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole([
                'admin',
                'manager',
                'sales_manager',
                'marketing_manager',
                'accounting',
                'management',
                'director',
                'general_director',
                'ban_giam_doc',
                'giam_doc',
            ]);
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole([
                'admin',
                'manager',
                'sales_manager',
                'marketing_manager',
                'accounting',
                'management',
                'director',
                'general_director',
                'ban_giam_doc',
                'giam_doc',
            ]);
        }

        $role = strtolower((string) ($user->role ?? ''));

        return in_array($role, [
            'admin',
            'manager',
            'sales_manager',
            'marketing_manager',
            'accounting',
            'management',
            'director',
            'general_director',
            'ban_giam_doc',
            'giam_doc',
        ]);
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
     * Lưu file đính kèm của công việc theo loại (giao việc/kết quả/trả hồ sơ).
     */
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
        if (! $this->canAssign(auth()->user())) {
            abort(403);
        }

        $allTasks = Task::with(['assignee.department', 'requester'])
            ->latest()
            ->get();

        $summary = [
            'total' => Task::count(),
            'new' => Task::where('status', 'new')->count(),
            'in_progress' => Task::where('status', 'in_progress')->count(),
            'submitted' => Task::where('status', 'submitted')->count(),
            'approved' => Task::where('status', 'approved')->count(),
        ];

        $departments = collect();

        if (Schema::hasTable('departments')) {
            $departments = Department::orderBy('name')->get();
        }

        $taskGroups = collect();

        foreach ($departments as $department) {
            $departmentTasks = $allTasks->filter(function ($task) use ($department) {
                return (int) optional($task->assignee)->department_id === (int) $department->id;
            })->values();

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

        $noDepartmentTasks = $allTasks->filter(function ($task) {
            return empty(optional($task->assignee)->department_id);
        })->values();

        if ($noDepartmentTasks->count() > 0 || $departments->count() === 0) {
            $taskGroups->push([
                'id' => 0,
                'name' => 'Chưa có phòng ban',
                'tasks' => $noDepartmentTasks,
                'total' => $noDepartmentTasks->count(),
                'new' => $noDepartmentTasks->where('status', 'new')->count(),
                'in_progress' => $noDepartmentTasks->where('status', 'in_progress')->count(),
                'submitted' => $noDepartmentTasks->where('status', 'submitted')->count(),
                'approved' => $noDepartmentTasks->where('status', 'approved')->count(),
            ]);
        }

        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $canBossEdit = $this->canAssign(auth()->user());

        return view('tasks.index', compact('allTasks', 'taskGroups', 'summary', 'priorities', 'statuses'));
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
        if (! $this->canAssign(auth()->user())) {
            abort(403);
        }

        $users = User::orderBy('name')->get();
        $priorities = $this->priorities();
        $statuses = $this->statuses();

        return view('tasks.create', compact('users', 'priorities', 'statuses'));
    }

    /**
     * Tạo công việc cho từng người được giao và gửi thông báo.
     */
    public function store(Request $request)
    {
        if (! $this->canAssign(auth()->user())) {
            abort(403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignee_ids' => 'required|array|min:1',
            'assignee_ids.*' => 'required|integer|exists:users,id',
            'priority' => 'required|in:low,medium,high',
            'due_at' => 'nullable|date',
            'link_url' => 'nullable|url|max:2048',
            'attachments.*' => 'nullable|file',
        ]);

        $assigneeIds = collect($data['assignee_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $createdTasks = collect();

        foreach ($assigneeIds as $assigneeId) {
            $taskData = $data;
            unset($taskData['assignee_ids']);

            $taskData['assignee_id'] = $assigneeId;
            $taskData['requester_id'] = auth()->id();
            $taskData['status'] = 'new';
            $taskData['progress_percent'] = 0;

            $task = Task::create($this->filterColumns('tasks', $taskData));
            $createdTasks->push($task);

            $this->storeAttachments($request, $task, 'attachments', 'task');
            $this->notifyTaskAssigned($task);
        }

        if ($createdTasks->count() === 1) {
            return redirect()
                ->route('tasks.show', $createdTasks->first())
                ->with('success', 'Đã giao việc thành công.');
        }

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Đã giao việc cho '.$createdTasks->count().' người.');
    }

    /**
     * Hiển thị chi tiết công việc kèm các task cùng nhóm giao nhiều người.
     */
    public function show(Task $task)
    {
        $isOwner = (int) $task->assignee_id === (int) auth()->id();
        $isRequester = (int) $task->requester_id === (int) auth()->id();

        if (! $isOwner && ! $isRequester && ! $this->canAssign(auth()->user())) {
            abort(403);
        }

        $task->load(['assignee', 'requester']);

        /*
         * Khi giao cho nhiều người, hệ thống tạo nhiều task riêng.
         * Trang chi tiết sẽ gom các task cùng nhóm để hiển thị đầy đủ người nhận.
         * Ưu tiên gom theo task_group_id nếu có cột này.
         * Nếu chưa có task_group_id thì gom theo tiêu đề + người giao + hạn + mô tả.
         */
        $relatedTasksQuery = Task::with('assignee');

        if (
            Schema::hasColumn('tasks', 'task_group_id')
            && ! empty($task->task_group_id)
        ) {
            $relatedTasksQuery->where('task_group_id', $task->task_group_id);
        } else {
            $relatedTasksQuery
                ->where('title', $task->title)
                ->where('requester_id', $task->requester_id)
                ->where(function ($q) use ($task) {
                    if (empty($task->due_at)) {
                        $q->whereNull('due_at');
                    } else {
                        $q->where('due_at', $task->due_at);
                    }
                })
                ->where(function ($q) use ($task) {
                    if (empty($task->description)) {
                        $q->whereNull('description')->orWhere('description', '');
                    } else {
                        $q->where('description', $task->description);
                    }
                });
        }

        $relatedTasks = $relatedTasksQuery
            ->orderBy('id')
            ->get();

        if ($relatedTasks->isEmpty()) {
            $relatedTasks = collect([$task]);
        }

        $attachments = $this->attachmentsFor($task);
        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $canApprove = $this->canAssign(auth()->user()) || $isRequester;
        $canUpdate = $isOwner;

        return view('tasks.show', compact(
            'task',
            'relatedTasks',
            'attachments',
            'priorities',
            'statuses',
            'canApprove',
            'canUpdate'
        ));
    }

    /**
     * Hiển thị form sửa công việc.
     */
    public function edit(Task $task)
    {
        if (! $this->canAssign(auth()->user()) && (int) $task->requester_id !== (int) auth()->id()) {
            abort(403);
        }

        $users = User::orderBy('name')->get();
        $priorities = $this->priorities();
        $statuses = $this->statuses();

        return view('tasks.edit', compact('task', 'users', 'priorities', 'statuses'));
    }

    /**
     * Cập nhật công việc và giao thêm cho người nhận mới nếu có.
     */
    public function update(Request $request, Task $task)
    {
        if (! $this->canAssign(auth()->user()) && (int) $task->requester_id !== (int) auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignee_ids' => 'required|array|min:1',
            'assignee_ids.*' => 'required|integer|exists:users,id',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:new,in_progress,submitted,approved',
            'due_at' => 'nullable|date',
            'link_url' => 'nullable|url|max:2048',
            'attachments.*' => 'nullable|file',
        ]);

        $assigneeIds = collect($data['assignee_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $mainAssigneeId = (int) $assigneeIds->first();
        $oldAssigneeId = (int) ($task->assignee_id ?? 0);

        $updateData = $data;
        unset($updateData['assignee_ids']);
        $updateData['assignee_id'] = $mainAssigneeId;

        $task->update($this->filterColumns('tasks', $updateData));
        $this->storeAttachments($request, $task, 'attachments', 'task');

        if ($oldAssigneeId !== $mainAssigneeId) {
            $this->notifyTaskAssigned($task->fresh());
        }

        $createdCount = 0;

        foreach ($assigneeIds->skip(1) as $assigneeId) {
            $taskData = $updateData;
            $taskData['assignee_id'] = $assigneeId;
            $taskData['requester_id'] = $task->requester_id ?: auth()->id();
            $taskData['status'] = $data['status'] ?? 'new';
            $taskData['progress_percent'] = 0;

            $newTask = Task::create($this->filterColumns('tasks', $taskData));
            $this->storeAttachments($request, $newTask, 'attachments', 'task');
            $this->notifyTaskAssigned($newTask);
            $createdCount++;
        }

        if ($createdCount > 0) {
            return redirect()
                ->route('tasks.index')
                ->with('success', 'Đã cập nhật công việc và giao thêm cho '.$createdCount.' người.');
        }

        return redirect()
            ->route('tasks.show', $task)
            ->with('success', 'Đã cập nhật công việc.');
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

        $task->status = $data['status'];
        $task->progress_percent = (int) ($data['progress_percent'] ?? $task->progress_percent);
        $task->save();

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
            return redirect()
                ->route('tasks.show', $task)
                ->with('error', 'Vui lòng nộp kết quả bằng form trong trang chi tiết công việc.');
        }

        $authId = (int) auth()->id();
        $isAssignee = (int) ($task->assignee_id ?? 0) === $authId;
        $isRequester = (int) ($task->requester_id ?? 0) === $authId;
        $canManage = $isRequester || $this->canAssign(auth()->user());

        abort_unless($isAssignee || $canManage, 403);

        $oldStatus = (string) ($task->status ?? '');

        $data = $request->validate([
            'result_note' => 'nullable|string',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'result_attachments.*' => 'nullable|file',
            'clear_result_attachments' => 'nullable|boolean',
        ]);

        $task->result_note = $data['result_note'] ?? null;
        $task->progress_percent = (int) ($data['progress_percent'] ?? 100);
        $task->status = 'submitted';
        $task->completed_at = now();

        if (in_array($oldStatus, ['revision', 'rejected'], true)) {
            if (Schema::hasColumn('tasks', 'resubmitted_at')) {
                $task->resubmitted_at = now();
            }
        }

        $task->save();

        if ($request->boolean('clear_result_attachments') && $this->tableExists('task_attachments')) {
            $oldFiles = DB::table('task_attachments')
                ->where('task_id', $task->id)
                ->where('type', 'result')
                ->get();

            foreach ($oldFiles as $oldFile) {
                if (! empty($oldFile->file_path)) {
                    Storage::disk('public')->delete($oldFile->file_path);
                }
            }

            DB::table('task_attachments')
                ->where('task_id', $task->id)
                ->where('type', 'result')
                ->delete();
        }

        $this->storeAttachments($request, $task, 'result_attachments', 'result');

        return back()->with('success', in_array($oldStatus, ['revision', 'rejected'], true)
            ? 'Đã nộp lại kết quả, chờ sếp duyệt lại.'
            : 'Đã nộp kết quả, chờ sếp duyệt.');
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

        return back()->with('success', 'Đã trả hồ sơ để nhân viên sửa đổi / bổ sung và nộp lại.');
    }

    /**
     * Duyệt hoàn thành công việc đã nộp.
     */
    public function approve(Task $task)
    {
        if ((int) $task->requester_id !== (int) auth()->id() && ! $this->canAssign(auth()->user())) {
            abort(403);
        }

        if ($task->status !== 'submitted') {
            return back()->with('success', 'Công việc chưa được nộp kết quả.');
        }

        $task->status = 'approved';
        $task->progress_percent = 100;
        $task->save();

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
