<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    private function ensureTables(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS hr_announcements (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                body LONGTEXT NULL,
                category VARCHAR(80) NOT NULL DEFAULT 'general',
                target_type VARCHAR(50) NOT NULL DEFAULT 'all',
                department_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NULL,
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                is_pinned TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(50) NOT NULL DEFAULT 'published',
                created_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX hra_status_index (status),
                INDEX hra_target_index (target_type, department_id, user_id),
                INDEX hra_time_index (starts_at, ends_at),
                INDEX hra_pinned_index (is_pinned)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS hr_announcement_files (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                announcement_id BIGINT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                path VARCHAR(500) NOT NULL,
                mime_type VARCHAR(150) NULL,
                size BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX hraf_announcement_id_index (announcement_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS hr_announcement_reads (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                announcement_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                read_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY hra_reads_unique (announcement_id, user_id),
                INDEX hra_reads_user_index (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function index(Request $request)
    {
        $this->ensureTables();

        $user = auth()->user();
        $canManage = $this->canManage();
        $scope = $request->input('scope', 'published');

        $query = $canManage && $scope === 'manage'
            ? DB::table('hr_announcements as a')
            : $this->visibleQuery();

        $query->leftJoin('users as creator', 'creator.id', '=', 'a.created_by')
            ->leftJoin('departments as d', 'd.id', '=', 'a.department_id')
            ->leftJoin('users as target_user', 'target_user.id', '=', 'a.user_id')
            ->leftJoin('hr_announcement_reads as r', function ($join) use ($user) {
                $join->on('r.announcement_id', '=', 'a.id')
                    ->where('r.user_id', '=', $user->id);
            })
            ->select([
                'a.*',
                'creator.name as creator_name',
                'd.name as department_name',
                'target_user.name as target_user_name',
                'r.read_at',
            ]);

        if ($request->filled('category')) {
            $query->where('a.category', $request->category);
        }

        if ($request->filled('status') && $canManage && $scope === 'manage') {
            $query->where('a.status', $request->status);
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->q);
            $query->where(function ($q) use ($keyword) {
                $q->where('a.title', 'like', '%' . $keyword . '%')
                    ->orWhere('a.body', 'like', '%' . $keyword . '%');
            });
        }

        $announcements = $query
            ->orderByDesc('a.is_pinned')
            ->orderByDesc('a.created_at')
            ->paginate(12)
            ->withQueryString();

        $departments = Schema::hasTable('departments')
            ? DB::table('departments')->orderBy('name')->get()
            : collect();

        $users = Schema::hasTable('users')
            ? DB::table('users')
                ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', 1))
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'department_id'])
            : collect();

        $summary = [
            'published' => DB::table('hr_announcements')->where('status', 'published')->count(),
            'draft' => DB::table('hr_announcements')->where('status', 'draft')->count(),
            'pinned' => DB::table('hr_announcements')->where('is_pinned', 1)->count(),
            'unread' => $this->unreadCountValue(),
        ];

        return view('hr.announcements.index', compact(
            'announcements',
            'departments',
            'users',
            'canManage',
            'scope',
            'summary'
        ));
    }

    public function store(Request $request)
    {
        $this->ensureTables();
        abort_unless($this->canManage(), 403);

        $data = $this->validatedData($request);

        $id = DB::table('hr_announcements')->insertGetId([
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'category' => $data['category'],
            'target_type' => $data['target_type'],
            'department_id' => $data['target_type'] === 'department' ? ($data['department_id'] ?? null) : null,
            'user_id' => $data['target_type'] === 'user' ? ($data['user_id'] ?? null) : null,
            'starts_at' => $this->parseDateTime($data['starts_at'] ?? null),
            'ends_at' => $this->parseDateTime($data['ends_at'] ?? null),
            'is_pinned' => (int) $request->boolean('is_pinned'),
            'status' => $data['status'],
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->storeFiles($request, $id);

        return redirect()
            ->route('hr.announcements.show', $id)
            ->with('success', 'Đã tạo thông báo nhân sự.');
    }

    public function show($announcement)
    {
        $this->ensureTables();

        $item = DB::table('hr_announcements as a')
            ->leftJoin('users as creator', 'creator.id', '=', 'a.created_by')
            ->leftJoin('departments as d', 'd.id', '=', 'a.department_id')
            ->leftJoin('users as target_user', 'target_user.id', '=', 'a.user_id')
            ->where('a.id', (int) $announcement)
            ->select([
                'a.*',
                'creator.name as creator_name',
                'd.name as department_name',
                'target_user.name as target_user_name',
            ])
            ->first();

        abort_unless($item, 404);
        abort_unless($this->canManage() || $this->isVisibleToCurrentUser($item), 403);

        $this->markOneAsRead((int) $item->id);

        $files = DB::table('hr_announcement_files')
            ->where('announcement_id', (int) $item->id)
            ->orderBy('id')
            ->get();

        return view('hr.announcements.show', [
            'item' => $item,
            'files' => $files,
            'canManage' => $this->canManage(),
        ]);
    }

    public function update(Request $request, $announcement)
    {
        $this->ensureTables();
        abort_unless($this->canManage(), 403);

        $item = DB::table('hr_announcements')->where('id', (int) $announcement)->first();
        abort_unless($item, 404);

        $data = $this->validatedData($request);

        DB::table('hr_announcements')->where('id', (int) $announcement)->update([
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'category' => $data['category'],
            'target_type' => $data['target_type'],
            'department_id' => $data['target_type'] === 'department' ? ($data['department_id'] ?? null) : null,
            'user_id' => $data['target_type'] === 'user' ? ($data['user_id'] ?? null) : null,
            'starts_at' => $this->parseDateTime($data['starts_at'] ?? null),
            'ends_at' => $this->parseDateTime($data['ends_at'] ?? null),
            'is_pinned' => (int) $request->boolean('is_pinned'),
            'status' => $data['status'],
            'updated_at' => now(),
        ]);

        $this->storeFiles($request, (int) $announcement);

        return back()->with('success', 'Đã cập nhật thông báo.');
    }

    public function destroy($announcement)
    {
        $this->ensureTables();
        abort_unless($this->canManage(), 403);

        $id = (int) $announcement;

        DB::transaction(function () use ($id) {
            DB::table('hr_announcement_reads')->where('announcement_id', $id)->delete();
            DB::table('hr_announcement_files')->where('announcement_id', $id)->delete();
            DB::table('hr_announcements')->where('id', $id)->delete();
        });

        return redirect()
            ->route('hr.announcements.index', ['scope' => 'manage'])
            ->with('success', 'Đã xoá thông báo.');
    }

    public function markRead($announcement)
    {
        $this->ensureTables();
        $this->markOneAsRead((int) $announcement);

        return back()->with('success', 'Đã đánh dấu đã đọc.');
    }

    public function markAllRead()
    {
        $this->ensureTables();

        $this->visibleQuery()->select('a.id')->orderByDesc('a.id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $this->markOneAsRead((int) $row->id);
            }
        }, 'a.id', 'id');

        return back()->with('success', 'Đã đọc tất cả thông báo nhân sự.');
    }

    public function unreadCount()
    {
        $this->ensureTables();

        return response()->json([
            'count' => $this->unreadCountValue(),
        ]);
    }

    public function jsonList(Request $request)
    {
        $this->ensureTables();

        $limit = max(1, min(30, (int) $request->input('limit', 10)));

        $rows = $this->visibleQuery()
            ->leftJoin('hr_announcement_reads as r', function ($join) {
                $join->on('r.announcement_id', '=', 'a.id')
                    ->where('r.user_id', '=', auth()->id());
            })
            ->select([
                'a.id',
                'a.title',
                'a.body',
                'a.category',
                'a.is_pinned',
                'a.created_at',
                'r.read_at',
            ])
            ->orderByDesc('a.is_pinned')
            ->orderByDesc('a.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => 'hr_' . $row->id,
                'title' => 'Nhân sự: ' . $row->title,
                'message' => mb_strimwidth(strip_tags((string) $row->body), 0, 140, '...'),
                'is_read' => !empty($row->read_at),
                'created_at' => $row->created_at,
                'link' => route('hr.announcements.show', $row->id),
                'category' => $row->category,
            ]);

        return response()->json([
            'items' => [
                'data' => $rows,
            ],
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:80'],
            'target_type' => ['required', 'in:all,department,user'],
            'department_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,published,archived'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480'],
        ]);
    }

    private function visibleQuery()
    {
        $user = auth()->user();
        $now = now();

        return DB::table('hr_announcements as a')
            ->where('a.status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('a.starts_at')->orWhere('a.starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('a.ends_at')->orWhere('a.ends_at', '>=', $now);
            })
            ->where(function ($q) use ($user) {
                $q->where('a.target_type', 'all')
                    ->orWhereNull('a.target_type')
                    ->orWhere(function ($qq) use ($user) {
                        $qq->where('a.target_type', 'department')
                            ->where('a.department_id', $user->department_id);
                    })
                    ->orWhere(function ($qq) use ($user) {
                        $qq->where('a.target_type', 'user')
                            ->where('a.user_id', $user->id);
                    });
            });
    }

    private function isVisibleToCurrentUser($item): bool
    {
        if (($item->status ?? '') !== 'published') {
            return false;
        }

        $now = now();

        if (!empty($item->starts_at) && Carbon::parse($item->starts_at)->gt($now)) {
            return false;
        }

        if (!empty($item->ends_at) && Carbon::parse($item->ends_at)->lt($now)) {
            return false;
        }

        $user = auth()->user();

        if (($item->target_type ?? 'all') === 'all') {
            return true;
        }

        if (($item->target_type ?? '') === 'department') {
            return (int) $item->department_id === (int) $user->department_id;
        }

        if (($item->target_type ?? '') === 'user') {
            return (int) $item->user_id === (int) $user->id;
        }

        return false;
    }

    private function unreadCountValue(): int
    {
        return (int) $this->visibleQuery()
            ->leftJoin('hr_announcement_reads as r', function ($join) {
                $join->on('r.announcement_id', '=', 'a.id')
                    ->where('r.user_id', '=', auth()->id());
            })
            ->whereNull('r.id')
            ->count('a.id');
    }

    private function markOneAsRead(int $announcementId): void
    {
        DB::table('hr_announcement_reads')->updateOrInsert(
            [
                'announcement_id' => $announcementId,
                'user_id' => auth()->id(),
            ],
            [
                'read_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['admin', 'accounting', 'manager', 'hr']);
        }

        return false;
    }

    private function parseDateTime($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function storeFiles(Request $request, int $announcementId): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ((array) $request->file('attachments') as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $path = $file->store('hr_announcements/' . $announcementId, 'public');

            DB::table('hr_announcement_files')->insert([
                'announcement_id' => $announcementId,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
