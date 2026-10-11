<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi\Sources;

use App\Services\Hr\Kpi\HcnsSourceReviews;
use App\Support\EgoCompanyLock;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HC07 — Tiếp nhận, phân loại, luân chuyển công văn/giấy tờ chính xác (nguồn: Giao nhận hồ sơ, kpi_tracked = 1).
 *
 * Đo ĐỘ CHÍNH XÁC qua kiểm tra độc lập (phân loại, nơi nhận, đủ giấy tờ) — không đo đúng hạn (thuộc HC09).
 * Trạng thái "đã nhận/hoàn tất" của hồ sơ hay việc có tệp không tự thành đạt.
 *   kpi_state: earned (kiểm tra đạt) | lost (kiểm tra không đạt) | pending (chờ kiểm / sửa sau khi gửi)
 *              | missing (chưa gửi kiểm, thiếu người xử lý/nơi nhận) .
 * Mẫu số = earned + lost; kỳ theo tháng gửi kiểm tra lần đầu (quy tắc nháp).
 */
final class DocumentAccuracySource
{
    public const CODE = 'documents';

    public const SOURCE_TYPE = 'hr_document_handover';

    private const COLUMNS = ['company_id', 'kpi_tracked', 'processor_id', 'reviewer_id', 'classification', 'routed_to_user_id', 'routed_to_note'];

    public static function ready(): bool
    {
        if (! SchemaCache::hasTable('hr_document_handovers') || ! HcnsSourceReviews::ready()) {
            return false;
        }
        foreach (self::COLUMNS as $column) {
            if (! SchemaCache::hasColumn('hr_document_handovers', $column)) {
                return false;
            }
        }

        return SchemaCache::hasColumn('hr_document_handover_files', 'disk');
    }

    /** Nội dung người kiểm xác nhận: phân loại, nơi nhận, người xử lý và danh sách tệp. */
    public function declared(object $handover): array
    {
        return [
            'title' => (string) $handover->title,
            'document_type' => (string) ($handover->document_type ?? ''),
            'classification' => (string) ($handover->classification ?? ''),
            'processor_id' => (int) ($handover->processor_id ?? 0),
            'routed_to_user_id' => (int) ($handover->routed_to_user_id ?? 0),
            'routed_to_note' => (string) ($handover->routed_to_note ?? ''),
            'files' => DB::table('hr_document_handover_files')->where('handover_id', $handover->id)->orderBy('id')
                ->get(['id', 'original_name', 'size'])->map(fn ($f) => $f->id.':'.$f->original_name.':'.$f->size)->all(),
        ];
    }

    public function fingerprint(object $handover): string
    {
        return hash('sha256', json_encode($this->declared($handover), JSON_UNESCAPED_UNICODE));
    }

    /** Thiếu dữ kiện bắt buộc trước khi gửi kiểm. @return array<int, string> */
    public function missingFields(object $handover): array
    {
        $missing = [];
        if (empty($handover->processor_id)) {
            $missing[] = 'người xử lý HCNS';
        }
        if (empty($handover->classification)) {
            $missing[] = 'phân loại';
        }
        if (empty($handover->routed_to_user_id) && trim((string) ($handover->routed_to_note ?? '')) === '') {
            $missing[] = 'nơi nhận (người nhận hoặc ghi rõ đơn vị nhận)';
        }
        if (empty($handover->reviewer_id)) {
            $missing[] = 'người kiểm tra';
        }

        return $missing;
    }

    /** @return array<int, array> */
    public function rows(int $userId, string $month, array $rules, ?Carbon $now = null): array
    {
        if (! self::ready()) {
            return [];
        }
        $now ??= now();
        [$start, $end] = $this->bounds($month);

        $items = DB::table('hr_document_handovers as h')
            ->leftJoin('hr_kpi_source_reviews as r', function ($join): void {
                $join->on('r.source_id', '=', 'h.id')->where('r.source_type', self::SOURCE_TYPE)->where('r.criterion_code', self::CODE);
            })
            ->where('h.kpi_tracked', true)
            ->where('h.processor_id', $userId)
            ->where(fn ($q) => $q->where('h.company_id', EgoCompanyLock::id())->orWhereNull('h.company_id'))
            ->where(function ($q) use ($start, $end): void {
                // Đã gửi kiểm: kỳ theo lần gửi đầu. Chưa gửi: hiện ở kỳ tạo hồ sơ để không biến mất khỏi checklist.
                $q->whereBetween('r.first_submitted_at', [$start, $end])
                    ->orWhere(fn ($q2) => $q2->whereNull('r.first_submitted_at')->whereBetween('h.created_at', [$start, $end]));
            })
            ->orderBy('h.id')
            ->get(['h.*', 'r.id as review_id', 'r.status as review_status', 'r.result', 'r.checks', 'r.first_submitted_at',
                'r.submitted_at', 'r.submission_count', 'r.confirmed_at', 'r.confirmed_by', 'r.returned_at', 'r.return_reason', 'r.reviewer_id as review_reviewer_id']);

        $names = $this->names($items);

        return $items->map(fn ($h) => $this->evaluate($h, $names))->all();
    }

