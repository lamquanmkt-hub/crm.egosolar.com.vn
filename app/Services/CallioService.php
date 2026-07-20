<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Service tích hợp tổng đài Callio: lấy lịch sử cuộc gọi và link ghi âm.
 */
class CallioService
{
    /**
     * Kiểm tra Callio đã được cấu hình (base_url + token) hay chưa.
     */
    public function enabled(): bool
    {
        return filled(config('services.callio.base_url')) && filled(config('services.callio.token'));
    }

    /**
     * Lấy danh sách cuộc gọi từ Callio theo ngày (phân trang, tìm kiếm từ khóa).
     *
     * @return array Kết quả gồm ok, message, docs và meta.
     */
    public function fetchCallsForDate(Carbon $date, int $page = 1, int $pageSize = 15, ?string $keyword = null): array
    {
        if (!$this->enabled()) {
            return [
                'ok' => false,
                'message' => 'Callio chưa được cấu hình.',
                'docs' => [],
                'meta' => [
                    'base_url' => config('services.callio.base_url'),
                    'token_filled' => filled(config('services.callio.token')),
                ],
            ];
        }

        // Callio yêu cầu Unix epoch time dạng milliseconds
        $from = $date->copy()->startOfDay()->valueOf();
        $to = $date->copy()->endOfDay()->valueOf();

        $query = [
            'page' => $page,
            'pageSize' => $pageSize,
            'from' => $from,
            'to' => $to,
        ];

        if (filled($keyword)) {
            $query['keyword'] = $keyword;
        }

        $url = rtrim(config('services.callio.base_url'), '/') . '/call';

        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders([
                'token' => config('services.callio.token'),
            ])
            ->get($url, $query);

        if (!$response->successful()) {
            return [
                'ok' => false,
                'message' => 'Không thể lấy dữ liệu cuộc gọi từ Callio.',
                'docs' => [],
                'meta' => [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 1500),
                    'request_url' => $url,
                    'query' => $query,
                ],
            ];
        }

        $json = $response->json();

        $docs = collect($json['docs'] ?? [])->map(function ($item) {
            $directionRaw = (string)($item['direction'] ?? '');

            $directionLabel = match ($directionRaw) {
                '1' => 'Gọi vào',
                '2' => 'Gọi ra',
                '3' => 'Nội bộ',
                default => 'Không rõ',
            };

            $startTime = isset($item['startTime']) ? (int)$item['startTime'] : null;
            $answerTime = isset($item['answerTime']) ? (int)$item['answerTime'] : null;
            $endTime = isset($item['endTime']) ? (int)$item['endTime'] : null;
            $duration = (int)($item['duration'] ?? 0);

            $phone = match ($directionRaw) {
                '1' => $item['fromNumber'] ?? '-', // gọi vào => số khách thường nằm ở fromNumber
                '2' => $item['toNumber'] ?? '-',   // gọi ra => số khách thường nằm ở toNumber
                default => ($item['toNumber'] ?? $item['fromNumber'] ?? '-'),
            };

            $ext = match ($directionRaw) {
                '1' => $item['toExt'] ?? null,
                '2' => $item['fromExt'] ?? null,
                default => $item['fromExt'] ?? $item['toExt'] ?? null,
            };

            return [
                'id' => $item['_id'] ?? null,
                'direction' => $directionRaw,
                'direction_label' => $directionLabel,
                'phone' => $phone,
                'extension' => $ext,
                'from_number' => $item['fromNumber'] ?? null,
                'to_number' => $item['toNumber'] ?? null,
                'from_ext' => $item['fromExt'] ?? null,
                'to_ext' => $item['toExt'] ?? null,
                'start_time' => $startTime,
                'answer_time' => $answerTime,
                'end_time' => $endTime,
                'started_at' => $startTime ? Carbon::createFromTimestampMs($startTime)->format('d/m/Y H:i:s') : null,
                'answered_at' => $answerTime ? Carbon::createFromTimestampMs($answerTime)->format('d/m/Y H:i:s') : null,
                'ended_at' => $endTime ? Carbon::createFromTimestampMs($endTime)->format('d/m/Y H:i:s') : null,
                'duration' => $duration,
                'duration_human' => gmdate('H:i:s', max(0, $duration)),
                'hangup_cause' => $item['hangupCause'] ?? null,
                'recording_duration' => (int)($item['recordingDuration'] ?? 0),
                'has_recording' => (int)($item['recordingDuration'] ?? 0) > 0,
                'raw' => $item,
            ];
        })->values()->all();

        return [
            'ok' => true,
            'message' => null,
            'docs' => $docs,
            'meta' => [
                'status' => $response->status(),
                'request_url' => $url,
                'query' => $query,
                'total' => $json['totalDocs'] ?? count($docs),
                'page' => $json['page'] ?? $page,
                'has_next_page' => $json['hasNextPage'] ?? false,
                'raw_keys' => array_keys($json),
            ],
        ];
    }

    /**
     * Lấy link ghi âm của một cuộc gọi theo ID.
     */
    public function fetchRecordingUrl(string $callId): array
    {
        if (!$this->enabled()) {
            return [
                'ok' => false,
                'message' => 'Callio chưa được cấu hình.',
                'url' => null,
            ];
        }

        $url = rtrim(config('services.callio.base_url'), '/') . '/call/' . $callId . '/recording';

        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders([
                'token' => config('services.callio.token'),
            ])
            ->get($url);

        if (!$response->successful()) {
            return [
                'ok' => false,
                'message' => 'Không thể lấy link ghi âm.',
                'url' => null,
                'meta' => [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 1000),
                    'request_url' => $url,
                ],
            ];
        }

        $json = $response->json();

        return [
            'ok' => true,
            'message' => null,
            'url' => $json['url'] ?? null,
        ];
    }
}