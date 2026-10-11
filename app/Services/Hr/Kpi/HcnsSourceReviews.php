<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Support\EgoCompanyLock;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kiểm tra độc lập một hồ sơ nguồn KPI HCNS (bảng hr_kpi_source_reviews + lịch sử events).
 *
 * Trạng thái: draft → submitted → confirmed | returned; "changed" khi nội dung/tệp đổi sau khi gửi hoặc
 * sau khi xác nhận. Xác nhận gắn với fingerprint nội dung lúc gửi; đổi rồi đổi lại vẫn phải gửi và xác
 * nhận lại (trạng thái chuyển hẳn sang "changed", không suy lại từ so khớp fingerprint).
 */
final class HcnsSourceReviews
{
    public const STATUSES = [
        'draft' => 'Đã lưu nháp',
        'submitted' => 'Chờ kiểm tra',
        'returned' => 'Trả bổ sung',
        'changed' => 'Đã sửa sau khi gửi — cần gửi lại',
        'confirmed' => 'Đã kiểm tra',
    ];

    public static function ready(): bool
    {
        return SchemaCache::hasTable('hr_kpi_source_reviews') && SchemaCache::hasTable('hr_kpi_source_review_events');
    }

    public function find(string $sourceType, int $sourceId, string $criterion): ?object
    {
        if (! self::ready()) {
            return null;
        }

        return DB::table('hr_kpi_source_reviews')
            ->where('source_type', $sourceType)->where('source_id', $sourceId)->where('criterion_code', $criterion)
            ->first();
    }

    public function events(int $reviewId): \Illuminate\Support\Collection
    {
        return DB::table('hr_kpi_source_review_events as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.actor_id')
            ->where('e.review_id', $reviewId)
            ->orderByDesc('e.id')
            ->get(['e.*', 'u.name as actor_name']);
    }

