<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Content\ContentCalendar;
use App\Models\ContentFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Marketing\MarketingKpiPayActual;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Controller lịch biên tập nội dung: CRUD, feedback, số liệu tuần và đồng bộ KPI lương.
 */
class ContentCalendarController extends Controller
{
    /**
     * ✅ Chuẩn hoá nhóm content_type để tránh LIKE dính dấu/collation
     */
    private function typeGroups(): array
    {
        return [
            'post'   => ['Bài viết'],
            'ai'     => ['Video', 'Video AI'],
            'review' => ['Video Review'],
            'trend'  => ['Trend Video'],
            'live'   => ['Livestream'],
        ];
    }

    /**
     * ✅ Parse date flexible:
     * - hỗ trợ "Y-m-d" (2026-02-23) và "d/m/Y" (23/02/2026)
     */
    private function parseDateFlexible(string $date): Carbon
    {
        $date = trim($date);

        // dd/mm/yyyy
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }

        // yyyy-mm-dd (or anything Carbon can parse)
        return Carbon::parse($date);
    }

    /**
     * Lấy danh sách user thuộc role marketing/marketing_manager để dùng cho dropdown "Phụ trách".
     */
    protected function getMarketingUsers()
    {
        return User::query()
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('roles.name', ['marketing', 'marketing_manager'])
            ->select('users.id', 'users.name', 'users.email')
            ->distinct()
            ->orderBy('users.name')
            ->get();
    }

    /**
     * Danh sách nội dung lịch biên tập kèm danh sách user marketing phụ trách.
     */
    public function index()
    {
        $items = ContentCalendar::with('files')
            ->orderBy('publish_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $marketingUsers = $this->getMarketingUsers();
        $marketingUserMap = $marketingUsers->keyBy('id');

        return view('marketing.reports.content_calendar', compact('items', 'marketingUsers', 'marketingUserMap'));
    }

    /**
     * Tạo nội dung mới, gán người phụ trách, lưu file đính kèm và đồng bộ KPI lương nếu có.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'publish_date'  => 'required|date',
            'platform'      => 'required|string',
            'content_type'  => 'required|string',
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'full_content'  => 'nullable|string',
            'status'        => 'required|string',
            'campaign_id'   => 'nullable',
            'link'          => 'nullable|url|max:2048',
            'attachment'    => 'nullable|file|max:10240',

            'assignees'     => 'nullable|array',
            'assignees.*'   => 'nullable|string|max:255',

            'assignee_user_id' => 'nullable|integer|exists:users,id',
            'assignee'      => 'nullable|string|max:255',
        ]);

        $assignees = $this->normalizeAssignees($request);
        $assigneeJoined = implode(', ', $assignees);

        $assigneeUserId = $request->input('assignee_user_id');
        $assigneeUserName = null;
        if ($assigneeUserId) {
            $u = User::find($assigneeUserId);
            $assigneeUserName = $u?->name;
        }

        if ($assigneeUserName) {
            $assignees = [$assigneeUserName];
            $assigneeJoined = $assigneeUserName;
        }

        $item = ContentCalendar::create([
            'publish_date' => $validated['publish_date'],
            'platform'     => $validated['platform'],
            'content_type' => $validated['content_type'],
            'title'        => $validated['title'],
            'description'  => $validated['description'] ?? null,
            'full_content' => $validated['full_content'] ?? null,
            'campaign_id'  => $validated['campaign_id'] ?? null,
            'created_by'   => Auth::id(),
            'status'       => $validated['status'],
            'link'         => $validated['link'] ?? null,

            'assignee_user_id' => $assigneeUserId ?: null,
            'assignee'     => $assigneeJoined ?: null,
            'assignees'    => count($assignees) ? json_encode($assignees, JSON_UNESCAPED_UNICODE) : null,
        ]);

        // ✅ Sync KPI payroll nếu có assignee
        if (!empty($assigneeUserId)) {
            $period = Carbon::parse($validated['publish_date'])->format('Y-m');
            $this->syncKpiPayrollFromCalendarByMonth($period, (int)$assigneeUserId);
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('content_files', 'public');
            if (method_exists($item, 'files')) {
                $item->files()->create([
                    'file_path'   => $path,
                    'file_name'   => $file->getClientOriginalName(),
                    'file_type'   => $file->getClientMimeType(),
                    'uploaded_by' => auth()->id(),
                ]);
            } else {
                $item->attachment_path = $path;
                $item->save();
            }
        }

        return back()->with('success', 'Đã thêm nội dung');
    }

    /**
     * ✅ FEEDBACK (Facebook-like) - dùng bảng content_feedbacks (Eloquent)
     * Route: POST /marketing/reports/content-calendar/{id}/feedback
     */
    public function storeFeedback(Request $request, $id)
    {
        if (!Schema::hasTable('content_feedbacks')) {
            return back()->with('error', 'Chưa có bảng content_feedbacks. Hãy chạy migrate.');
        }

        $request->validate([
            'message'    => 'required|string',
            'parent_id'  => 'nullable|integer',
            'image'      => 'nullable|image|max:4096',
        ]);

        $item = ContentCalendar::findOrFail($id);

        // Validate parent_id thuộc cùng content_calendar_id (nếu có)
        $parentId = $request->input('parent_id');
        if (!empty($parentId)) {
            $parent = ContentFeedback::query()
                ->where('id', (int)$parentId)
                ->where('content_calendar_id', (int)$item->id)
                ->first();

            if (!$parent) {
                return back()->with('error', 'Comment cha không hợp lệ.');
            }
        }

        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('feedback', 'public');
        }

        ContentFeedback::create([
            'content_calendar_id' => (int)$item->id,
            'user_id'             => Auth::id(),
            'parent_id'           => $parentId ? (int)$parentId : null,
            'message'             => $request->message,
            'image_path'          => $path,
        ]);

        return back()->with('success', 'Đã gửi feedback');
    }

    /**
     * Cập nhật nội dung feedback (chỉ chủ sở hữu được sửa).
     */
    public function updateFeedback(Request $request, $fbId)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $fb = ContentFeedback::with('user')->find($fbId);
        if (!$fb) return back()->with('error', 'Feedback không tồn tại');

        if ((int)$fb->user_id !== (int)Auth::id()) {
            return back()->with('error', 'Bạn không có quyền sửa feedback này');
        }

        $fb->message = $request->message;
        $fb->save();

        return back()->with('success', 'Đã cập nhật feedback');
    }

    /**
     * Xoá feedback cùng replies con và ảnh đính kèm (chỉ chủ sở hữu được xoá).
     */
    public function deleteFeedback($fbId)
    {
        $fb = ContentFeedback::find($fbId);
        if (!$fb) return back()->with('error', 'Feedback không tồn tại');

        if ((int)$fb->user_id !== (int)Auth::id()) {
            return back()->with('error', 'Bạn không có quyền xoá feedback này');
        }

        // Xoá replies con trước
        $children = ContentFeedback::query()->where('parent_id', (int)$fb->id)->get();
        foreach ($children as $c) {
            if (!empty($c->image_path) && Storage::disk('public')->exists($c->image_path)) {
                Storage::disk('public')->delete($c->image_path);
            }
            $c->delete();
        }

        // Xoá ảnh của comment cha (nếu có)
        if (!empty($fb->image_path) && Storage::disk('public')->exists($fb->image_path)) {
            Storage::disk('public')->delete($fb->image_path);
        }

        $fb->delete();

        return back()->with('success', 'Đã xoá feedback');
    }

    /**
     * Chi tiết nội dung: feedback dạng cây, số liệu tuần/tổng và các danh sách liên quan.
     */
    public function show($id)
    {
        $item = ContentCalendar::with('files')->findOrFail($id);

        // ✅ Lấy feedback theo bảng content_feedbacks + user (có thread parent_id)
        $allFeedbacks = ContentFeedback::query()
            ->where('content_calendar_id', $item->id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        // Build tree: parents + children
        $parents = $allFeedbacks->whereNull('parent_id')->values();
        $childrenByParent = $allFeedbacks->whereNotNull('parent_id')->groupBy('parent_id');

        $feedbackTree = $parents->map(function ($p) use ($childrenByParent) {
            $p->children = ($childrenByParent[$p->id] ?? collect())->values();
            return $p;
        });

        $weekStart = Carbon::parse($item->publish_date)
            ->startOfWeek(Carbon::MONDAY)
            ->format('Y-m-d');

        $metricWeek = DB::table('content_calendar_weekly_metrics')
            ->where('content_calendar_id', $item->id)
            ->where('week_start', $weekStart)
            ->first();

        $metricTotal = DB::table('content_calendar_weekly_metrics')
            ->where('content_calendar_id', $item->id)
            ->selectRaw('
                COALESCE(SUM(reach),0) as reach,
                COALESCE(SUM(views),0) as views,
                COALESCE(SUM(likes),0) as likes,
                COALESCE(SUM(comments),0) as comments,
                COALESCE(SUM(shares),0) as shares,
                COALESCE(SUM(leads),0) as leads,
                COALESCE(SUM(duration_min),0) as duration_min
            ')
            ->first();

        $upcoming = ContentCalendar::query()
            ->where('status', 'scheduled')
            ->orderBy('publish_date', 'asc')
            ->limit(8)
            ->get(['id', 'title', 'publish_date', 'platform']);

        $drafts = ContentCalendar::query()
            ->where('status', 'draft')
            ->orderBy('publish_date', 'desc')
            ->limit(8)
            ->get(['id', 'title', 'publish_date', 'platform']);

        $submitted = ContentCalendar::query()
            ->where('status', 'submitted')
            ->orderBy('publish_date', 'desc')
            ->limit(8)
            ->get(['id', 'title', 'publish_date', 'platform']);

        return view('marketing.reports.content_calendar_show', compact(
            'item',
            'upcoming',
            'drafts',
            'submitted',
            'metricWeek',
            'metricTotal',
            'weekStart',
            'feedbackTree'
        ));
    }

    /**
     * Cập nhật nội dung, người phụ trách và đồng bộ KPI lương theo tháng khi cần.
     */
    public function update(Request $request, $id)
    {
        $item = ContentCalendar::findOrFail($id);

        $validated = $request->validate([
            'publish_date' => 'nullable|date',
            'platform'     => 'nullable|string',
            'content_type' => 'nullable|string',
            'title'        => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'full_content' => 'nullable|string',
            'status'       => 'nullable|string',
            'campaign_id'  => 'nullable',
            'link'         => 'nullable|url|max:2048',
            'attachment'   => 'nullable|file|max:10240',

            'assignees'    => 'nullable|array',
            'assignees.*'  => 'nullable|string|max:255',
            'assignee_user_id' => 'nullable|integer|exists:users,id',
            'assignee'     => 'nullable|string|max:255',
        ]);

        $updateData = [];
        foreach (['publish_date', 'platform', 'content_type', 'title', 'description', 'full_content', 'status', 'campaign_id', 'link'] as $k) {
            if (array_key_exists($k, $validated)) $updateData[$k] = $validated[$k];
        }

        $assignees = $this->normalizeAssignees($request);

        if (empty($assignees)) {
            $legacy = trim((string)$request->input('assignee', ''));
            if ($legacy !== '') {
                $parts = preg_split('/,|;|\|/', $legacy);
                $parts = array_values(array_filter(array_map('trim', $parts)));
                $tmpReq = new Request(['assignees' => $parts]);
                $assignees = $this->normalizeAssignees($tmpReq);
            }
        }

        $assigneeJoined = trim((string)($validated['assignee'] ?? ''));
        if ($assigneeJoined === '' && count($assignees)) $assigneeJoined = implode(', ', $assignees);

        if ($request->has('assignee_user_id')) {
            $assigneeUserId = $request->input('assignee_user_id');
            $assigneeUserName = null;
            if ($assigneeUserId) {
                $u = User::find($assigneeUserId);
                $assigneeUserName = $u?->name;
            }

            $updateData['assignee_user_id'] = $assigneeUserId ?: null;

            if ($assigneeUserName) {
                $assignees = [$assigneeUserName];
                $assigneeJoined = $assigneeUserName;
                $updateData['assignee'] = $assigneeJoined;
                $updateData['assignees'] = json_encode($assignees, JSON_UNESCAPED_UNICODE);
            } else {
                $updateData['assignee'] = null;
                $updateData['assignees'] = null;
            }
        } else {
            if ($request->has('assignee') || $request->has('assignees')) {
                $updateData['assignee']  = $assigneeJoined ?: null;
                $updateData['assignees'] = count($assignees) ? json_encode($assignees, JSON_UNESCAPED_UNICODE) : null;
            }
        }

        if (!empty($updateData)) $item->update($updateData);

        // ✅ Auto sync KPI payroll theo tháng sau khi sửa
        $assigneeId = $item->assignee_user_id ?? null;
        if ($request->has('assignee_user_id')) $assigneeId = $request->input('assignee_user_id') ?: null;

        $publishDate = $item->publish_date;
        if ($request->filled('publish_date')) $publishDate = $request->input('publish_date');

        $needSync = false;
        if ($request->has('status') || $request->has('publish_date') || $request->has('content_type') || $request->has('assignee_user_id')) $needSync = true;
        if (($request->input('status') ?? $item->status) === 'posted') $needSync = true;

        if ($needSync && !empty($assigneeId) && !empty($publishDate)) {
            $period = Carbon::parse($publishDate)->format('Y-m');
            $this->syncKpiPayrollFromCalendarByMonth($period, (int)$assigneeId);
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('content_files', 'public');

            if (method_exists($item, 'files')) {
                $item->files()->create([
                    'file_path'   => $path,
                    'file_name'   => $file->getClientOriginalName(),
                    'file_type'   => $file->getClientMimeType(),
                    'uploaded_by' => auth()->id(),
                ]);
            } else {
                $item->attachment_path = $path;
                $item->save();
            }
        }

        return back()->with('success', 'Đã lưu nội dung');
    }

    /**
     * Upload file đính kèm cho nội dung.
     */
    public function uploadFile(Request $request, $id)
    {
        $item = ContentCalendar::findOrFail($id);

        $request->validate([
            'file' => 'required|file|max:5120',
        ]);

        $file = $request->file('file');
        $path = $file->store('content_files', 'public');

        $item->files()->create([
            'file_path'     => $path,
            'file_name'     => $file->getClientOriginalName(),
            'file_type'     => $file->getClientMimeType(),
            'uploaded_by'   => auth()->id(),
        ]);

        return back()->with('success', 'Đã upload file');
    }

    /**
     * Chuyển nội dung sang trạng thái chờ duyệt (submitted).
     */
    public function submit($id)
    {
        $item = ContentCalendar::findOrFail($id);

        $item->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Đã gửi duyệt');
    }

    /**
     * Duyệt nội dung (approved) và ghi nhận người duyệt.
     */
    public function approve($id)
    {
        $item = ContentCalendar::findOrFail($id);

        $item->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return back()->with('success', 'Đã duyệt');
    }

    /**
     * Lấy số liệu tuần của nội dung theo week_start (trả JSON).
     */
    public function getWeeklyMetrics(Request $request, $id)
    {
        $request->validate([
            'week_start' => ['required'],
        ]);

        $weekStart = $this->parseDateFlexible((string)$request->week_start)
            ->startOfWeek(Carbon::MONDAY)
            ->format('Y-m-d');

        $row = DB::table('content_calendar_weekly_metrics')
            ->where('content_calendar_id', $id)
            ->where('week_start', $weekStart)
            ->first();

        return response()->json([
            'ok' => true,
            'data' => $row,
            'week_start' => $weekStart,
        ]);
    }

    /**
     * Lưu (tạo mới hoặc cập nhật) số liệu tuần của nội dung.
     */
    public function saveWeeklyMetrics(Request $request, $id)
    {
        $request->validate([
            'week_start' => ['required'],
            'reach' => ['nullable', 'integer', 'min:0'],
            'views' => ['nullable', 'integer', 'min:0'],
            'likes' => ['nullable', 'integer', 'min:0'],
            'comments' => ['nullable', 'integer', 'min:0'],
            'shares' => ['nullable', 'integer', 'min:0'],
            'leads' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
            'duration_min' => ['nullable', 'integer', 'min:0'],
        ]);

        // ✅ FIX: parse dd/mm/yyyy hoặc yyyy-mm-dd
        $weekStartCarbon = $this->parseDateFlexible((string)$request->week_start)->startOfWeek(Carbon::MONDAY);
        $weekEndCarbon   = (clone $weekStartCarbon)->endOfWeek(Carbon::SUNDAY);

        $payload = [
            'week_end' => $weekEndCarbon->format('Y-m-d'),
            'reach' => (int)($request->reach ?? 0),
            'views' => (int)($request->views ?? 0),
            'likes' => (int)($request->likes ?? 0),
            'comments' => (int)($request->comments ?? 0),
            'shares' => (int)($request->shares ?? 0),
            'leads' => (int)($request->leads ?? 0),
            'note' => $request->note,
            'duration_min' => (int)($request->duration_min ?? 0), // ✅ PHÚT
            'entered_by' => auth()->id(),
            'updated_at' => now(),
        ];

        $weekStartStr = $weekStartCarbon->format('Y-m-d');

        $exists = DB::table('content_calendar_weekly_metrics')
            ->where('content_calendar_id', $id)
            ->where('week_start', $weekStartStr)
            ->exists();

        if ($exists) {
            DB::table('content_calendar_weekly_metrics')
                ->where('content_calendar_id', $id)
                ->where('week_start', $weekStartStr)
                ->update($payload);
        } else {
            $payload['content_calendar_id'] = $id;
            $payload['week_start'] = $weekStartStr;
            $payload['created_at'] = now();
            DB::table('content_calendar_weekly_metrics')->insert($payload);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Đã lưu số liệu tuần thành công.',
            'week_start' => $weekStartStr,
            'duration_min' => (int)$payload['duration_min'],
        ]);
    }

    /**
     * Dashboard tổng hợp số liệu tuần: KPI, xếp hạng, top nội dung, cảnh báo thiếu số liệu (trả JSON).
     */
    public function weeklyDashboard(Request $request)
    {
        $request->validate([
            'week_start' => ['nullable'],
            'from_date' => ['nullable'],
            'to_date' => ['nullable'],
            'platform' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'assignee_user_id' => ['nullable', 'integer'],
        ]);

        $from = $request->filled('from_date') ? $this->parseDateFlexible($request->from_date)->format('Y-m-d') : null;
        $to   = $request->filled('to_date')   ? $this->parseDateFlexible($request->to_date)->format('Y-m-d')   : null;

        if (!$from || !$to) {
            $ws = $request->filled('week_start') ? $this->parseDateFlexible($request->week_start) : now();
            $weekStart = $ws->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd   = $ws->copy()->endOfWeek(Carbon::SUNDAY);
            $from = $weekStart->format('Y-m-d');
            $to   = $weekEnd->format('Y-m-d');
        }

        $base = DB::table('content_calendars as c')
            ->leftJoin('content_calendar_weekly_metrics as m', function ($join) {
                $join->on('m.content_calendar_id', '=', 'c.id');
                $join->whereRaw("m.week_start = DATE_SUB(DATE(c.publish_date), INTERVAL WEEKDAY(DATE(c.publish_date)) DAY)");
            })
            ->whereBetween('c.publish_date', [$from, $to]);

        if ($request->filled('platform') && $request->platform !== 'all') {
            $plat = strtolower(trim($request->platform));
            $base->whereRaw("LOWER(SUBSTRING_INDEX(c.platform, '|', 1)) = ?", [$plat]);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $base->where('c.status', $request->status);
        }

        if ($request->filled('assignee_user_id')) {
            $base->where('c.assignee_user_id', (int)$request->assignee_user_id);
        }

        $g = $this->typeGroups();
        $postTypes = $g['post'];
        $aiTypes = $g['ai'];
        $reviewTypes = $g['review'];
        $liveTypes = $g['live'];

        $totals = (clone $base)
            ->selectRaw('
                COALESCE(SUM(COALESCE(m.reach,0)),0) as reach,
                COALESCE(SUM(COALESCE(m.views,0)),0) as views,
                COALESCE(SUM(COALESCE(m.likes,0)),0) as likes,
                COALESCE(SUM(COALESCE(m.comments,0)),0) as comments,
                COALESCE(SUM(COALESCE(m.shares,0)),0) as shares,
                COALESCE(SUM(COALESCE(m.leads,0)),0) as leads,
                COALESCE(SUM(COALESCE(m.duration_min,0)),0) as duration_min
            ')
            ->selectRaw('
                SUM(CASE WHEN c.content_type IN ("' . implode('","', $postTypes) . '") THEN 1 ELSE 0 END) as cnt_post,
                SUM(CASE WHEN c.content_type IN ("' . implode('","', $aiTypes) . '") THEN 1 ELSE 0 END) as cnt_video_ai,
                SUM(CASE WHEN c.content_type IN ("' . implode('","', $reviewTypes) . '") THEN 1 ELSE 0 END) as cnt_video_review,
                SUM(CASE WHEN c.content_type IN ("' . implode('","', $liveTypes) . '") THEN 1 ELSE 0 END) as cnt_livestream
            ')
            ->first();

        $engagement = (int)$totals->likes + (int)$totals->comments + (int)$totals->shares;

        $ranking = (clone $base)
            ->selectRaw('
                c.assignee_user_id,
                COALESCE(SUM(COALESCE(m.reach,0)),0) as reach,
                COALESCE(SUM(COALESCE(m.views,0)),0) as views,
                COALESCE(SUM(COALESCE(m.likes,0)),0) as likes,
                COALESCE(SUM(COALESCE(m.comments,0)),0) as comments,
                COALESCE(SUM(COALESCE(m.shares,0)),0) as shares,
                COALESCE(SUM(COALESCE(m.leads,0)),0) as leads,
                COALESCE(SUM(COALESCE(m.duration_min,0)),0) as duration_min
            ')
            ->groupBy('c.assignee_user_id')
            ->orderByDesc('leads')
            ->orderByDesc('reach')
            ->limit(10)
            ->get();

        $topContent = (clone $base)
            ->selectRaw('
                c.id, c.title, c.platform, c.publish_date,
                COALESCE(m.reach,0) as reach,
                COALESCE(m.views,0) as views,
                (COALESCE(m.likes,0)+COALESCE(m.comments,0)+COALESCE(m.shares,0)) as engagement,
                COALESCE(m.leads,0) as leads,
                COALESCE(m.duration_min,0) as duration_min
            ')
            ->orderByRaw('
                (
                  (COALESCE(m.likes,0)+COALESCE(m.comments,0)+COALESCE(m.shares,0))
                  / NULLIF(GREATEST(COALESCE(m.reach,0), COALESCE(m.views,0)), 0)
                ) DESC
            ')
            ->orderByDesc('engagement')
            ->limit(5)
            ->get();

        $alertsMissing = (clone $base)
            ->whereNull('m.week_start')
            ->select('c.id','c.title','c.publish_date','c.assignee_user_id','c.platform','c.status')
            ->orderBy('c.publish_date')
            ->limit(10)
            ->get();

        $daily = (clone $base)
            ->selectRaw('
                DATE(c.publish_date) as d,
                COALESCE(SUM(COALESCE(m.reach,0)),0) as reach,
                COALESCE(SUM(COALESCE(m.likes,0)+COALESCE(m.comments,0)+COALESCE(m.shares,0)),0) as engagement,
                COALESCE(SUM(COALESCE(m.leads,0)),0) as leads,
                COALESCE(SUM(COALESCE(m.duration_min,0)),0) as duration_min
            ')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        return response()->json([
            'ok' => true,
            'range_from' => $from,
            'range_to' => $to,
            'week_start' => $from,
            'week_end' => $to,
            'totals' => $totals,
            'engagement' => $engagement,
            'ranking' => $ranking,
            'top_content' => $topContent,
            'alerts_missing' => $alertsMissing,
            'daily' => $daily,
        ]);
    }

    /**
     * ✅ Sync KPI payroll theo tháng
     */
    private function syncKpiPayrollFromCalendarByMonth(string $period, int $userId): void
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();
        $end   = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();

        $base = ContentCalendar::query()
            ->where('assignee_user_id', $userId)
            ->whereBetween('publish_date', [$start, $end])
            ->where('status', 'posted');

        $g = $this->typeGroups();

        $post   = (clone $base)->whereIn('content_type', $g['post'])->count();
        $ai     = (clone $base)->whereIn('content_type', $g['ai'])->count();
        $review = (clone $base)->whereIn('content_type', $g['review'])->count();

        $trendRows = (clone $base)->whereIn('content_type', $g['trend'])
            ->orderBy('publish_date')
            ->get(['title','link']);

        $liveRows = (clone $base)->whereIn('content_type', $g['live'])
            ->orderBy('publish_date')
            ->get(['title','link']);

        $suggestTrend = $trendRows->map(fn($r) => [
            'title' => (string)($r->title ?? ''),
            'url' => (string)($r->link ?? ''),
            'views' => 0,
            'engagement' => 0,
        ])->values()->all();

        $suggestLive = $liveRows->map(fn($r) => [
            'title' => (string)($r->title ?? ''),
            'url' => (string)($r->link ?? ''),
            'duration_min' => 0,
            'views' => 0,
            'engagement' => 0,
            'lead_count' => 0,
        ])->values()->all();

        $actual = MarketingKpiPayActual::firstOrNew([
            'period' => $period,
            'user_id' => $userId,
        ]);

        $existingTrend = is_array($actual->trend_videos) ? $actual->trend_videos : (array)($actual->trend_videos ?? []);
        $existingLive  = is_array($actual->livestreams)  ? $actual->livestreams  : (array)($actual->livestreams ?? []);

        $mergeByUrl = function(array $old, array $new): array {
            $map = [];
            foreach ($old as $row) {
                $key = trim((string)($row['url'] ?? ''));
                if ($key === '') $key = trim((string)($row['title'] ?? ''));
                if ($key !== '') $map[$key] = $row;
            }
            foreach ($new as $row) {
                $key = trim((string)($row['url'] ?? ''));
                if ($key === '') $key = trim((string)($row['title'] ?? ''));
                if ($key !== '' && !isset($map[$key])) $map[$key] = $row;
            }
            return array_values($map);
        };

        $actual->actual_post = (int)$post;
        $actual->actual_video_ai = (int)$ai;
        $actual->actual_video_review = (int)$review;

        $actual->trend_videos = $mergeByUrl($existingTrend, $suggestTrend);
        $actual->livestreams  = $mergeByUrl($existingLive,  $suggestLive);

        $actual->save();
    }

    /**
     * Xóa nội dung khỏi lịch biên tập.
     */
    public function destroy($id)
    {
        $item = ContentCalendar::findOrFail($id);
        $item->delete();

        return redirect()->back()->with('success', 'Đã xóa nội dung');
    }

    /**
     * Chuẩn hoá danh sách người phụ trách từ request (trim, bỏ rỗng, bỏ trùng).
     *
     * @return array
     */
    protected function normalizeAssignees(Request $request): array
    {
        $assignees = $request->input('assignees');

        if (is_string($assignees)) {
            $assignees = preg_split('/,|;|\|/', $assignees);
        }

        if (!is_array($assignees)) $assignees = [];

        $assignees = array_map(fn($v) => trim((string)$v), $assignees);
        $assignees = array_values(array_filter($assignees, fn($v) => $v !== ''));
        $assignees = array_values(array_unique($assignees));

        return $assignees;
    }
}