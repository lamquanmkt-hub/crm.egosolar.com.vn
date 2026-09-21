<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Services\Technical\TechnicalWorkFeedService;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Lớp tổng hợp công việc kỹ thuật (`TechnicalWorkFeedService`).
 *
 * Đây là phần sửa nguyên nhân gốc của "dashboard Kỹ thuật toàn số 0":
 * trang cũ đọc `technical_work_records` (không ai ghi), trang mới đọc thẳng
 * ba nguồn công việc thật.
 */
final class TechnicalWorkFeedTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private function feed(): TechnicalWorkFeedService
    {
        return app(TechnicalWorkFeedService::class);
    }

    /** Cả 3 nguồn đều được chuẩn hoá về cùng một hình dạng. */
    public function test_feed_normalises_items_from_all_three_sources(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();

        $this->makeWorkflowAssignment($siteId, (int) $tech->id);
        $this->makeTask($siteId, (int) $tech->id);
        $this->makeMaintenance($siteId, (int) $tech->id);

        $items = $this->feed()->paginate(['user_id' => (int) $tech->id], 50)->getCollection();

        $bySource = $items->groupBy(fn (TechnicalWorkItem $item): string => $item->sourceType);

        $this->assertCount(1, $bySource->get(TechnicalWorkItem::SOURCE_PROJECT_WORKFLOW, collect()));
        $this->assertCount(1, $bySource->get(TechnicalWorkItem::SOURCE_TASK, collect()));
        $this->assertCount(1, $bySource->get(TechnicalWorkItem::SOURCE_MAINTENANCE, collect()));

        foreach ($items as $item) {
            $this->assertInstanceOf(TechnicalWorkItem::class, $item);
            $this->assertSame($siteId, $item->siteId, 'Mọi đầu việc phải giữ được liên kết công trình.');
            $this->assertNotSame('', $item->title, 'Tiêu đề phải được dịch sang nhãn đọc được.');
            $this->assertSame((int) $tech->id, $item->assignedUserId);
            $this->assertContains($item->statusGroup, [
                TechnicalWorkItem::GROUP_PENDING,
                TechnicalWorkItem::GROUP_IN_PROGRESS,
                TechnicalWorkItem::GROUP_DONE,
                TechnicalWorkItem::GROUP_CANCELLED,
            ]);
        }
    }

    /** Nhãn bước quy trình phải là tiếng Việt, không phải mã kỹ thuật. */
    public function test_project_workflow_item_uses_step_label_and_source_url(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $this->makeWorkflowAssignment($siteId, (int) $tech->id, ['step_code' => 'acceptance']);

        $item = $this->feed()
            ->paginate(['user_id' => (int) $tech->id], 50)
            ->getCollection()
            ->firstWhere('sourceType', TechnicalWorkItem::SOURCE_PROJECT_WORKFLOW);

        $this->assertNotNull($item);
        $this->assertSame(
            (string) config('project_workflow_v2.steps.acceptance.label'),
            $item->title,
        );

        if (Route::has('projects-unified.show')) {
            $this->assertStringContainsString('/du-an/'.$siteId, (string) $item->url);
        }
    }

    /** Việc quá hạn: hạn ở quá khứ + còn đang làm. */
    public function test_overdue_items_are_detected(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();

        // Bảng project_workflow_steps có UNIQUE (site_id, step_code) nên hai
        // đầu việc trên cùng công trình phải thuộc hai bước khác nhau.
        $this->makeOverdueWorkflowAssignment($siteId, (int) $tech->id);
        $this->makeWorkflowAssignment($siteId, (int) $tech->id, [
            'step_code' => 'acceptance',
            'sequence' => 6,
            'due_at' => Carbon::today()->addDays(10)->setTime(17, 0),
        ]);

        $items = $this->feed()->paginate(['user_id' => (int) $tech->id], 50)->getCollection();

        $this->assertCount(1, $items->filter(fn (TechnicalWorkItem $i): bool => $i->isOverdue));

        $summary = $this->feed()->summary(['user_id' => (int) $tech->id]);
        $this->assertSame(1, $summary['overdue_items']);
    }

    /** Việc ĐÃ XONG thì không bị tính là quá hạn dù hạn đã trôi qua. */
    public function test_completed_item_is_not_overdue(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();

        $this->makeWorkflowAssignment($siteId, (int) $tech->id, [
            'due_at' => Carbon::today()->subDays(9)->setTime(17, 0),
        ], [
            'status' => 'approved',
        ]);

        $summary = $this->feed()->summary(['user_id' => (int) $tech->id]);

        $this->assertSame(0, $summary['overdue_items']);
    }

    /** Kỹ thuật viên chỉ thấy việc ĐƯỢC GIAO cho mình. */
    public function test_feed_scopes_items_to_the_assigned_user(): void
    {
        $mine = $this->technician();
        $other = $this->technician();
        $siteId = $this->makeSite();

        $this->makeTask($siteId, (int) $mine->id);
        $this->makeTask($siteId, (int) $other->id);
        $this->makeTask($siteId, (int) $other->id);

        $this->assertSame(1, $this->feed()->query(['user_id' => (int) $mine->id])->count());
        $this->assertSame(2, $this->feed()->query(['user_id' => (int) $other->id])->count());
    }

    /** Công ty khác KHÔNG được rò sang company scope đang hoạt động. */
    public function test_feed_never_leaks_items_from_another_company(): void
    {
        $tech = $this->technician();

        $ourSite = $this->makeSite();
        $foreignSite = $this->makeSite($this->companyId() + 9_999);

        $this->makeTask($ourSite, (int) $tech->id);
        $this->makeTask($foreignSite, (int) $tech->id, ['company_id' => $this->companyId() + 9_999]);
        $this->makeWorkflowAssignment($foreignSite, (int) $tech->id);
        $this->makeMaintenance($foreignSite, (int) $tech->id, ['company_id' => $this->companyId() + 9_999]);

        $items = $this->feed()->paginate(['user_id' => (int) $tech->id], 50)->getCollection();

        $this->assertCount(1, $items, 'Chỉ đầu việc của công ty đang hoạt động được trả về.');
        $this->assertSame($ourSite, $items->first()->siteId);
    }

    /** Thẻ "Bảo hành đang xử lý" chỉ đếm lịch loại bảo hành/sự cố chưa đóng. */
    public function test_open_warranty_count_uses_real_type_and_status_columns(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();

        $this->makeMaintenance($siteId, (int) $tech->id, ['type' => 'warranty_inverter', 'status' => 'in_progress']);
        $this->makeMaintenance($siteId, (int) $tech->id, ['type' => 'incident', 'status' => 'assigned']);
        $this->makeMaintenance($siteId, (int) $tech->id, ['type' => 'warranty_inverter', 'status' => 'completed']);
        $this->makeMaintenance($siteId, (int) $tech->id, ['type' => 'periodic', 'status' => 'in_progress']);

        $this->assertSame(2, $this->feed()->openWarrantyCount(['user_id' => (int) $tech->id]));
    }

    /**
     * "Báo cáo chưa nộp": đầu việc đang mở, đến hạn, mà hôm nay chưa có báo
     * cáo ở trạng thái đã gửi / đã duyệt.
     */
    public function test_unreported_count_drops_after_a_report_is_submitted(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $taskId = $this->makeTask($siteId, (int) $tech->id, ['due_at' => Carbon::today()->setTime(17, 0)]);

        $this->assertSame(1, $this->feed()->unreportedCount(['user_id' => (int) $tech->id]));

        // Bản nháp CHƯA tính là đã nộp.
        $this->makeDailyReport($tech, [
            'source_type' => TechnicalWorkItem::SOURCE_TASK,
            'source_id' => $taskId,
            'status' => 'draft',
        ]);
        $this->assertSame(1, $this->feed()->unreportedCount(['user_id' => (int) $tech->id]));

        // Đã gửi thì hết "chưa nộp".
        DB::table('technical_daily_reports')
            ->where('source_id', $taskId)
            ->where('user_id', $tech->id)
            ->update(['status' => 'submitted']);

        $this->assertSame(0, $this->feed()->unreportedCount(['user_id' => (int) $tech->id]));
    }

    /** Đếm phải chạy ở DB, không phụ thuộc trang đang xem. */
    public function test_summary_counts_all_rows_not_only_the_current_page(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();

        for ($i = 0; $i < 25; $i++) {
            $this->makeTask($siteId, (int) $tech->id);
        }

        $summary = $this->feed()->summary(['user_id' => (int) $tech->id]);
        $page = $this->feed()->paginate(['user_id' => (int) $tech->id], 10);

        $this->assertSame(25, $summary['total_items']);
        $this->assertCount(10, $page->items(), 'Trang vẫn phải bị giới hạn.');
        $this->assertSame(25, $page->total());
    }

    /** Một đầu việc chỉ xuất hiện đúng một lần cho mỗi người được giao. */
    public function test_feed_deduplicates_by_source_and_user(): void
    {
        $techA = $this->technician();
        $techB = $this->technician();
        $siteId = $this->makeSite();

        $scheduleId = $this->makeMaintenance($siteId, (int) $techA->id);
        DB::table('solar_maintenance_assignees')->insert([
            'maintenance_schedule_id' => $scheduleId,
            'user_id' => $techB->id,
            'role' => 'member',
            'assignment_role' => 'member',
            'is_leader' => 0,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $keys = $this->feed()->paginate([], 50)->getCollection()
            ->map(fn (TechnicalWorkItem $item): string => $item->key());

        $this->assertSame(
            $keys->count(),
            $keys->unique()->count(),
            'Không được có đầu việc trùng khoá (nguồn + bản ghi + người được giao).',
        );

        // Hai người được giao cùng một lịch => hai dòng việc, không phải một.
        $this->assertCount(2, $keys->filter(fn (string $k): bool => str_starts_with($k, 'maintenance:'.$scheduleId.':')));
    }
}
