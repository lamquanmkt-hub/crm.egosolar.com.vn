<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalDailyReport;
use App\Models\Technical\TechnicalDailyReportFile;
use App\Models\Technical\TechnicalDailyReportHistory;
use App\Models\User;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Luồng báo cáo ngày: tạo — gửi — duyệt — trả lại — mở lại, kèm phân quyền,
 * lịch sử và bảo mật file minh chứng.
 */
final class TechnicalDailyReportTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    /** Dựng một kỹ thuật viên kèm một task đang mở được giao cho họ. */
    private function technicianWithTask(): array
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $taskId = $this->makeTask($siteId, (int) $tech->id);

        return [$tech, $siteId, $taskId];
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'report_date' => Carbon::today()->toDateString(),
            'content' => 'Đã lắp đặt 12 tấm pin dãy A và đấu nối DC.',
            'progress_percent' => 45,
            'work_hours' => 7.5,
            'materials_note' => '12 tấm pin, 20m cáp DC',
            'issues_note' => 'Thiếu 2 kẹp giữa',
            'next_plan' => 'Hoàn thiện dãy B',
        ], $overrides);
    }

    /* ---------------------------------------------------------------- Tạo */

    public function test_technician_can_create_a_draft_report_for_an_assigned_item(): void
    {
        [$tech, $siteId, $taskId] = $this->technicianWithTask();

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->payload([
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $taskId,
            ]))
            ->assertRedirect();

        $report = TechnicalDailyReport::query()->where('source_id', $taskId)->firstOrFail();

        $this->assertSame(TechnicalDailyReport::STATUS_DRAFT, $report->status);
        $this->assertSame((int) $tech->id, (int) $report->user_id);
        $this->assertSame($siteId, (int) $report->site_id);
        $this->assertSame(45, (int) $report->progress_percent);

        $this->assertDatabaseHas('technical_daily_report_histories', [
            'report_id' => $report->id,
            'action' => TechnicalDailyReportHistory::ACTION_CREATE,
        ]);
    }

    /** Một người báo cáo NHIỀU đầu việc trong cùng một ngày — không ghi đè. */
    public function test_multiple_reports_in_the_same_day_create_separate_records(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $taskA = $this->makeTask($siteId, (int) $tech->id);
        $taskB = $this->makeTask($siteId, (int) $tech->id);

        foreach ([$taskA, $taskB] as $taskId) {
            $this->actingAs($tech)
                ->post(route('technical.daily-reports.store'), $this->payload([
                    'source_type' => TechnicalWorkItem::SOURCE_TASK,
                    'source_id' => $taskId,
                ]))
                ->assertRedirect();
        }

        $this->assertSame(2, TechnicalDailyReport::query()->where('user_id', $tech->id)->count());
    }

    /** Không thể báo cáo cho đầu việc KHÔNG được giao cho mình. */
    public function test_cannot_report_on_a_work_item_assigned_to_someone_else(): void
    {
        $tech = $this->technician();
        $other = $this->technician();
        $siteId = $this->makeSite();
        $foreignTask = $this->makeTask($siteId, (int) $other->id);

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->payload([
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $foreignTask,
            ]))
            ->assertForbidden();

        $this->assertSame(0, TechnicalDailyReport::query()->where('source_id', $foreignTask)->count());
    }

    public function test_report_date_in_the_future_is_rejected(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->payload([
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $taskId,
                'report_date' => Carbon::tomorrow()->toDateString(),
            ]))
            ->assertSessionHasErrors('report_date');
    }

    public function test_progress_percent_above_one_hundred_is_rejected(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->payload([
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $taskId,
                'progress_percent' => 140,
            ]))
            ->assertSessionHasErrors('progress_percent');
    }

    /* -------------------------------------------------------------- Sửa */

    public function test_owner_can_edit_a_draft_report(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();
        $report = $this->storeReport($tech, $taskId);

        $this->actingAs($tech)
            ->put(route('technical.daily-reports.update', $report), $this->payload([
                'content' => 'Nội dung đã được cập nhật lại cho đầy đủ.',
                'progress_percent' => 70,
            ]))
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(70, (int) $report->progress_percent);
        $this->assertStringContainsString('cập nhật lại', $report->content);
    }

    /** Đã gửi thì chính chủ KHÔNG được tự sửa nữa. */
    public function test_submitted_report_cannot_be_edited_by_its_owner(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();
        $report = $this->storeReport($tech, $taskId, submit: true);

        $this->assertSame(TechnicalDailyReport::STATUS_SUBMITTED, $report->status);

        $this->actingAs($tech)
            ->get(route('technical.daily-reports.edit', $report))
            ->assertForbidden();

        $this->actingAs($tech)
            ->put(route('technical.daily-reports.update', $report), $this->payload(['content' => 'Cố tình sửa sau khi gửi.']))
            ->assertForbidden();

        $report->refresh();
        $this->assertStringNotContainsString('Cố tình sửa', $report->content);
    }

    /** Không xem / không sửa được báo cáo của người khác bằng cách đổi ID. */
    public function test_another_technician_cannot_view_or_edit_someone_elses_report(): void
    {
        [$owner, , $taskId] = $this->technicianWithTask();
        $report = $this->storeReport($owner, $taskId);
        $stranger = $this->technician();

        $this->actingAs($stranger)->get(route('technical.daily-reports.show', $report))->assertForbidden();
        $this->actingAs($stranger)->get(route('technical.daily-reports.edit', $report))->assertForbidden();
        $this->actingAs($stranger)
            ->put(route('technical.daily-reports.update', $report), $this->payload())
            ->assertForbidden();
    }

    /* ------------------------------------------------------------ Duyệt */

    public function test_manager_can_approve_a_submitted_report(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();
        $report = $this->storeReport($tech, $taskId, submit: true);
        $manager = $this->technicalManager();

        $this->actingAs($manager)
            ->post(route('technical.daily-reports.approve', $report), ['note' => 'Đạt yêu cầu.'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(TechnicalDailyReport::STATUS_APPROVED, $report->status);
        $this->assertSame((int) $manager->id, (int) $report->approved_by);
        $this->assertNotNull($report->approved_at);

        $this->assertDatabaseHas('technical_daily_report_histories', [
            'report_id' => $report->id,
            'action' => TechnicalDailyReportHistory::ACTION_APPROVE,
            'status_after' => TechnicalDailyReport::STATUS_APPROVED,
        ]);
    }

    public function test_technician_cannot_approve_reports(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();
        $report = $this->storeReport($tech, $taskId, submit: true);

        $this->actingAs($this->technician())
            ->post(route('technical.daily-reports.approve', $report))
            ->assertForbidden();

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.approve', $report))
            ->assertForbidden();

        $report->refresh();
        $this->assertSame(TechnicalDailyReport::STATUS_SUBMITTED, $report->status);
    }

    /** Yêu cầu sửa BẮT BUỘC có ý kiến. */
    public function test_request_revision_requires_a_note_and_is_logged(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();
        $report = $this->storeReport($tech, $taskId, submit: true);
        $manager = $this->technicalManager();

        $this->actingAs($manager)
            ->post(route('technical.daily-reports.request-revision', $report), ['note' => ''])
            ->assertSessionHasErrors('note');

        $report->refresh();
        $this->assertSame(TechnicalDailyReport::STATUS_SUBMITTED, $report->status);

        $this->actingAs($manager)
            ->post(route('technical.daily-reports.request-revision', $report), ['note' => 'Thiếu ảnh hiện trường.'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(TechnicalDailyReport::STATUS_REVISION, $report->status);
        $this->assertSame('Thiếu ảnh hiện trường.', $report->review_note);

        $this->assertDatabaseHas('technical_daily_report_histories', [
            'report_id' => $report->id,
            'action' => TechnicalDailyReportHistory::ACTION_REQUEST_REVISION,
            'note' => 'Thiếu ảnh hiện trường.',
        ]);

        // Sau khi bị trả lại, chính chủ sửa được trở lại.
        $this->actingAs($tech)->get(route('technical.daily-reports.edit', $report))->assertOk();
    }

    /* ------------------------------------------------------------ Mở lại */

    public function test_reopen_requires_a_reason_and_is_admin_only(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();
        $report = $this->storeReport($tech, $taskId, submit: true);
        $manager = $this->technicalManager();

        $this->actingAs($manager)
            ->post(route('technical.daily-reports.approve', $report))
            ->assertRedirect();

        // Trưởng kỹ thuật KHÔNG được mở lại báo cáo đã duyệt.
        $this->actingAs($manager)
            ->post(route('technical.daily-reports.reopen', $report), ['note' => 'Cần xem lại số liệu.'])
            ->assertForbidden();

        $admin = $this->admin();

        // Admin mở lại nhưng thiếu lý do => bị chặn.
        $this->actingAs($admin)
            ->post(route('technical.daily-reports.reopen', $report), ['note' => ''])
            ->assertSessionHasErrors('note');

        $report->refresh();
        $this->assertSame(TechnicalDailyReport::STATUS_APPROVED, $report->status);

        // Có lý do => mở lại được và ghi lịch sử.
        $this->actingAs($admin)
            ->post(route('technical.daily-reports.reopen', $report), ['note' => 'Sai số giờ thi công, cần khai lại.'])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(TechnicalDailyReport::STATUS_REVISION, $report->status);
        $this->assertNull($report->approved_by);

        $this->assertDatabaseHas('technical_daily_report_histories', [
            'report_id' => $report->id,
            'action' => TechnicalDailyReportHistory::ACTION_REOPEN,
            'note' => 'Sai số giờ thi công, cần khai lại.',
        ]);
    }

    /* ------------------------------------------------------------- Files */

    public function test_upload_rejects_disallowed_mime_and_oversized_files(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();

        // Sai định dạng
        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->payload([
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $taskId,
                'files' => [UploadedFile::fake()->create('malicious.php', 20, 'application/x-php')],
            ]))
            ->assertSessionHasErrors('files.0');

        // Quá dung lượng (giới hạn 10 MB)
        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->payload([
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $taskId,
                'files' => [UploadedFile::fake()->create('too-big.pdf', 15_000, 'application/pdf')],
            ]))
            ->assertSessionHasErrors('files.0');

        $this->assertSame(0, TechnicalDailyReport::query()->where('source_id', $taskId)->count());
    }

    public function test_valid_file_is_stored_on_the_private_disk_with_a_random_name(): void
    {
        Storage::fake('local');
        [$tech, , $taskId] = $this->technicianWithTask();

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->payload([
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $taskId,
                'files' => [UploadedFile::fake()->image('hien-truong.jpg')],
            ]))
            ->assertRedirect();

        $file = TechnicalDailyReportFile::query()->latest('id')->firstOrFail();

        $this->assertSame('local', $file->disk);
        $this->assertSame('hien-truong.jpg', $file->original_name);
        $this->assertStringNotContainsString('hien-truong', $file->path, 'Tên file lưu phải ngẫu nhiên.');
        $this->assertStringStartsWith('technical/daily-reports/', $file->path);
        Storage::disk('local')->assertExists($file->path);
    }

    /** Người không có quyền KHÔNG tải được file minh chứng. */
    public function test_evidence_file_cannot_be_downloaded_without_permission(): void
    {
        Storage::fake('local');
        [$owner, , $taskId] = $this->technicianWithTask();

        $this->actingAs($owner)
            ->post(route('technical.daily-reports.store'), $this->payload([
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $taskId,
                'files' => [UploadedFile::fake()->image('bang-chung.png')],
            ]))
            ->assertRedirect();

        $report = TechnicalDailyReport::query()->where('source_id', $taskId)->firstOrFail();
        $file = TechnicalDailyReportFile::query()->where('report_id', $report->id)->firstOrFail();
        $url = route('technical.daily-reports.files.download', [$report, $file]);

        // Khách vãng lai (bỏ phiên đăng nhập mà actingAs ở trên đã đặt)
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirect();

        // Kỹ thuật viên khác
        $this->actingAs($this->technician())->get($url)->assertForbidden();

        // Người ngoài phòng Kỹ thuật
        $this->actingAs($this->outsider())->get($url)->assertForbidden();

        // Chính chủ và người quản lý thì tải được
        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs($this->technicalManager())->get($url)->assertOk();
    }

    /** Form vẫn hoạt động khi trình duyệt tắt JS (chỉ gửi work_item_key). */
    public function test_work_item_key_fallback_works_without_javascript(): void
    {
        [$tech, , $taskId] = $this->technicianWithTask();

        $payload = $this->payload();
        $payload['work_item_key'] = TechnicalWorkItem::SOURCE_TASK.'|'.$taskId;

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('technical_daily_reports', [
            'user_id' => $tech->id,
            'source_type' => TechnicalWorkItem::SOURCE_TASK,
            'source_id' => $taskId,
        ]);
    }

    /** Khoá gộp giả mạo cũng không vượt được kiểm tra phân công phía server. */
    public function test_work_item_key_fallback_still_enforces_assignment(): void
    {
        $tech = $this->technician();
        $other = $this->technician();
        $siteId = $this->makeSite();
        $foreignTask = $this->makeTask($siteId, (int) $other->id);

        $payload = $this->payload();
        $payload['work_item_key'] = TechnicalWorkItem::SOURCE_TASK.'|'.$foreignTask;

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $payload)
            ->assertForbidden();
    }

    /** Báo cáo của công ty khác không xem được (company scope). */
    public function test_report_from_another_company_is_not_visible(): void
    {
        [$owner, , $taskId] = $this->technicianWithTask();
        $report = $this->storeReport($owner, $taskId);

        \Illuminate\Support\Facades\DB::table('technical_daily_reports')
            ->where('id', $report->id)
            ->update(['company_id' => $this->companyId() + 9_999]);

        $this->actingAs($owner)
            ->get(route('technical.daily-reports.show', $report))
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->get(route('technical.daily-reports.show', $report))
            ->assertNotFound();
    }

    /** Danh sách báo cáo: kỹ thuật viên chỉ thấy báo cáo của mình. */
    public function test_report_index_is_scoped_for_technicians_and_open_for_managers(): void
    {
        [$owner, , $taskId] = $this->technicianWithTask();
        $this->storeReport($owner, $taskId);

        $stranger = $this->technician();
        $strangerSite = $this->makeSite();
        $strangerTask = $this->makeTask($strangerSite, (int) $stranger->id);
        $this->storeReport($stranger, $strangerTask);

        $ownerView = $this->actingAs($owner)->get(route('technical.daily-reports.index'))->assertOk();
        $this->assertSame(1, $ownerView->viewData('reports')->total());

        $managerView = $this->actingAs($this->technicalManager())
            ->get(route('technical.daily-reports.index'))
            ->assertOk();
        $this->assertGreaterThanOrEqual(2, $managerView->viewData('reports')->total());
    }

    /* ------------------------------------------------------------- Helper */

    private function storeReport(User $owner, int $taskId, bool $submit = false): TechnicalDailyReport
    {
        $payload = $this->payload([
            'source_type' => TechnicalWorkItem::SOURCE_TASK,
            'source_id' => $taskId,
        ]);

        if ($submit) {
            $payload['submit'] = 1;
        }

        $this->actingAs($owner)
            ->post(route('technical.daily-reports.store'), $payload)
            ->assertRedirect();

        return TechnicalDailyReport::query()
            ->where('user_id', $owner->id)
            ->where('source_id', $taskId)
            ->latest('id')
            ->firstOrFail();
    }
}
