<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\System\Conversation;
use App\Models\System\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    private function ensureChatSchema(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS conversations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(50) NOT NULL DEFAULT 'direct',
                name VARCHAR(255) NULL,
                is_internal TINYINT(1) NOT NULL DEFAULT 1,
                department_id BIGINT UNSIGNED NULL,
                task_id BIGINT UNSIGNED NULL,
                created_by BIGINT UNSIGNED NULL,
                last_message_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX conversations_type_index (type),
                INDEX conversations_department_id_index (department_id),
                INDEX conversations_last_message_at_index (last_message_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS conversation_user (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                conversation_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                last_read_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY conversation_user_unique (conversation_id, user_id),
                INDEX conversation_user_user_id_index (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                conversation_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                body TEXT NULL,
                attachment_path VARCHAR(500) NULL,
                attachment_name VARCHAR(255) NULL,
                attachment_mime VARCHAR(150) NULL,
                attachment_size BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX messages_conversation_id_index (conversation_id),
                INDEX messages_user_id_index (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->trySql("ALTER TABLE messages MODIFY body TEXT NULL");

        foreach ([
            'name' => "ALTER TABLE conversations ADD COLUMN name VARCHAR(255) NULL AFTER type",
            'is_internal' => "ALTER TABLE conversations ADD COLUMN is_internal TINYINT(1) NOT NULL DEFAULT 1 AFTER name",
            'department_id' => "ALTER TABLE conversations ADD COLUMN department_id BIGINT UNSIGNED NULL AFTER is_internal",
            'task_id' => "ALTER TABLE conversations ADD COLUMN task_id BIGINT UNSIGNED NULL AFTER department_id",
        ] as $column => $sql) {
            if (!Schema::hasColumn('conversations', $column)) {
                $this->trySql($sql);
            }
        }

        foreach ([
            'attachment_path' => "ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(500) NULL AFTER body",
            'attachment_name' => "ALTER TABLE messages ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path",
            'attachment_mime' => "ALTER TABLE messages ADD COLUMN attachment_mime VARCHAR(150) NULL AFTER attachment_name",
            'attachment_size' => "ALTER TABLE messages ADD COLUMN attachment_size BIGINT UNSIGNED NULL AFTER attachment_mime",
        ] as $column => $sql) {
            if (!Schema::hasColumn('messages', $column)) {
                $this->trySql($sql);
            }
        }

        if (!Schema::hasColumn('conversation_user', 'last_read_at')) {
            $this->trySql("ALTER TABLE conversation_user ADD COLUMN last_read_at TIMESTAMP NULL DEFAULT NULL AFTER user_id");
        }
    }

    private function trySql(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            // Ignore duplicate / incompatible alter errors.
        }
    }

    public function inbox()
    {
        $this->ensureChatSchema();

        return view('chat.inbox', [
            'initialConversationId' => null,
        ]);
    }

    public function users()
    {
        $this->ensureChatSchema();

        $users = User::query()
            ->select('id', 'name', 'email', 'department_id', 'avatar')
            ->where('id', '!=', auth()->id())
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->orderBy('name')
            ->get();

        return view('chat.users', compact('users'));
    }

    public function direct(Request $request)
    {
        $this->ensureChatSchema();

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $conversation = $this->findOrCreateDirect((int) auth()->id(), (int) $data['user_id']);

        return redirect()->route('chat.show', $conversation->id);
    }

    public function show(Conversation $conversation)
    {
        $this->ensureChatSchema();
        $this->abortUnlessMember($conversation);

        $conversation->users()->updateExistingPivot(auth()->id(), [
            'last_read_at' => now(),
        ]);

        return view('chat.inbox', [
            'initialConversationId' => (int) $conversation->id,
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->ensureChatSchema();
        $this->abortUnlessMember($conversation);

        $this->createMessagesFromRequest($request, $conversation);

        $conversation->update(['last_message_at' => now()]);

        return redirect()->route('chat.show', $conversation->id);
    }

    public function usersJson()
    {
        $this->ensureChatSchema();

        $users = User::query()
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->where('users.id', '!=', auth()->id())
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('users.is_active', 1))
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.department_id',
                'users.avatar',
                'departments.name as department_name',
            ])
            ->orderBy('users.name')
            ->get();

        $users = $users->map(function ($user) {
            $user->avatar_url = $this->userAvatarUrl($user);
            return $user;
        });

        return response()->json(['users' => $users]);
    }

    public function departmentsJson()
    {
        $this->ensureChatSchema();

        if (!Schema::hasTable('departments')) {
            return response()->json(['departments' => []]);
        }

        $departments = DB::table('departments')
            ->leftJoin('users', 'users.department_id', '=', 'departments.id')
            ->select([
                'departments.id',
                'departments.name',
                'departments.code',
                DB::raw('COUNT(users.id) as users_count'),
            ])
            ->groupBy('departments.id', 'departments.name', 'departments.code')
            ->orderBy('departments.name')
            ->get();

        return response()->json(['departments' => $departments]);
    }

    public function directJson(Request $request)
    {
        $this->ensureChatSchema();

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $conversation = $this->findOrCreateDirect((int) auth()->id(), (int) $data['user_id']);

        return response()->json([
            'ok' => true,
            'conversation_id' => (int) $conversation->id,
        ]);
    }

    public function departmentJson(Request $request)
    {
        $this->ensureChatSchema();

        $data = $request->validate([
            'department_id' => 'required|integer',
        ]);

        $department = Department::query()->findOrFail((int) $data['department_id']);

        $conversation = Conversation::query()
            ->where('type', 'department')
            ->where('department_id', $department->id)
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'type' => 'department',
                'name' => $department->name,
                'is_internal' => 1,
                'department_id' => $department->id,
                'created_by' => auth()->id(),
                'last_message_at' => now(),
            ]);
        }

        $userIds = User::query()
            ->where('department_id', $department->id)
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->pluck('id')
            ->push((int) auth()->id())
            ->unique()
            ->values();

        $this->attachUsers($conversation, $userIds->all());

        return response()->json([
            'ok' => true,
            'conversation_id' => (int) $conversation->id,
        ]);
    }

    public function groupJson(Request $request)
    {
        $this->ensureChatSchema();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $conversation = Conversation::create([
            'type' => 'group',
            'name' => $data['name'],
            'is_internal' => 1,
            'created_by' => auth()->id(),
            'last_message_at' => now(),
        ]);

        $userIds = collect($data['user_ids'])
            ->push((int) auth()->id())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->attachUsers($conversation, $userIds);

        return response()->json([
            'ok' => true,
            'conversation_id' => (int) $conversation->id,
        ]);
    }

    public function conversationsJson()
    {
        $this->ensureChatSchema();

        $meId = (int) auth()->id();

        $conversations = auth()->user()->conversations()
            ->with([
                'users:id,name,email,department_id,avatar',
                'messages' => fn ($q) => $q->latest()->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('conversations.id')
            ->get();

        if (Schema::hasColumn('users', 'is_active')) {
            $conversations = $conversations->filter(function ($c) use ($meId) {
                if ($c->type !== 'direct') {
                    return true;
                }

                $other = $c->users->firstWhere('id', '!=', $meId);

                if (!$other) {
                    return true;
                }

                return (int) DB::table('users')->where('id', $other->id)->value('is_active') === 1;
            })->values();
        }

        $data = $conversations->map(function ($c) use ($meId) {
            $lastReadAt = $c->users->firstWhere('id', $meId)?->pivot?->last_read_at;
            $lastMsg = $c->messages->first();
            $other = $c->users->firstWhere('id', '!=', $meId);

            $unread = $c->messages()
                ->where('user_id', '!=', $meId)
                ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
                ->count();

            $title = $this->conversationTitle($c, $meId);

            $preview = $lastMsg?->body ?: '';
            if (!$preview && $lastMsg?->attachment_name) {
                $preview = '📎 ' . $lastMsg->attachment_name;
            }

            return [
                'id' => (int) $c->id,
                'type' => $c->type,
                'name' => $title,
                'other_id' => $other?->id,
                'members_count' => $c->users->count(),
                'last_message' => $preview,
                'last_message_at' => $lastMsg?->created_at?->format('d/m H:i') ?? '',
                'unread' => (int) $unread,
                'initials' => mb_strtoupper(mb_substr($title ?: 'C', 0, 2, 'UTF-8'), 'UTF-8'),
                'avatar_url' => $this->conversationAvatarUrl($c, $meId),
            ];
        })->values();

        return response()->json(['conversations' => $data]);
    }

    public function messagesJson(Request $request, Conversation $conversation)
    {
        $this->ensureChatSchema();
        $this->abortUnlessMember($conversation);

        $afterId = (int) $request->query('after_id', 0);

        $rows = $conversation->messages()
            ->with('sender:id,name,avatar')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(200)
            ->get();

        $conversation->users()->updateExistingPivot(auth()->id(), [
            'last_read_at' => now(),
        ]);

        $messages = $rows->map(fn ($m) => $this->messagePayload($m));

        return response()->json([
            'messages' => $messages,
            'conversation' => [
                'id' => (int) $conversation->id,
                'name' => $this->conversationTitle($conversation->loadMissing('users:id,name,avatar'), (int) auth()->id()),
                'type' => $conversation->type,
                'avatar_url' => $this->conversationAvatarUrl($conversation->loadMissing('users:id,name,avatar'), (int) auth()->id()),
                'initials' => mb_strtoupper(mb_substr($this->conversationTitle($conversation->loadMissing('users:id,name,avatar'), (int) auth()->id()) ?: 'CH', 0, 2, 'UTF-8'), 'UTF-8'),
            ],
        ]);
    }

    public function sendJson(Request $request, Conversation $conversation)
    {
        $this->ensureChatSchema();
        $this->abortUnlessMember($conversation);

        $messages = $this->createMessagesFromRequest($request, $conversation);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'ok' => true,
            'messages' => $messages->map(fn ($m) => $this->messagePayload($m->loadMissing('sender:id,name,avatar')))->values(),
        ]);
    }

    public function markReadJson(Conversation $conversation)
    {
        $this->ensureChatSchema();
        $this->abortUnlessMember($conversation);

        $conversation->users()->updateExistingPivot(auth()->id(), [
            'last_read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function downloadAttachment(Request $request, Message $message)
    {
        $this->ensureChatSchema();

        $conversation = $message->conversation;
        $this->abortUnlessMember($conversation);

        abort_unless($message->attachment_path, 404);

        $fullPath = storage_path('app/public/' . ltrim($message->attachment_path, '/'));

        abort_unless(is_file($fullPath), 404);

        $fileName = $message->attachment_name ?: basename($fullPath);
        $mime = $message->attachment_mime ?: 'application/octet-stream';

        if ($request->boolean('inline') && strpos($mime, 'image/') === 0) {
            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
            ]);
        }

        return response()->download($fullPath, $fileName, [
            'Content-Type' => $mime,
        ]);
    }

    public function quickTaskStore(Request $request, Conversation $conversation)
    {
        $this->ensureChatSchema();
        $this->abortUnlessMember($conversation);

        abort_unless(Schema::hasTable('tasks'), 422, 'Chưa có bảng tasks.');

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'assignee_id' => 'nullable|integer|exists:users,id',
            'priority' => 'nullable|string|max:50',
            'due_at' => 'nullable|date',
        ]);

        $columns = array_flip(Schema::getColumnListing('tasks'));

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'requester_id' => auth()->id(),
            'assignee_id' => $data['assignee_id'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
            'due_at' => $data['due_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $payload = array_intersect_key($payload, $columns);

        $taskId = DB::table('tasks')->insertGetId($payload);

        $taskText = "📌 Công việc mới: " . $data['title'];
        if (!empty($data['due_at'])) {
            $taskText .= "\nHạn: " . Carbon::parse($data['due_at'])->format('d/m/Y');
        }

        $conversation->messages()->create([
            'user_id' => auth()->id(),
            'body' => $taskText,
        ]);

        $conversation->update([
            'task_id' => $taskId,
            'last_message_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'task_id' => $taskId,
            ]);
        }

        return back()->with('success', 'Đã tạo công việc nhanh.');
    }

    private function findOrCreateDirect(int $myId, int $otherId): Conversation
    {
        $conversation = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('users', fn ($q) => $q->where('users.id', $myId))
            ->whereHas('users', fn ($q) => $q->where('users.id', $otherId))
            ->first();

        if (!$conversation) {
            $other = User::query()
                ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', 1))
                ->find($otherId);

            abort_unless($other, 404, 'Nhan su nay dang ngung hoat dong hoac khong ton tai.');

            $conversation = Conversation::create([
                'type' => 'direct',
                'name' => $other?->name,
                'is_internal' => 1,
                'created_by' => $myId,
                'last_message_at' => now(),
            ]);

            $this->attachUsers($conversation, [$myId, $otherId]);
        }

        return $conversation;
    }

    private function attachUsers(Conversation $conversation, array $userIds): void
    {
        $now = now();

        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId <= 0) {
                continue;
            }

            $exists = DB::table('conversation_user')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->exists();

            if (!$exists) {
                DB::table('conversation_user')->insert([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'last_read_at' => $userId === (int) auth()->id() ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function abortUnlessMember(Conversation $conversation): void
    {
        abort_unless(
            DB::table('conversation_user')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', auth()->id())
                ->exists(),
            403
        );
    }

    private function createMessagesFromRequest(Request $request, Conversation $conversation)
    {
        $data = $request->validate([
            'body' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file',
        ]);

        $body = trim((string) ($data['body'] ?? ''));
        $files = $request->file('attachments', []);

        if ($files && !is_array($files)) {
            $files = [$files];
        }

        if ($body === '' && empty($files)) {
            throw ValidationException::withMessages([
                'body' => 'Vui lòng nhập tin nhắn hoặc chọn file.',
            ]);
        }

        $created = collect();

        if (!empty($files)) {
            foreach ($files as $index => $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }

                $path = $file->store('chat/' . $conversation->id, 'public');

                $created->push($conversation->messages()->create([
                    'user_id' => auth()->id(),
                    'body' => $index === 0 ? ($body ?: null) : null,
                    'attachment_path' => $path,
                    'attachment_name' => $file->getClientOriginalName(),
                    'attachment_mime' => $file->getMimeType(),
                    'attachment_size' => $file->getSize(),
                ]));
            }
        } else {
            $created->push($conversation->messages()->create([
                'user_id' => auth()->id(),
                'body' => $body,
            ]));
        }

        return $created;
    }

    private function messagePayload(Message $m): array
    {
        $mime = (string) ($m->attachment_mime ?? '');
        $isImage = $mime !== '' && strpos($mime, 'image/') === 0;

        return [
            'id' => (int) $m->id,
            'user_id' => (int) $m->user_id,
            'sender_name' => (string) ($m->sender?->name ?? 'Unknown'),
            'avatar_url' => $this->userAvatarUrl($m->sender),
            'body' => (string) ($m->body ?? ''),
            'created_at' => $m->created_at ? Carbon::parse($m->created_at)->format('d/m H:i') : '',
            'attachment_name' => $m->attachment_name,
            'attachment_mime' => $m->attachment_mime,
            'attachment_size' => $m->attachment_size,
            'attachment_url' => $m->attachment_path ? route('chat.messages.download', $m->id) : null,
            'attachment_inline_url' => $m->attachment_path ? route('chat.messages.download', ['message' => $m->id, 'inline' => 1]) : null,
            'is_image' => $isImage,
        ];
    }


    private function userAvatarUrl($user): ?string
    {
        if (!$user) {
            return null;
        }

        $avatarPath = null;

        if (isset($user->avatar) && is_string($user->avatar) && trim($user->avatar) !== '') {
            $avatarPath = trim($user->avatar);
        } elseif (is_object($user) && method_exists($user, 'relationLoaded') && $user->relationLoaded('avatar') && $user->avatar) {
            if (isset($user->avatar->file_path)) {
                $avatarPath = $user->avatar->file_path;
            }
        }

        if (!$avatarPath) {
            return null;
        }

        if (str_starts_with($avatarPath, 'http://') || str_starts_with($avatarPath, 'https://')) {
            return $avatarPath;
        }

        if (str_starts_with($avatarPath, '/storage/')) {
            return $avatarPath;
        }

        if (str_starts_with($avatarPath, 'storage/')) {
            return url('/' . $avatarPath);
        }

        if (str_starts_with($avatarPath, '/')) {
            return url($avatarPath);
        }

        return Storage::disk('public')->url($avatarPath);
    }

    private function conversationAvatarUrl(Conversation $conversation, int $meId): ?string
    {
        if ($conversation->type === 'direct') {
            $other = $conversation->users->firstWhere('id', '!=', $meId);
            return $this->userAvatarUrl($other);
        }

        return null;
    }
    private function conversationTitle(Conversation $conversation, int $meId): string
    {
        if ($conversation->type === 'direct') {
            $other = $conversation->users->firstWhere('id', '!=', $meId);
            return $other?->name ?: ($conversation->name ?: 'Chat cá nhân');
        }

        if ($conversation->name) {
            return $conversation->name;
        }

        if ($conversation->type === 'department') {
            return 'Phòng ban';
        }

        return 'Nhóm chat';
    }
}