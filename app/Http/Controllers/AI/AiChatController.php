<?php

declare(strict_types=1);

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AI\AiConversation;
use App\Models\AI\AiMessage;
use App\Models\AI\AiProvider;
use App\Models\User;
use App\Services\AI\AiAccessService;
use App\Services\AI\AiGateway;
use App\Services\AI\AiPromptBuilder;
use App\Services\AI\AiToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AiChatController extends Controller
{
    public function index(Request $request, AiAccessService $access): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($access->canUse($user), 403, 'Tài khoản chưa được cấp quyền sử dụng EGO AI Copilot.');

        $conversations = AiConversation::query()
            ->where('user_id', $user->id)
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $canManageProviders = $access->isAdmin($user) || $user->can('ai.providers.manage');
        $providersQuery = AiProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (! $canManageProviders) {
            $providersQuery->where('is_default', true);
        }

        $providers = $providersQuery->get(['id', 'name', 'provider_type', 'model', 'is_default']);
        if ($providers->isEmpty() && ! $canManageProviders) {
            $default = AiProvider::activeDefault();
            $providers = $default ? collect([$default]) : collect();
        }

        $todayUsage = Schema::hasTable('ai_usage_logs')
            ? (int) DB::table('ai_usage_logs')->where('user_id', $user->id)->whereDate('created_at', today())->count()
            : 0;

        return view('ai.index', [
            'conversations' => $conversations,
            'providers' => $providers,
            'defaultProvider' => $providers->firstWhere('is_default', true) ?: $providers->first(),
            'isAdmin' => $access->isAdmin($user),
            'canManageProviders' => $canManageProviders,
            'canViewAudit' => $access->isAdmin($user) || $user->can('ai.audit.view'),
            'accessProfile' => $access->profile($user),
            'todayUsage' => $todayUsage,
        ]);
    }

    public function create(Request $request, AiAccessService $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($access->canUse($user), 403);
        $provider = $this->resolveProvider($request, $access);

        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'provider_id' => $provider?->id,
            'title' => 'Cuộc trò chuyện mới',
            'last_message_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'conversation' => $this->conversationPayload($conversation),
        ]);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->assertOwner($request, $conversation);

        $messages = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->map(fn (AiMessage $message): array => $this->messagePayload($message));

        return response()->json([
            'ok' => true,
            'conversation' => $this->conversationPayload($conversation),
            'messages' => $messages,
        ]);
    }

    public function send(
        Request $request,
        AiGateway $gateway,
        AiPromptBuilder $promptBuilder,
        AiToolRegistry $tools,
        AiAccessService $access
    ): JsonResponse {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'provider_id' => ['nullable', 'integer', Rule::exists('ai_providers', 'id')->where('is_active', true)],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'path' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();
        abort_unless($access->canUse($user), 403, 'Tài khoản chưa được cấp quyền sử dụng AI.');

        $limit = $access->dailyLimit($user);
        $usedToday = Schema::hasTable('ai_usage_logs')
            ? (int) DB::table('ai_usage_logs')->where('user_id', $user->id)->whereDate('created_at', today())->count()
            : 0;

        if (! $access->isAdmin($user) && $usedToday >= $limit) {
            return response()->json([
                'ok' => false,
                'code' => 'AI_DAILY_LIMIT',
                'message' => "Bạn đã dùng hết hạn mức {$limit} yêu cầu AI trong ngày. Vui lòng liên hệ Admin nếu cần tăng hạn mức.",
            ], 429);
        }

        $provider = $this->resolveProvider($request, $access);
        if (! $provider) {
            return response()->json([
                'ok' => false,
                'code' => 'AI_PROVIDER_MISSING',
                'message' => 'Chưa có kết nối AI đang hoạt động. Admin cần cấu hình API trước.',
                'settings_url' => ($access->isAdmin($user) || $user->can('ai.providers.manage')) ? route('admin.settings.ai.index') : null,
            ], 422);
        }

        $conversation = $validated['conversation_id'] ?? null
            ? AiConversation::query()->findOrFail((int) $validated['conversation_id'])
            : AiConversation::query()->create([
                'user_id' => $user->id,
                'provider_id' => $provider->id,
                'title' => 'Cuộc trò chuyện mới',
                'last_message_at' => now(),
            ]);

        $this->assertOwner($request, $conversation);
        if ((int) $conversation->provider_id !== (int) $provider->id) {
            $conversation->update(['provider_id' => $provider->id]);
        }

        $text = trim((string) $validated['message']);
        $previousUserMessages = $conversation->messages()
            ->where('role', 'user')
            ->latest('id')
            ->limit(8)
            ->pluck('content')
            ->reverse()
            ->values()
            ->all();

        $previousAssistant = $conversation->messages()
            ->where('role', 'assistant')
            ->latest('id')
            ->first(['metadata']);
        $previousTool = data_get($previousAssistant?->metadata, 'tool_primary');

        $userMessage = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $text,
        ]);

        if ($conversation->title === 'Cuộc trò chuyện mới') {
            $conversation->title = Str::limit(preg_replace('/\s+/', ' ', $text) ?: $text, 68, '…');
        }
        $conversation->last_message_at = now();
        $conversation->save();

        $toolRun = $tools->run($user, $text, $previousUserMessages, is_string($previousTool) ? $previousTool : null);

        if (($toolRun['denied'] ?? false) === true) {
            $deniedText = (string) ($toolRun['message'] ?? 'Bạn chưa được cấp quyền truy cập dữ liệu này.');
            $assistantMessage = AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'role' => 'assistant',
                'content' => $deniedText,
                'model' => 'EGO Permission Guard',
                'metadata' => [
                    'permission_denied' => true,
                    'denied_module' => $toolRun['denied_module'] ?? null,
                    'access_profile' => $access->profile($user),
                ],
            ]);

            $this->logTool($user, $conversation, [
                'tool' => (string) ($toolRun['denied_module'] ?? 'unknown'),
                'status' => 'denied',
                'scope' => 'none',
                'denial_reason' => $deniedText,
                'query' => $text,
            ]);

            return response()->json([
                'ok' => true,
                'conversation' => $this->conversationPayload($conversation->fresh()),
                'user_message' => $this->messagePayload($userMessage),
                'assistant_message' => $this->messagePayload($assistantMessage),
                'provider' => ['id' => null, 'name' => 'EGO Permission Guard', 'model' => 'Server-side'],
                'crm_context' => null,
                'access' => $access->profile($user),
            ]);
        }

        foreach ((array) ($toolRun['contexts'] ?? []) as $context) {
            $this->logTool($user, $conversation, [
                'tool' => (string) ($context['tool'] ?? 'unknown'),
                'status' => 'success',
                'scope' => (string) ($context['scope'] ?? ''),
                'period_label' => (string) ($context['period'] ?? ''),
                'result_count' => count((array) ($context['items'] ?? [])),
                'query' => $text,
                'metadata' => ['title' => $context['title'] ?? null],
            ]);
        }

        $crmContext = $toolRun['combined'] ?? null;
        $history = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest('id')
            ->limit(18)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn (AiMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();

        $startedAt = microtime(true);

        try {
            $result = $gateway->chat(
                $provider,
                $promptBuilder->build($user, is_array($crmContext) ? $crmContext : null),
                $history
            );

            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
            $primaryTool = data_get($toolRun, 'plan.primary');

            $assistantMessage = AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'role' => 'assistant',
                'content' => $result['text'],
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'total_tokens' => $result['total_tokens'],
                'metadata' => [
                    'provider_id' => $provider->id,
                    'provider_name' => $provider->name,
                    'crm_context_used' => $crmContext !== null,
                    'crm_context' => $crmContext,
                    'tool_primary' => $primaryTool,
                    'tools' => data_get($toolRun, 'plan.tools', []),
                    'latency_ms' => $latencyMs,
                    'access_profile' => $access->profile($user),
                ],
            ]);

            $conversation->update(['last_message_at' => now()]);
            $this->logUsage($provider, $user, $conversation, $result, $latencyMs, null);

            return response()->json([
                'ok' => true,
                'conversation' => $this->conversationPayload($conversation->fresh()),
                'user_message' => $this->messagePayload($userMessage),
                'assistant_message' => $this->messagePayload($assistantMessage),
                'provider' => [
                    'id' => $provider->id,
                    'name' => $provider->name,
                    'model' => $result['model'],
                ],
                'crm_context' => $crmContext,
                'access' => $access->profile($user),
                'usage' => ['used_today' => $usedToday + 1, 'daily_limit' => $limit],
            ]);
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            Log::error('EGO AI request failed', [
                'user_id' => $user->id,
                'provider_id' => $provider->id,
                'conversation_id' => $conversation->id,
                'message' => $e->getMessage(),
            ]);

            $this->logUsage($provider, $user, $conversation, null, $latencyMs, $e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'conversation' => $this->conversationPayload($conversation),
                'user_message' => $this->messagePayload($userMessage),
            ], 422);
        }
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->assertOwner($request, $conversation);
        $conversation->delete();
        return response()->json(['ok' => true]);
    }

    private function resolveProvider(Request $request, AiAccessService $access): ?AiProvider
    {
        /** @var User $user */
        $user = $request->user();
        $canChoose = $access->isAdmin($user) || $user->can('ai.providers.manage');
        $providerId = $canChoose ? (int) $request->input('provider_id', 0) : 0;

        if ($providerId > 0) {
            return AiProvider::query()->whereKey($providerId)->where('is_active', true)->first();
        }

        return AiProvider::activeDefault();
    }

    private function assertOwner(Request $request, AiConversation $conversation): void
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 403);
    }

    private function conversationPayload(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'provider_id' => $conversation->provider_id,
            'last_message_at' => optional($conversation->last_message_at)->format('d/m/Y H:i'),
            'show_url' => route('ai.conversations.show', $conversation),
            'delete_url' => route('ai.conversations.destroy', $conversation),
        ];
    }

    private function messagePayload(AiMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'model' => $message->model,
            'metadata' => $message->metadata,
            'created_at' => $message->created_at?->format('H:i d/m/Y'),
        ];
    }

    /** @param array<string, mixed> $data */
    private function logTool(User $user, AiConversation $conversation, array $data): void
    {
        if (! Schema::hasTable('ai_tool_logs')) {
            return;
        }

        $module = (string) ($data['tool'] ?? 'unknown');
        DB::table('ai_tool_logs')->insert([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'tool' => $module,
            'module' => $module,
            'permission' => (string) config("ego_ai.modules.{$module}.permission", ''),
            'scope' => $data['scope'] ?? null,
            'status' => $data['status'] ?? 'success',
            'period_label' => $data['period_label'] ?? null,
            'result_count' => (int) ($data['result_count'] ?? 0),
            'query_hash' => hash('sha256', (string) ($data['query'] ?? '')),
            'denial_reason' => $data['denial_reason'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed>|null $result */
    private function logUsage(AiProvider $provider, User $user, AiConversation $conversation, ?array $result, int $latencyMs, ?string $error): void
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            return;
        }

        DB::table('ai_usage_logs')->insert([
            'provider_id' => $provider->id,
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'request_id' => $result['request_id'] ?? null,
            'model' => $result['model'] ?? $provider->model,
            'status' => $error ? 'error' : 'success',
            'input_tokens' => (int) ($result['input_tokens'] ?? 0),
            'output_tokens' => (int) ($result['output_tokens'] ?? 0),
            'total_tokens' => (int) ($result['total_tokens'] ?? 0),
            'latency_ms' => $latencyMs,
            'error_message' => $error ? Str::limit($error, 1000) : null,
            'created_at' => now(),
        ]);
    }
}
