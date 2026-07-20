<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Conversation;
use App\Models\System\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chat nội bộ hệ thống: hội thoại 1-1, gửi/nhận tin nhắn, widget chat.
 */
class ChatController extends Controller
{
    /**
     * Trang chat chính: danh sách người dùng, hội thoại và tin nhắn của hội thoại đang mở.
     */
    public function index(Request $request)
    {
        $me = auth()->user();

        $users = User::query()
            ->where('id', '!=', $me->id)
            ->orderBy('name')
            ->get();

        $conversations = $this->conversationList($me->id);

        $activeConversation = null;
        $messages = collect();

        if ($request->filled('conversation_id')) {
            $activeConversation = Conversation::query()
                ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
                ->find($request->integer('conversation_id'));

            if ($activeConversation) {
                $messages = $this->messagesForConversation($activeConversation->id);
                $this->markRead($activeConversation->id, $me->id);
            }
        }

        return view('chat.index', [
            'users' => $users,
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'messages' => $messages,
            'myId' => $me->id,
        ]);
    }

    /**
     * Danh sách người dùng có thể bắt đầu chat (trừ bản thân).
     */
    public function users()
    {
        $users = User::query()
            ->where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get();

        return view('chat.user', compact('users'));
    }

    /**
     * Mở (hoặc tạo mới) hội thoại 1-1 với người dùng rồi chuyển tới trang hội thoại.
     */
    public function direct(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $conversation = $this->findOrCreateDirectConversation(auth()->id(), (int) $request->user_id);

        return redirect()->route('chat.show', $conversation->id);
    }

    /**
     * Mở (hoặc tạo mới) hội thoại 1-1 và trả dữ liệu hội thoại + tin nhắn dạng JSON.
     */
    public function directJson(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $conversation = $this->findOrCreateDirectConversation(auth()->id(), (int) $request->user_id);

        return response()->json([
            'ok' => true,
            'conversation_id' => $conversation->id,
            'conversation' => $this->conversationPayload($conversation, auth()->id()),
            'messages' => $this->messagePayloads($this->messagesForConversation($conversation->id)),
        ]);
    }

