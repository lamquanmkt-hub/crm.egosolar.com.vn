<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Tasks\WeeklyTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class WeeklyTaskController extends Controller
{
    private function normStatus(?string $s): string
    {
        $s = strtolower(trim((string)$s));
        // đồng bộ todo -> pending
        if ($s === 'todo') $s = 'pending';
        if (!in_array($s, ['pending', 'doing', 'done'], true)) $s = 'pending';
        return $s;
    }

    private function normPriority(?string $p): string
    {
        $p = strtolower(trim((string)$p));
        if (!in_array($p, ['high', 'medium', 'low'], true)) $p = 'high';
        return $p;
    }

    private function parseList($v): array
    {
        // nhận array hoặc string "a, b\nc"
        if (is_array($v)) {
            $arr = $v;
        } else {
            $str = trim((string)$v);
            if ($str === '') return [];
            $arr = preg_split('/[\n,]+/u', $str);
        }
        $arr = array_values(array_filter(array_map('trim', $arr)));
        // unique
        $arr = array_values(array_unique($arr));
        return $arr;
    }

    private function handleUploads(Request $request, array $old = []): array
    {
        $attachments = is_array($old) ? $old : [];

        if (!$request->hasFile('attachments')) return $attachments;

        foreach ((array)$request->file('attachments') as $file) {
            if (!$file || !$file->isValid()) continue;

            $path = $file->store('weekly_tasks/' . date('Y/m'), 'public');

            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        return $attachments;
    }

    public function index(Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : null;
        $to   = $request->filled('to')   ? Carbon::parse($request->to)->endOfDay()   : null;

        $category = trim((string)$request->get('category', ''));
        $status   = strtolower(trim((string)$request->get('status', '')));

        $categories = WeeklyTask::query()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $q = WeeklyTask::query();

        // lọc theo start_date
        if ($from) $q->whereDate('start_date', '>=', $from->toDateString());
        if ($to)   $q->whereDate('start_date', '<=', $to->toDateString());

        if ($category !== '') $q->where('category', $category);

        if (in_array($status, ['pending','doing','done'], true)) {
            $q->where('status', $status);
        }

        $tasks = $q->orderByRaw("CASE WHEN due_date IS NULL THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();

        $total = $tasks->count();
        $done  = $tasks->where('status', 'done')->count();
        $doing = $tasks->where('status', 'doing')->count();

        $today = Carbon::today();
        $overdue = $tasks->filter(function ($t) use ($today) {
            if (empty($t->due_date)) return false;
            return Carbon::parse($t->due_date)->lt($today) && strtolower((string)$t->status) !== 'done';
        })->count();

        $priorityCount = [
            'high'   => $tasks->where('priority', 'high')->count(),
            'medium' => $tasks->where('priority', 'medium')->count(),
            'low'    => $tasks->where('priority', 'low')->count(),
        ];

        return view('marketing.reports.weekly_tasks', compact(
            'tasks', 'total', 'done', 'doing', 'overdue', 'priorityCount', 'categories'
        ));
    }

    public function create()
    {
        return view('marketing.reports.weekly_tasks_create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'      => ['required','string','max:255'],
            'priority'   => ['nullable','string'],
            'category'   => ['nullable','string','max:255'],
            'assignee'   => ['nullable','string','max:255'], // fallback
            'assignees'  => ['nullable'], // array/string đều ok
            'start_date' => ['nullable','date'],
            'due_date'   => ['nullable','date'],
            'status'     => ['nullable','string'],
            'progress'   => ['nullable','integer','min:0','max:100'],
            'note'       => ['nullable','string'],
            'links'      => ['nullable'],
            'attachments'=> ['nullable'],
        ]);

        $assignees = $this->parseList($request->input('assignees', []));
        // nếu user chỉ nhập 1 ô assignee
        $fallbackAssignee = trim((string)($data['assignee'] ?? ''));
        if ($fallbackAssignee !== '') $assignees = array_values(array_unique(array_merge($assignees, [$fallbackAssignee])));

        $links = $this->parseList($request->input('links', []));
        $attachments = $this->handleUploads($request, []);

        $payload = [
            'title'      => $data['title'],
            'priority'   => $this->normPriority($data['priority'] ?? 'high'),
            'category'   => $data['category'] ?? null,
            'assignee'   => implode(', ', $assignees),
            'start_date' => $data['start_date'] ?? null,
            'due_date'   => $data['due_date'] ?? null,
            'status'     => $this->normStatus($data['status'] ?? 'pending'),
            'progress'   => (int)($data['progress'] ?? 0),
            'note'       => $data['note'] ?? null,
        ];

        // ✅ tránh lỗi thiếu cột (tự tương thích DB)
        if (Schema::hasColumn('weekly_tasks', 'assignees'))   $payload['assignees'] = $assignees;
        if (Schema::hasColumn('weekly_tasks', 'links'))       $payload['links'] = $links;
        if (Schema::hasColumn('weekly_tasks', 'attachments')) $payload['attachments'] = $attachments;

        WeeklyTask::create($payload);

        return redirect()
            ->route('marketing.reports.weekly-tasks')
            ->with('success', 'Đã tạo công việc');
    }

    // ✅ CHI TIẾT
    public function show($id)
    {
        $task = WeeklyTask::findOrFail($id);
        return view('marketing.reports.weekly_tasks_show', compact('task'));
    }

    public function edit($id)
    {
        $task = WeeklyTask::findOrFail($id);
        return view('marketing.reports.weekly_tasks_edit', compact('task'));
    }

    public function update(Request $request, $id)
    {
        $task = WeeklyTask::findOrFail($id);

        $data = $request->validate([
            'title'      => ['required','string','max:255'],
            'priority'   => ['nullable','string'],
            'category'   => ['nullable','string','max:255'],
            'assignee'   => ['nullable','string','max:255'],
            'assignees'  => ['nullable'],
            'start_date' => ['nullable','date'],
            'due_date'   => ['nullable','date'],
            'status'     => ['nullable','string'],
            'progress'   => ['nullable','integer','min:0','max:100'],
            'note'       => ['nullable','string'],
            'links'      => ['nullable'],
            'attachments'=> ['nullable'],
        ]);

        $assignees = $this->parseList($request->input('assignees', []));
        $fallbackAssignee = trim((string)($data['assignee'] ?? ''));
        if ($fallbackAssignee !== '') $assignees = array_values(array_unique(array_merge($assignees, [$fallbackAssignee])));

        $links = $this->parseList($request->input('links', []));

        $oldAttachments = $task->attachments ?? [];
        $attachments = $this->handleUploads($request, is_array($oldAttachments) ? $oldAttachments : []);

        $payload = [
            'title'      => $data['title'],
            'priority'   => $this->normPriority($data['priority'] ?? $task->priority),
            'category'   => $data['category'] ?? null,
            'assignee'   => implode(', ', $assignees),
            'start_date' => $data['start_date'] ?? null,
            'due_date'   => $data['due_date'] ?? null,
            'status'     => $this->normStatus($data['status'] ?? $task->status),
            'progress'   => (int)($data['progress'] ?? ($task->progress ?? 0)),
            'note'       => $data['note'] ?? null,
        ];

        if (Schema::hasColumn('weekly_tasks', 'assignees'))   $payload['assignees'] = $assignees;
        if (Schema::hasColumn('weekly_tasks', 'links'))       $payload['links'] = $links;
        if (Schema::hasColumn('weekly_tasks', 'attachments')) $payload['attachments'] = $attachments;

        $task->update($payload);

        return redirect()
            ->route('marketing.reports.weekly-tasks')
            ->with('success', 'Đã cập nhật công việc #' . $id);
    }

    public function destroy($id)
    {
        $task = WeeklyTask::findOrFail($id);

        $attachments = $task->attachments ?? [];
        if (is_array($attachments)) {
            foreach ($attachments as $a) {
                $path = $a['path'] ?? null;
                if ($path) {
                    try {
                        Storage::disk('public')->delete($path);
                    } catch (\Throwable $e) {
                        // bỏ qua
                    }
                }
            }
        }

        $task->delete();

        return redirect()
            ->route('marketing.reports.weekly-tasks')
            ->with('success', 'Đã xoá công việc #' . $id);
    }
}