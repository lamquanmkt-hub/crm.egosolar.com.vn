<?php

declare(strict_types=1);

namespace App\Services\AI;

use Carbon\Carbon;
use Illuminate\Support\Str;

final class AiDateResolver
{
    /**
     * @param array<int, string> $previousUserMessages
     * @return array{from:Carbon,to:Carbon,label:string,explicit:bool,key:string}
     */
    public function resolve(string $text, array $previousUserMessages = [], string $default = 'month'): array
    {
        $resolved = $this->fromText($text);
        if ($resolved !== null) {
            return $resolved + ['explicit' => true];
        }

        foreach (array_reverse($previousUserMessages) as $previous) {
            $resolved = $this->fromText((string) $previous);
            if ($resolved !== null) {
                return $resolved + ['explicit' => false];
            }
        }

        return $this->defaultPeriod($default) + ['explicit' => false];
    }

    /** @return array{from:Carbon,to:Carbon,label:string,key:string}|null */
    private function fromText(string $text): ?array
    {
        $raw = mb_strtolower(trim($text), 'UTF-8');
        $normalized = mb_strtolower(Str::ascii($raw), 'UTF-8');
        $now = now();

        if (preg_match('/(?:tu|từ)\s+ngay\s+(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\s+(?:den|đến)\s+ngay\s+(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?/u', $normalized, $m)) {
            $from = $this->makeDate((int) $m[1], (int) $m[2], $m[3] ?? null, $now);
            $to = $this->makeDate((int) $m[4], (int) $m[5], $m[6] ?? null, $now);
            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }

            return [
                'from' => $from->startOfDay(),
                'to' => $to->endOfDay(),
                'label' => 'từ '.$from->format('d/m/Y').' đến '.$to->format('d/m/Y'),
                'key' => 'custom_range',
            ];
        }

        if (preg_match('/(?:ngay|ngày)\s+(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?/u', $normalized, $m)) {
            $date = $this->makeDate((int) $m[1], (int) $m[2], $m[3] ?? null, $now);
            return [
                'from' => $date->copy()->startOfDay(),
                'to' => $date->copy()->endOfDay(),
                'label' => 'ngày '.$date->format('d/m/Y'),
                'key' => 'date',
            ];
        }

        if (str_contains($normalized, 'hom kia')) {
            $date = $now->copy()->subDays(2);
            return $this->singleDay($date, 'hôm kia', 'day_before_yesterday');
        }
        if (str_contains($normalized, 'hom qua')) {
            $date = $now->copy()->subDay();
            return $this->singleDay($date, 'hôm qua', 'yesterday');
        }
        if (str_contains($normalized, 'hom nay')) {
            return $this->singleDay($now, 'hôm nay', 'today');
        }
        if (str_contains($normalized, 'ngay mai')) {
            $date = $now->copy()->addDay();
            return $this->singleDay($date, 'ngày mai', 'tomorrow');
        }
        if (str_contains($normalized, 'tuan truoc')) {
            $date = $now->copy()->subWeek();
            return [
                'from' => $date->copy()->startOfWeek()->startOfDay(),
                'to' => $date->copy()->endOfWeek()->endOfDay(),
                'label' => 'tuần trước',
                'key' => 'last_week',
            ];
        }
        if (str_contains($normalized, 'tuan nay')) {
            return [
                'from' => $now->copy()->startOfWeek()->startOfDay(),
                'to' => $now->copy()->endOfWeek()->endOfDay(),
                'label' => 'tuần này',
                'key' => 'this_week',
            ];
        }
        if (str_contains($normalized, 'thang truoc')) {
            $date = $now->copy()->subMonthNoOverflow();
            return [
                'from' => $date->copy()->startOfMonth()->startOfDay(),
                'to' => $date->copy()->endOfMonth()->endOfDay(),
                'label' => 'tháng trước',
                'key' => 'last_month',
            ];
        }
        if (str_contains($normalized, 'thang nay')) {
            return [
                'from' => $now->copy()->startOfMonth()->startOfDay(),
                'to' => $now->copy()->endOfMonth()->endOfDay(),
                'label' => 'tháng này',
                'key' => 'this_month',
            ];
        }
        if (str_contains($normalized, 'quy truoc')) {
            $date = $now->copy()->subQuarter();
            return [
                'from' => $date->copy()->firstOfQuarter()->startOfDay(),
                'to' => $date->copy()->lastOfQuarter()->endOfDay(),
                'label' => 'quý trước',
                'key' => 'last_quarter',
            ];
        }
        if (str_contains($normalized, 'quy nay')) {
            return [
                'from' => $now->copy()->firstOfQuarter()->startOfDay(),
                'to' => $now->copy()->lastOfQuarter()->endOfDay(),
                'label' => 'quý này',
                'key' => 'this_quarter',
            ];
        }
        if (str_contains($normalized, 'nam truoc')) {
            $date = $now->copy()->subYear();
            return [
                'from' => $date->copy()->startOfYear()->startOfDay(),
                'to' => $date->copy()->endOfYear()->endOfDay(),
                'label' => 'năm trước',
                'key' => 'last_year',
            ];
        }
        if (str_contains($normalized, 'nam nay')) {
            return [
                'from' => $now->copy()->startOfYear()->startOfDay(),
                'to' => $now->copy()->endOfYear()->endOfDay(),
                'label' => 'năm nay',
                'key' => 'this_year',
            ];
        }

        return null;
    }

    /** @return array{from:Carbon,to:Carbon,label:string,key:string} */
    private function defaultPeriod(string $default): array
    {
        $now = now();
        if ($default === 'today') {
            return $this->singleDay($now, 'hôm nay', 'today');
        }
        if ($default === 'week') {
            return [
                'from' => $now->copy()->startOfWeek()->startOfDay(),
                'to' => $now->copy()->endOfWeek()->endOfDay(),
                'label' => 'tuần này',
                'key' => 'this_week',
            ];
        }

        return [
            'from' => $now->copy()->startOfMonth()->startOfDay(),
            'to' => $now->copy()->endOfMonth()->endOfDay(),
            'label' => 'tháng này',
            'key' => 'this_month',
        ];
    }

    /** @return array{from:Carbon,to:Carbon,label:string,key:string} */
    private function singleDay(Carbon $date, string $label, string $key): array
    {
        return [
            'from' => $date->copy()->startOfDay(),
            'to' => $date->copy()->endOfDay(),
            'label' => $label.' ('.$date->format('d/m/Y').')',
            'key' => $key,
        ];
    }

    private function makeDate(int $day, int $month, string|int|null $year, Carbon $now): Carbon
    {
        $resolvedYear = $year === null || $year === '' ? (int) $now->year : (int) $year;
        if ($resolvedYear < 100) {
            $resolvedYear += 2000;
        }

        return Carbon::create($resolvedYear, $month, $day, 0, 0, 0, $now->getTimezone());
    }
}