    /**
     * Hiển thị trang một hội thoại và đánh dấu đã đọc.
     */
    public function show(Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $messages = $this->messagesForConversation($conversation->id);
        $this->markRead($conversation->id, auth()->id());

        return view('chat.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'myId' => auth()->id(),
        ]);
    }

    /**
     * Gửi tin nhắn vào hội thoại rồi quay lại trang hội thoại.
     */
    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->createMessage($conversation, auth()->id(), $request->body);

        return redirect()
            ->route('chat.show', $conversation->id)
            ->with('success', 'Đã gửi tin nhắn.');
    }

    /**
     * Gửi tin nhắn qua AJAX, trả về tin nhắn mới và hội thoại đã cập nhật dạng JSON.
     */
    public function sendJson(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->createMessage($conversation, auth()->id(), $request->body);

        return response()->json([
            'ok' => true,
            'message' => $this->messagePayload($message),
            'conversation' => $this->conversationPayload($conversation->fresh(), auth()->id()),
        ]);
    }

    /**
     * Lấy tin nhắn mới của hội thoại (sau after_id) dạng JSON và đánh dấu đã đọc.
     */
    public function messagesJson(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $afterId = (int) $request->query('after_id', 0);

        $query = Message::query()
            ->with('sender')
            ->where('conversation_id', $conversation->id)
            ->orderBy('id');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $messages = $query->limit(80)->get();

        $this->markRead($conversation->id, auth()->id());

        return response()->json([
            'ok' => true,
            'messages' => $this->messagePayloads($messages),
        ]);
    }

    /**
     * Dữ liệu JSON cho widget chat: thông tin bản thân, người dùng và danh sách hội thoại.
     */
    public function widgetData()
    {
        $me = auth()->user();

        $users = User::query()
            ->where('id', '!=', $me->id)
            ->orderBy('name')
            ->limit(80)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name ?: 'Không rõ',
                'email' => $u->email,
                'initial' => mb_strtoupper(mb_substr($u->name ?: $u->email ?: 'U', 0, 1)),
                'online' => false,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'me' => [
                'id' => $me->id,
                'name' => $me->name,
            ],
            'users' => $users,
            'conversations' => $this->conversationList($me->id),
        ]);
    }

    /**
     * Tìm hội thoại 1-1 giữa hai người dùng, chưa có thì tạo mới trong transaction.
     */
    private function findOrCreateDirectConversation(int $meId, int $otherId): Conversation
    {
        if ($meId === $otherId) {
            abort(422, 'Không thể chat với chính mình.');
        }

        $conversation = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('users', fn ($q) => $q->where('users.id', $meId))
            ->whereHas('users', fn ($q) => $q->where('users.id', $otherId))
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return DB::transaction(function () use ($meId, $otherId) {
            $conversation = Conversation::create([
                'type' => 'direct',
                'created_by' => $meId,
                'last_message_at' => now(),
            ]);

            $conversation->users()->syncWithoutDetaching([$meId, $otherId]);

            return $conversation;
        });
    }

    /**
     * Tạo tin nhắn mới, cập nhật mốc thời gian hội thoại và đánh dấu chưa đọc cho người còn lại.
     */
    private function createMessage(Conversation $conversation, int $userId, string $body): Message
    {
        return DB::transaction(function () use ($conversation, $userId, $body) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'body' => trim($body),
            ]);

            $conversation->forceFill([
                'last_message_at' => now(),
            ])->save();

            if (Schema::hasTable('conversation_user')) {
                DB::table('conversation_user')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', '!=', $userId)
                    ->update(['updated_at' => now()]);
            }

            return $message->load('sender');
        });
    }

    /**
     * Chặn 403 nếu người dùng hiện tại không thuộc hội thoại.
     */
    private function authorizeConversation(Conversation $conversation): void
    {
        $allowed = $conversation->users()
            ->where('users.id', auth()->id())
            ->exists();

        abort_unless($allowed, 403);
    }

    /**
     * Lấy tối đa 300 tin nhắn của hội thoại theo thứ tự cũ đến mới.
     */
    private function messagesForConversation(int $conversationId)
    {
        return Message::query()
            ->with('sender')
            ->where('conversation_id', $conversationId)
            ->orderBy('id')
            ->limit(300)
            ->get();
    }

    /**
     * Danh sách hội thoại của người dùng (mới nhất trước) đã chuyển thành payload hiển thị.
     */
    private function conversationList(int $meId)
    {
        return Conversation::query()
            ->with(['users', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->whereHas('users', fn ($q) => $q->where('users.id', $meId))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn ($conversation) => $this->conversationPayload($conversation, $meId))
            ->values();
    }

    /**
     * Chuyển hội thoại thành mảng hiển thị: tên đối phương, tin nhắn cuối, thời gian.
     */
    private function conversationPayload(Conversation $conversation, int $meId): array
    {
        $other = $conversation->users->firstWhere('id', '!=', $meId);
        $last = $conversation->messages->first();

        if (! $last) {
            $last = Message::query()
                ->with('sender')
                ->where('conversation_id', $conversation->id)
                ->latest('id')
                ->first();
        }

        return [
            'id' => $conversation->id,
            'title' => $other->name ?? 'Không rõ',
            'subtitle' => $other->email ?? '',
            'initial' => mb_strtoupper(mb_substr($other->name ?? $other->email ?? 'U', 0, 1)),
            'other_user_id' => $other->id ?? null,
            'last_message' => $last?->body ?: 'Chưa có tin nhắn',
            'last_time' => optional($last?->created_at ?: $conversation->last_message_at)->format('d/m H:i'),
            'updated_at' => optional($conversation->last_message_at ?: $conversation->updated_at)->timestamp,
        ];
    }

    /**
     * Chuyển danh sách tin nhắn thành mảng payload JSON.
     */
    private function messagePayloads($messages): array
    {
        return collect($messages)->map(fn ($m) => $this->messagePayload($m))->values()->all();
    }

    /**
     * Chuyển một tin nhắn thành mảng payload (kèm tên người gửi, thời gian định dạng).
     */
    private function messagePayload(Message $message): array
    {
        $message->loadMissing('sender');

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'sender_name' => $message->sender->name ?? 'Không rõ',
            'body' => $message->body,
            'created_at' => optional($message->created_at)->format('d/m H:i'),
            'created_at_full' => optional($message->created_at)->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * Đánh dấu hội thoại là đã đọc cho người dùng (nếu bảng/cột hỗ trợ).
     */
    private function markRead(int $conversationId, int $userId): void
    {
        if (! Schema::hasTable('conversation_user') || ! Schema::hasColumn('conversation_user', 'last_read_at')) {
            return;
        }

        DB::table('conversation_user')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }
}