    public function evaluate(object $h, array $names = []): array
    {
        $fmt = fn ($t) => $t ? Carbon::parse($t)->format('H:i d/m/Y') : '—';
        $checks = json_decode((string) ($h->checks ?? ''), true) ?: [];
        $labels = (array) config('hcns_kpi.document_checks', []);
        $status = $h->review_status ?? null;

        [$state, $reason] = match (true) {
            $status === 'confirmed' && $h->result === 'pass' => ['earned', 'Kiểm tra đạt cả '.count($checks).' mục.'],
            $status === 'confirmed' => ['lost', 'Không đạt: '.collect($checks)->filter(fn ($v) => $v !== 'pass')->keys()->map(fn ($k) => $labels[$k] ?? $k)->implode(', ').'.'],
            $status === 'submitted' => ['pending', 'Đã gửi '.$fmt($h->submitted_at).' — chờ người kiểm.'],
            $status === 'changed' => ['pending', 'Nội dung/tệp đổi sau khi gửi — người xử lý cần gửi lại để kiểm tra lại.'],
            $status === 'returned' => ['pending', 'Trả bổ sung: '.($h->return_reason ?: '—')],
            default => ['missing', $this->missingFields($h) !== []
                ? 'Chưa gửi kiểm — thiếu '.implode(', ', $this->missingFields($h)).'.'
                : 'Chưa gửi kiểm tra.'],
        };

        $classes = (array) config('hcns_kpi.document_classes', []);

        return [
            'code' => self::CODE,
            'source_id' => (int) $h->id,
            'title' => trim(($h->code ? $h->code.' · ' : '').$h->title),
            'url' => route('hr.document-handovers.show', (int) $h->id),
            'owner_id' => (int) $h->processor_id,
            'owner_name' => $names[(int) $h->processor_id] ?? '—',
            'reviewer_name' => ($rid = (int) ($h->reviewer_id ?? 0)) ? ($names[$rid] ?? '—') : null,
            'classification' => $classes[(string) $h->classification] ?? ($h->classification ?: 'Chưa phân loại'),
            'routed_to' => ($h->routed_to_user_id ? ($names[(int) $h->routed_to_user_id] ?? '—') : '').($h->routed_to_note ? ' '.$h->routed_to_note : ''),
            'first_submitted_at' => $h->first_submitted_at ? Carbon::parse($h->first_submitted_at) : null,
            'submission_count' => (int) ($h->submission_count ?? 0),
            'confirmed_by_name' => $h->confirmed_by ? ($names[(int) $h->confirmed_by] ?? '—') : null,
            'confirmed_at' => $h->confirmed_at ? Carbon::parse($h->confirmed_at) : null,
            'checks' => $checks,
            'work_status' => (string) $h->status,
            'work_label' => [
                'created' => 'Tạo hồ sơ', 'assigned' => 'Đã giao', 'received' => 'Đã nhận', 'sent' => 'Đã gửi',
                'returned' => 'Đã trả', 'completed' => 'Hoàn tất', 'archived' => 'Lưu trữ',
            ][(string) $h->status] ?? (string) $h->status,
            'review_status' => $status ?? 'none',
            'review_label' => HcnsSourceReviews::STATUSES[$status] ?? 'Chưa gửi kiểm tra',
            'kpi_state' => $state,
            'reason' => $reason,
        ];
    }

    public function summarize(array $rows, string $month, ?Carbon $now = null): array
    {
        $now ??= now();
        $counts = array_fill_keys(['earned', 'lost', 'pending', 'waiting', 'excluded', 'missing'], 0);
        foreach ($rows as $row) {
            $counts[$row['kpi_state']]++;
        }
        $denominator = $counts['earned'] + $counts['lost'];
        [, $end] = $this->bounds($month);

        return [
            'numerator' => $counts['earned'],
            'denominator' => $denominator,
            'value' => $denominator > 0 ? round($counts['earned'] / $denominator * 100, 2) : null,
            'counts' => $counts,
            'data_status' => match (true) {
                $rows === [] => 'no_items',
                $counts['pending'] + $counts['missing'] > 0 => 'incomplete',
                $now->lessThanOrEqualTo($end) => 'open',
                default => 'complete',
            },
        ];
    }

    private function names($items): array
    {
        $ids = collect($items)->flatMap(fn ($h) => [$h->processor_id, $h->reviewer_id, $h->routed_to_user_id, $h->confirmed_by ?? null])->filter()->unique()->all();

        return $ids === [] ? [] : DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function bounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00', config('app.timezone'));

        return [$start, $start->copy()->endOfMonth()];
    }
}