    /** Lưu nháp phần khai báo của người xử lý. Không có mốc gửi, chưa tính KPI. */
    public function saveDraft(string $sourceType, int $sourceId, string $criterion, int $ownerId, ?int $reviewerId, array $declared, string $fingerprint, int $actorId, ?string $note): object
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $criterion, $ownerId, $reviewerId, $declared, $fingerprint, $actorId, $note) {
            $review = $this->lockOrCreate($sourceType, $sourceId, $criterion, $ownerId, $reviewerId);
            if (in_array($review->status, ['submitted', 'confirmed'], true)) {
                throw ValidationException::withMessages(['kpi' => 'Hồ sơ đã gửi kiểm tra — không lưu nháp đè lên bản đã gửi.']);
            }
            $this->update($review, [
                'owner_id' => $ownerId, 'reviewer_id' => $reviewerId, 'declared' => json_encode($declared, JSON_UNESCAPED_UNICODE),
                'fingerprint' => $fingerprint, 'note' => $note, 'draft_saved_at' => now(),
                'status' => $review->status === 'returned' || $review->status === 'changed' ? $review->status : 'draft',
            ]);
            $this->event($review, 'draft_saved', $actorId, $review->status, $review->status === 'returned' || $review->status === 'changed' ? $review->status : 'draft', $note);

            return $this->reload($review);
        });
    }

    /** Gửi kiểm tra: lưu lần gửi đầu (không ghi đè), lần gửi gần nhất và số lần gửi; tăng revision. */
    public function submit(string $sourceType, int $sourceId, string $criterion, int $ownerId, ?int $reviewerId, array $declared, string $fingerprint, int $actorId, ?string $note): object
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $criterion, $ownerId, $reviewerId, $declared, $fingerprint, $actorId, $note) {
            $review = $this->lockOrCreate($sourceType, $sourceId, $criterion, $ownerId, $reviewerId);
            if (in_array($review->status, ['submitted', 'confirmed'], true)) {
                throw ValidationException::withMessages(['kpi' => 'Hồ sơ đang chờ kiểm tra hoặc đã được kiểm tra.']);
            }
            $from = $review->status;
            $this->update($review, [
                'owner_id' => $ownerId, 'reviewer_id' => $reviewerId, 'declared' => json_encode($declared, JSON_UNESCAPED_UNICODE),
                'fingerprint' => $fingerprint, 'note' => $note, 'status' => 'submitted',
                'revision' => (int) $review->revision + 1, 'submitted_at' => now(), 'submitted_by' => $actorId,
                'first_submitted_at' => $review->first_submitted_at ?? now(),
                'submission_count' => (int) $review->submission_count + 1,
                'checks' => null, 'result' => null, 'confirmed_at' => null, 'confirmed_by' => null,
            ]);
            $this->event($review, $review->first_submitted_at ? 'resubmitted' : 'submitted', $actorId, $from, 'submitted', $note, ['revision' => (int) $review->revision + 1]);

            return $this->reload($review);
        });
    }

    /**
     * Người kiểm xác nhận kết quả từng mục (pass/fail). Phải đúng bản đang chờ (fingerprint) và khác người xử lý.
     *
     * @param  array<string, string>  $checks
     */
    public function confirm(object $review, array $checks, string $currentFingerprint, int $actorId, ?string $note): object
    {
        return DB::transaction(function () use ($review, $checks, $currentFingerprint, $actorId, $note) {
            $row = DB::table('hr_kpi_source_reviews')->where('id', $review->id)->lockForUpdate()->first();
            if ($row->status !== 'submitted') {
                throw ValidationException::withMessages(['kpi' => 'Chỉ xác nhận được hồ sơ đang chờ kiểm tra.']);
            }
            if ((int) $row->owner_id === $actorId) {
                throw ValidationException::withMessages(['kpi' => 'Người xử lý không tự xác nhận kết quả của mình.']);
            }
            if (! hash_equals((string) $row->fingerprint, $currentFingerprint)) {
                throw ValidationException::withMessages(['kpi' => 'Nội dung hoặc tệp đã đổi sau khi gửi — người xử lý cần gửi lại.']);
            }
            $result = collect($checks)->every(fn ($v) => $v === 'pass') ? 'pass' : 'fail';
            $this->update($row, [
                'status' => 'confirmed', 'checks' => json_encode($checks), 'result' => $result,
                'confirmed_at' => now(), 'confirmed_by' => $actorId,
            ]);
            $this->event($row, 'confirmed', $actorId, 'submitted', 'confirmed', $note, ['checks' => $checks, 'result' => $result, 'revision' => (int) $row->revision]);

            return $this->reload($row);
        });
    }

    public function returnForFix(object $review, string $reason, int $actorId): object
    {
        return DB::transaction(function () use ($review, $reason, $actorId) {
            $row = DB::table('hr_kpi_source_reviews')->where('id', $review->id)->lockForUpdate()->first();
            if (! in_array($row->status, ['submitted', 'confirmed'], true)) {
                throw ValidationException::withMessages(['kpi' => 'Chỉ trả bổ sung hồ sơ đã gửi hoặc đã kiểm tra.']);
            }
            if ((int) $row->owner_id === $actorId) {
                throw ValidationException::withMessages(['kpi' => 'Người xử lý không tự trả bổ sung hồ sơ của mình.']);
            }
            $this->update($row, [
                'status' => 'returned', 'returned_at' => now(), 'returned_by' => $actorId, 'return_reason' => $reason,
                'checks' => null, 'result' => null, 'confirmed_at' => null, 'confirmed_by' => null,
            ]);
            $this->event($row, 'returned', $actorId, $row->status, 'returned', $reason);

            return $this->reload($row);
        });
    }

    /**
     * Gọi sau mọi thay đổi hồ sơ nguồn. Nếu fingerprint khác bản đã gửi/xác nhận thì chuyển hẳn sang "changed"
     * (xác nhận cũ chuyển vào lịch sử). Đổi lại giá trị cũ sau đó không làm xác nhận sống lại.
     */
    public function invalidateIfChanged(string $sourceType, int $sourceId, string $criterion, string $fingerprint, int $actorId, string $what): ?object
    {
        $review = $this->find($sourceType, $sourceId, $criterion);
        if (! $review || ! in_array($review->status, ['submitted', 'confirmed'], true) || hash_equals((string) $review->fingerprint, $fingerprint)) {
            return $review;
        }

        DB::table('hr_kpi_source_reviews')->where('id', $review->id)->update([
            'status' => 'changed', 'checks' => null, 'result' => null, 'confirmed_at' => null, 'confirmed_by' => null, 'updated_at' => now(),
        ]);
        $this->event($review, 'invalidated', $actorId, $review->status, 'changed', $what, [
            'previous_result' => $review->result, 'previous_confirmed_by' => $review->confirmed_by,
            'previous_confirmed_at' => $review->confirmed_at, 'revision' => (int) $review->revision,
        ]);

        return $this->find($sourceType, $sourceId, $criterion);
    }

    private function lockOrCreate(string $sourceType, int $sourceId, string $criterion, int $ownerId, ?int $reviewerId): object
    {
        $row = DB::table('hr_kpi_source_reviews')
            ->where('source_type', $sourceType)->where('source_id', $sourceId)->where('criterion_code', $criterion)
            ->lockForUpdate()->first();
        if ($row) {
            return $row;
        }
        $id = DB::table('hr_kpi_source_reviews')->insertGetId([
            'company_id' => EgoCompanyLock::id(), 'criterion_code' => $criterion, 'source_type' => $sourceType, 'source_id' => $sourceId,
            'owner_id' => $ownerId, 'reviewer_id' => $reviewerId, 'status' => 'draft', 'revision' => 0, 'submission_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('hr_kpi_source_reviews')->where('id', $id)->lockForUpdate()->first();
    }

    private function update(object $review, array $values): void
    {
        DB::table('hr_kpi_source_reviews')->where('id', $review->id)->update($values + ['updated_at' => now()]);
    }

    private function reload(object $review): object
    {
        return DB::table('hr_kpi_source_reviews')->where('id', $review->id)->first();
    }

    private function event(object $review, string $action, int $actorId, ?string $from, ?string $to, ?string $note, array $data = []): void
    {
        DB::table('hr_kpi_source_review_events')->insert([
            'review_id' => $review->id, 'action' => $action, 'actor_id' => $actorId, 'revision' => (int) ($data['revision'] ?? $review->revision),
            'from_status' => $from, 'to_status' => $to, 'note' => $note,
            'data' => $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE), 'created_at' => now(),
        ]);
    }
}
