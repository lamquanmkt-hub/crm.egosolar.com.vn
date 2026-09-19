<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AI\AiProvider;
use App\Services\AI\AiGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AiProviderController extends Controller
{
    private const TYPES = [
        'openai' => 'OpenAI',
        'gemini' => 'Google Gemini',
        'anthropic' => 'Anthropic Claude',
        'openrouter' => 'OpenRouter',
        'deepseek' => 'DeepSeek',
        'groq' => 'Groq',
        'openai_compatible' => 'OpenAI Compatible',
        'custom' => 'Custom API',
    ];

    public function index(): View
    {
        $providers = AiProvider::query()
            ->with('creator:id,name')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $usage = DB::table('ai_usage_logs')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(AVG(latency_ms), 0) as latency')
            ->first();

        return view('admin.settings.ai-providers', [
            'providers' => $providers,
            'providerTypes' => self::TYPES,
            'usage' => $usage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, true);

        DB::transaction(function () use ($validated, $request): void {
            if ($request->boolean('is_default') || ! AiProvider::query()->exists()) {
                AiProvider::query()->update(['is_default' => false]);
                $validated['is_default'] = true;
            }

            $validated['created_by'] = $request->user()->id;
            AiProvider::query()->create($validated);
        });

        return back()->with('success', 'Đã thêm kết nối AI. Hãy bấm Kiểm tra kết nối trước khi sử dụng.');
    }

    public function update(Request $request, AiProvider $provider): RedirectResponse
    {
        $validated = $this->validated($request, false);

        if (trim((string) ($validated['api_key'] ?? '')) === '') {
            unset($validated['api_key']);
        }

        DB::transaction(function () use ($validated, $request, $provider): void {
            if ($request->boolean('is_default')) {
                AiProvider::query()->where('id', '!=', $provider->id)->update(['is_default' => false]);
                $validated['is_default'] = true;
            } elseif ($provider->is_default) {
                $validated['is_default'] = true;
            }

            $provider->update($validated);
        });

        return back()->with('success', 'Đã cập nhật kết nối AI.');
    }

    public function destroy(AiProvider $provider): RedirectResponse
    {
        if ($provider->is_default && AiProvider::query()->where('id', '!=', $provider->id)->where('is_active', true)->exists()) {
            $next = AiProvider::query()->where('id', '!=', $provider->id)->where('is_active', true)->first();
            $next?->update(['is_default' => true]);
        }

        $provider->delete();

        return back()->with('success', 'Đã xóa kết nối AI. Lịch sử hội thoại vẫn được giữ.');
    }

    public function setDefault(AiProvider $provider): RedirectResponse
    {
        abort_unless($provider->is_active, 422, 'Không thể chọn kết nối đang tắt làm mặc định.');

        DB::transaction(function () use ($provider): void {
            AiProvider::query()->update(['is_default' => false]);
            $provider->update(['is_default' => true]);
        });

        return back()->with('success', 'Đã chọn '.$provider->name.' làm kết nối mặc định.');
    }

    public function test(AiProvider $provider, AiGateway $gateway): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $result = $gateway->test($provider);

            return response()->json([
                'ok' => true,
                'message' => $result['message'],
                'model' => $result['model'],
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'provider_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'base_url' => ['nullable', 'url:http,https', 'max:500'],
            'model' => ['required', 'string', 'max:160'],
            'fast_model' => ['nullable', 'string', 'max:160'],
            'api_key' => [$creating ? 'required' : 'nullable', 'string', 'max:2000'],
            'organization' => ['nullable', 'string', 'max:160'],
            'project' => ['nullable', 'string', 'max:160'],
            'timeout_seconds' => ['required', 'integer', 'min:10', 'max:180'],
            'max_output_tokens' => ['required', 'integer', 'min:128', 'max:16000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ], [
            'api_key.required' => 'Vui lòng nhập API key.',
            'base_url.url' => 'Base URL phải bắt đầu bằng http:// hoặc https://.',
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
        ];
    }
}
