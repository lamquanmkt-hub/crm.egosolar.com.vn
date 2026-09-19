<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AI\AiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AiGateway
{
    /**
     * @param array<int, array{role:string,content:string}> $messages
     * @return array{text:string,model:string,request_id:?string,input_tokens:int,output_tokens:int,total_tokens:int,raw:array}
     */
    public function chat(AiProvider $provider, string $systemPrompt, array $messages): array
    {
        if (! $provider->is_active) {
            throw new RuntimeException('Kết nối AI đang bị tắt.');
        }

        if (trim((string) $provider->api_key) === '') {
            throw new RuntimeException('Kết nối AI chưa có API key.');
        }

        return match ($provider->provider_type) {
            'openai' => $this->openAiResponses($provider, $systemPrompt, $messages),
            'gemini' => $this->gemini($provider, $systemPrompt, $messages),
            'anthropic' => $this->anthropic($provider, $systemPrompt, $messages),
            'openrouter', 'deepseek', 'groq', 'openai_compatible', 'custom' =>
                $this->openAiCompatible($provider, $systemPrompt, $messages),
            default => throw new RuntimeException('Nhà cung cấp AI không được hỗ trợ.'),
        };
    }

    public function test(AiProvider $provider): array
    {
        $result = $this->chat(
            $provider,
            'Bạn là bài kiểm tra kết nối API. Chỉ trả lời chính xác: KẾT NỐI THÀNH CÔNG',
            [['role' => 'user', 'content' => 'Kiểm tra kết nối.']]
        );

        return [
            'ok' => true,
            'message' => trim($result['text']),
            'model' => $result['model'],
        ];
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     */
    private function openAiResponses(AiProvider $provider, string $systemPrompt, array $messages): array
    {
        $baseUrl = $this->baseUrl($provider, 'https://api.openai.com/v1');
        $headers = [];

        if ($provider->organization) {
            $headers['OpenAI-Organization'] = $provider->organization;
        }
        if ($provider->project) {
            $headers['OpenAI-Project'] = $provider->project;
        }

        $payload = [
            'model' => $provider->model,
            'instructions' => $systemPrompt,
            'input' => array_map(
                static fn (array $message): array => [
                    'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => $message['content'],
                ],
                $messages
            ),
            'max_output_tokens' => max(128, (int) $provider->max_output_tokens),
            'store' => false,
        ];

        $response = $this->request($provider, $headers)
            ->withToken((string) $provider->api_key)
            ->post($baseUrl.'/responses', $payload);

        $data = $this->successfulJson($response);
        $text = trim((string) ($data['output_text'] ?? ''));

        if ($text === '') {
            $parts = [];
            foreach (($data['output'] ?? []) as $output) {
                foreach (($output['content'] ?? []) as $content) {
                    if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                        $parts[] = (string) $content['text'];
                    }
                }
            }
            $text = trim(implode("\n", $parts));
        }

        if ($text === '') {
            throw new RuntimeException('API OpenAI không trả về nội dung văn bản.');
        }

        $usage = (array) ($data['usage'] ?? []);

        return $this->result(
            $text,
            (string) ($data['model'] ?? $provider->model),
            isset($data['id']) ? (string) $data['id'] : null,
            (int) ($usage['input_tokens'] ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
            (int) ($usage['total_tokens'] ?? 0),
            $data
        );
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     */
    private function openAiCompatible(AiProvider $provider, string $systemPrompt, array $messages): array
    {
        $defaults = [
            'openrouter' => 'https://openrouter.ai/api/v1',
            'deepseek' => 'https://api.deepseek.com',
            'groq' => 'https://api.groq.com/openai/v1',
            'openai_compatible' => '',
            'custom' => '',
        ];

        $baseUrl = $this->baseUrl($provider, $defaults[$provider->provider_type] ?? '');
        if ($baseUrl === '') {
            throw new RuntimeException('Vui lòng nhập Base URL cho kết nối này.');
        }

        $headers = [];
        if ($provider->provider_type === 'openrouter') {
            $headers['HTTP-Referer'] = (string) config('app.url');
            $headers['X-Title'] = 'EGO Solar CRM';
        }

        $chatMessages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($messages as $message) {
            $chatMessages[] = [
                'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $message['content'],
            ];
        }

        $payload = [
            'model' => $provider->model,
            'messages' => $chatMessages,
            'temperature' => (float) $provider->temperature,
            'max_tokens' => max(128, (int) $provider->max_output_tokens),
            'stream' => false,
        ];

        $response = $this->request($provider, $headers)
            ->withToken((string) $provider->api_key)
            ->post($baseUrl.'/chat/completions', $payload);

        $data = $this->successfulJson($response);
        $text = trim((string) data_get($data, 'choices.0.message.content', ''));

        if ($text === '') {
            throw new RuntimeException('API không trả về nội dung văn bản.');
        }

        $usage = (array) ($data['usage'] ?? []);
        $input = (int) ($usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($input + $output));

        return $this->result(
            $text,
            (string) ($data['model'] ?? $provider->model),
            isset($data['id']) ? (string) $data['id'] : null,
            $input,
            $output,
            $total,
            $data
        );
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     */
    private function gemini(AiProvider $provider, string $systemPrompt, array $messages): array
    {
        $baseUrl = $this->baseUrl($provider, 'https://generativelanguage.googleapis.com/v1beta');
        $contents = [];

        foreach ($messages as $message) {
            $contents[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) $provider->temperature,
                'maxOutputTokens' => max(128, (int) $provider->max_output_tokens),
            ],
        ];

        $endpoint = sprintf(
            '%s/models/%s:generateContent?key=%s',
            $baseUrl,
            rawurlencode($provider->model),
            rawurlencode((string) $provider->api_key)
        );

        $response = $this->request($provider)->post($endpoint, $payload);
        $data = $this->successfulJson($response);

        $parts = data_get($data, 'candidates.0.content.parts', []);
        $text = trim(collect(is_array($parts) ? $parts : [])
            ->pluck('text')
            ->filter()
            ->implode("\n"));

        if ($text === '') {
            throw new RuntimeException('Gemini không trả về nội dung văn bản.');
        }

        $usage = (array) ($data['usageMetadata'] ?? []);
        $input = (int) ($usage['promptTokenCount'] ?? 0);
        $output = (int) ($usage['candidatesTokenCount'] ?? 0);
        $total = (int) ($usage['totalTokenCount'] ?? ($input + $output));

        return $this->result(
            $text,
            $provider->model,
            null,
            $input,
            $output,
            $total,
            $data
        );
    }

    /**
     * @param array<int, array{role:string,content:string}> $messages
     */
    private function anthropic(AiProvider $provider, string $systemPrompt, array $messages): array
    {
        $baseUrl = $this->baseUrl($provider, 'https://api.anthropic.com/v1');
        $anthropicMessages = [];

        foreach ($messages as $message) {
            $anthropicMessages[] = [
                'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $message['content'],
            ];
        }

        $payload = [
            'model' => $provider->model,
            'system' => $systemPrompt,
            'messages' => $anthropicMessages,
            'max_tokens' => max(128, (int) $provider->max_output_tokens),
            'temperature' => (float) $provider->temperature,
        ];

        $response = $this->request($provider, [
            'x-api-key' => (string) $provider->api_key,
            'anthropic-version' => '2023-06-01',
        ])->post($baseUrl.'/messages', $payload);

        $data = $this->successfulJson($response);
        $text = trim(collect((array) ($data['content'] ?? []))
            ->where('type', 'text')
            ->pluck('text')
            ->filter()
            ->implode("\n"));

        if ($text === '') {
            throw new RuntimeException('Claude không trả về nội dung văn bản.');
        }

        $usage = (array) ($data['usage'] ?? []);
        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);

        return $this->result(
            $text,
            (string) ($data['model'] ?? $provider->model),
            isset($data['id']) ? (string) $data['id'] : null,
            $input,
            $output,
            $input + $output,
            $data
        );
    }

    private function request(AiProvider $provider, array $headers = []): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->connectTimeout(min(20, max(3, (int) $provider->timeout_seconds)))
            ->timeout(max(10, (int) $provider->timeout_seconds))
            ->retry(1, 300, throw: false);
    }

    private function successfulJson(Response $response): array
    {
        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if (! $response->successful()) {
            $message = (string) (
                data_get($data, 'error.message')
                ?? data_get($data, 'message')
                ?? ('HTTP '.$response->status())
            );

            throw new RuntimeException('API AI báo lỗi: '.$message);
        }

        return $data;
    }

    private function baseUrl(AiProvider $provider, string $default): string
    {
        $value = trim((string) ($provider->base_url ?: $default));

        return rtrim($value, '/');
    }

    private function result(
        string $text,
        string $model,
        ?string $requestId,
        int $inputTokens,
        int $outputTokens,
        int $totalTokens,
        array $raw
    ): array {
        return [
            'text' => $text,
            'model' => $model,
            'request_id' => $requestId,
            'input_tokens' => max(0, $inputTokens),
            'output_tokens' => max(0, $outputTokens),
            'total_tokens' => max(0, $totalTokens),
            'raw' => $raw,
        ];
    }
}
