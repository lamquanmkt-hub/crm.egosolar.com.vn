<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Services\Hr\Kpi\HcnsKpiRules;
use App\Services\Hr\Kpi\Sources\DocumentAccuracySource;
use App\Support\EgoCompanyLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * KPI HCNS — HC07 Phân loại/luân chuyển công văn chính xác (đợt hcns-kpi P3).
 * Luồng thật qua trang Giao nhận hồ sơ; dữ liệu giả trong transaction trên egosolar_test, tệp trên disk giả.
 */
final class HcnsDocumentAccuracyKpiTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-10';

    private User $processor;

    private User $reviewer;

    private User $director;

    private User $receiver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(DocumentAccuracySource::ready(), 'DB test chưa áp migration nguồn HCNS.');
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-10-06 09:00:00'));
        $this->processor = $this->person('hr', 'Văn thư HCNS');
        $this->reviewer = $this->person('hr', 'Người kiểm HCNS');
        $this->director = $this->person('management', 'Giám đốc');
        $this->receiver = $this->person('accounting', 'Kế toán nhận');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, string $name): User
    {
        return $this->userWithRole($role, ['name' => 'HCNS-KPI-TEST '.$name.' '.Str::upper(Str::random(4)), 'email' => 'hcnsdoc-'.Str::lower(Str::random(10)).'@example.test']);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'HCNS-KPI-TEST Công văn Sở KH&ĐT', 'document_type' => 'Công văn', 'priority' => 'normal',
            'kpi_tracked' => '1', 'processor_id' => $this->processor->id, 'reviewer_id' => $this->reviewer->id,
            'classification' => 'incoming_external', 'routed_to_user_id' => $this->receiver->id, 'routed_to_note' => 'Bản gốc',
        ];
    }

    private function create(array $overrides = []): int
    {
        $this->actingAs($this->processor)->post(route('hr.document-handovers.store'), $this->form($overrides))->assertRedirect();

        return (int) DB::table('hr_document_handovers')->orderByDesc('id')->value('id');
    }

    private function review(int $id): object
    {
        return DB::table('hr_kpi_source_reviews')->where('source_type', DocumentAccuracySource::SOURCE_TYPE)->where('source_id', $id)->first();
    }

    private function passAll(): array
    {
        return ['checks' => ['classification' => 'pass', 'routing' => 'pass', 'completeness' => 'pass']];
    }

    private function rows(User $user, ?Carbon $now = null): array
    {
        return app(DocumentAccuracySource::class)->rows((int) $user->id, self::MONTH, app(HcnsKpiRules::class)->defaults(), $now ?? now());
    }

    public function test_tracked_document_needs_processor_and_independent_reviewer_and_stores_files_privately(): void
    {
        $this->actingAs($this->processor)->post(route('hr.document-handovers.store'), $this->form(['reviewer_id' => $this->processor->id]))
            ->assertSessionHasErrors('reviewer_id');

        $id = $this->create(['attachments' => [UploadedFile::fake()->create('cong-van.pdf', 12, 'application/pdf')]]);
        $item = DB::table('hr_document_handovers')->find($id);
        $this->assertSame(EgoCompanyLock::id(), (int) $item->company_id);
        $this->assertSame(1, (int) $item->kpi_tracked);

        $file = DB::table('hr_document_handover_files')->where('handover_id', $id)->first();
        $this->assertSame('local', $file->disk);
        Storage::disk('local')->assertExists($file->path);
        Storage::disk('public')->assertMissing($file->path);
        $this->actingAs($this->reviewer)->get(route('hr.document-handovers.files.download', [$id, $file->id]))->assertOk();
        $this->actingAs($this->reviewer)->get(route('hr.document-handovers.files.preview', [$id, $file->id]))->assertOk();
    }

    public function test_draft_submit_confirm_flow_and_who_may_act(): void
    {
        $id = $this->create(['classification' => '']);

        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.draft', $id), ['note' => 'đang phân loại'])->assertRedirect();
        $this->assertSame('draft', $this->review($id)->status);
        $this->assertNull($this->review($id)->first_submitted_at);

        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id))->assertSessionHas('error');
        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.submit', $id))->assertForbidden();

        DB::table('hr_document_handovers')->where('id', $id)->update(['classification' => 'incoming_external']);
        Carbon::setTestNow('2026-10-07 10:00:00');
        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id))->assertRedirect();
        $this->assertSame('submitted', $this->review($id)->status);
        $this->assertSame('2026-10-07 10:00:00', (string) $this->review($id)->first_submitted_at);

        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.confirm', $id), $this->passAll())->assertForbidden();
        $this->actingAs($this->receiver)->post(route('hr.document-handovers.kpi.confirm', $id), $this->passAll())->assertForbidden();
        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.confirm', $id), ['checks' => ['classification' => 'pass']])->assertSessionHasErrors('checks');

        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.confirm', $id), $this->passAll())->assertRedirect();
        $review = $this->review($id);
        $this->assertSame(['confirmed', 'pass', (int) $this->reviewer->id], [$review->status, $review->result, (int) $review->confirmed_by]);
        $this->assertSame('earned', $this->rows($this->processor)[0]['kpi_state']);
    }

    public function test_failed_check_needs_note_and_counts_as_lost(): void
    {
        $id = $this->create();
        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id));
        $fail = ['checks' => ['classification' => 'pass', 'routing' => 'fail', 'completeness' => 'pass']];

        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.confirm', $id), $fail)->assertSessionHasErrors('note');
        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.confirm', $id), $fail + ['note' => 'Gửi nhầm phòng'])->assertRedirect();

        $row = $this->rows($this->processor)[0];
        $this->assertSame('lost', $row['kpi_state']);
        $this->assertStringContainsString('Luân chuyển đúng nơi nhận', $row['reason']);
    }

    public function test_change_after_confirmation_needs_resubmit_even_if_reverted(): void
    {
        $id = $this->create();
        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id));
        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.confirm', $id), $this->passAll());

        $this->actingAs($this->processor)->put(route('hr.document-handovers.update', $id), $this->form(['classification' => 'internal']))->assertRedirect();
        $this->assertSame('changed', $this->review($id)->status);
        $this->actingAs($this->processor)->put(route('hr.document-handovers.update', $id), $this->form())->assertRedirect();
        $this->assertSame('changed', $this->review($id)->status, 'Đổi về như cũ không làm xác nhận sống lại.');
        $this->assertSame('pending', $this->rows($this->processor)[0]['kpi_state']);
        $this->assertTrue(DB::table('hr_kpi_source_review_events')->where('review_id', $this->review($id)->id)->where('action', 'invalidated')->exists());

        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id))->assertRedirect();
        $this->assertSame(2, (int) $this->review($id)->submission_count);
    }

    public function test_file_change_after_submit_invalidates_and_reviewer_cannot_confirm_old_version(): void
    {
        $id = $this->create(['attachments' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]]);
        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id));
        $this->actingAs($this->processor)->post(route('hr.document-handovers.files.upload', $id), ['attachments' => [UploadedFile::fake()->create('b.pdf', 5, 'application/pdf')]])->assertRedirect();

        $this->assertSame('changed', $this->review($id)->status);
        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.confirm', $id), $this->passAll())->assertSessionHasErrors('kpi');

        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id));
        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.confirm', $id), $this->passAll());
        $file = DB::table('hr_document_handover_files')->where('handover_id', $id)->orderBy('id')->first();
        $this->actingAs($this->processor)->delete(route('hr.document-handovers.files.delete', [$id, $file->id]))->assertRedirect();
        $this->assertSame('changed', $this->review($id)->status, 'Xóa tệp sau xác nhận cũng phải kiểm lại.');
    }

    public function test_return_resubmit_and_delete_or_untrack_blocked_after_submission(): void
    {
        $id = $this->create();
        Carbon::setTestNow('2026-10-07 10:00:00');
        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id));
        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.return', $id), ['reason' => 'Thiếu số công văn'])->assertRedirect();
        $this->assertSame(['returned', 'Thiếu số công văn'], [$this->review($id)->status, $this->review($id)->return_reason]);

        Carbon::setTestNow('2026-10-08 10:00:00');
        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id))->assertRedirect();
        $this->assertSame('2026-10-07 10:00:00', (string) $this->review($id)->first_submitted_at, 'Giữ lần gửi đầu.');
        $this->assertSame(2, (int) $this->review($id)->submission_count);

        $this->actingAs($this->director)->delete(route('hr.document-handovers.destroy', $id))->assertSessionHas('error');
        $this->assertNotNull(DB::table('hr_document_handovers')->find($id));
        $this->actingAs($this->processor)->put(route('hr.document-handovers.update', $id), $this->form(['kpi_tracked' => '0']))->assertSessionHasErrors('kpi_tracked');
    }

    public function test_measure_scope_period_and_summary(): void
    {
        $pass = $this->create();
        $fail = $this->create();
        $waiting = $this->create();
        $untracked = $this->create(['kpi_tracked' => '0']);
        $otherCompany = $this->create();
        DB::table('hr_document_handovers')->where('id', $otherCompany)->update(['company_id' => EgoCompanyLock::id() + 1000]);
        foreach ([$pass, $fail, $waiting] as $id) {
            $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $id));
        }
        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.confirm', $pass), $this->passAll());
        $this->actingAs($this->reviewer)->post(route('hr.document-handovers.kpi.confirm', $fail), ['checks' => ['classification' => 'fail', 'routing' => 'pass', 'completeness' => 'pass'], 'note' => 'Sai loại']);

        Carbon::setTestNow('2026-11-03 09:00:00');
        $nextMonth = $this->create();
        $this->actingAs($this->processor)->post(route('hr.document-handovers.kpi.submit', $nextMonth));

        $rows = $this->rows($this->processor, Carbon::parse('2026-11-03 09:00:00'));
        $ids = array_column($rows, 'source_id');
        $this->assertEqualsCanonicalizing([$pass, $fail, $waiting], $ids);
        $this->assertNotContains($untracked, $ids);
        $this->assertNotContains($otherCompany, $ids);
        $this->assertSame([], $this->rows($this->reviewer), 'Chỉ tính cho người xử lý bằng ID.');

        $summary = app(DocumentAccuracySource::class)->summarize($rows, self::MONTH, Carbon::parse('2026-11-03 09:00:00'));
        $this->assertSame([1, 2, 50.0, 'incomplete'], [$summary['numerator'], $summary['denominator'], $summary['value'], $summary['data_status']]);
    }
}
