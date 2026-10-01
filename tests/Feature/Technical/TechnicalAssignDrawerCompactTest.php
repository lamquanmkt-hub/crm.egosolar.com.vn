<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalPlanItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Drawer "Giao việc cho nhân viên" gọn: bỏ Ngày / Buổi / Giờ bắt đầu / Giờ kết thúc /
 * Thời gian dự kiến / Mức độ ưu tiên (giữ giá trị mặc định ẩn); "Đầu việc" là ô tự nhập.
 * Drawer "Tạo kế hoạch tuần" (nhiều dòng) giữ nguyên đầy đủ.
 */
final class TechnicalAssignDrawerCompactTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private function assignDrawerHtml(string $html): string
    {
        $start = strpos($html, 'id="tpAssignDrawer"');
        $end = strpos($html, 'id="tpPlanRowTemplate"', (int) $start);
        $this->assertNotFalse($start, 'Thiếu drawer Giao việc.');

        return substr($html, (int) $start, ($end === false ? 20000 : $end - $start));
    }

    private function createDrawerHtml(string $html): string
    {
        $start = strpos($html, 'id="tpCreateDrawer"');
        $end = strpos($html, 'id="tpAssignDrawer"', (int) $start);
        $this->assertNotFalse($start, 'Thiếu drawer Tạo kế hoạch.');

        return substr($html, (int) $start, (int) $end - (int) $start);
    }

    public function test_assign_drawer_has_no_date_part_time_estimate_or_priority_fields(): void
    {
        $html = $this->actingAs($this->technicalManager())->get(route('technical.manager.board'))->assertOk()->getContent();
        $drawer = $this->assignDrawerHtml($html);

        foreach (['Ngày thực hiện', 'Buổi <', 'Giờ bắt đầu', 'Giờ kết thúc', 'Thời gian dự kiến', 'Mức độ ưu tiên', 'Công trình liên quan'] as $gone) {
            $this->assertStringNotContainsString($gone, $drawer, 'Drawer Giao việc không còn ô "'.$gone.'".');
        }

        // Giá trị mặc định vẫn được gửi để server kiểm tra như cũ.
        foreach (['plan_date', 'day_part', 'estimated_minutes', 'priority'] as $hidden) {
            $this->assertMatchesRegularExpression('/<input type="hidden" name="items\[0\]\['.$hidden.'\]"/', $drawer, 'Thiếu input ẩn '.$hidden);
        }
    }

    public function test_assign_drawer_dau_viec_is_a_free_text_input(): void
    {
        $html = $this->actingAs($this->technicalManager())->get(route('technical.manager.board'))->assertOk()->getContent();
        $drawer = $this->assignDrawerHtml($html);

        $this->assertMatchesRegularExpression('/<input type="text"[^>]*name="items\[0\]\[source_text\]"/', $drawer);
        $this->assertStringNotContainsString('name="items[0][source_id]"', $drawer);
        $this->assertStringContainsString('Nội dung công việc', $drawer);
        $this->assertStringContainsString('Lý do giao việc', $drawer);
    }

    public function test_create_plan_drawer_is_unchanged(): void
    {
        $html = $this->actingAs($this->technicalManager())->get(route('technical.manager.board'))->assertOk()->getContent();
        $drawer = $this->createDrawerHtml($html);

        foreach (['Ngày thực hiện', 'Giờ bắt đầu', 'Mức độ ưu tiên', 'Đầu việc'] as $kept) {
            $this->assertStringContainsString($kept, $drawer);
        }
        $this->assertStringContainsString('name="items[0][source_id]"', $drawer);
    }

    public function test_assign_with_maintenance_source_and_free_text_dau_viec_is_saved(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $this->actingAs($manager)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Bổ sung nhân lực cho công trình gấp',
            'confirm_warnings' => 1,
            'items' => [[
                'plan_date' => $week->toDateString(),
                'day_part' => 'full_day',
                'estimated_minutes' => 240,
                'priority' => 'normal',
                'source_type' => \App\Support\Technical\TechnicalWorkItem::SOURCE_MAINTENANCE,
                'source_text' => 'Bảo trì định kỳ CRMTO Long An',
                'title' => 'Kiểm tra inverter và vệ sinh tấm pin',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item = TechnicalPlanItem::query()->where('user_id', $tech->id)->latest('id')->first();
        $this->assertNotNull($item);
        $this->assertNull($item->source_id);
        $this->assertSame('Bảo trì định kỳ CRMTO Long An', $item->site_name);
        $this->assertSame(\App\Support\Technical\TechnicalWorkItem::SOURCE_MAINTENANCE, $item->source_type);
        $this->assertSame(1, (int) $item->is_manager_assigned);
    }

    public function test_feed_source_without_id_and_without_text_is_still_rejected(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $this->actingAs($manager)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Bổ sung nhân lực cho công trình gấp',
            'items' => [[
                'plan_date' => $week->toDateString(),
                'day_part' => 'full_day',
                'priority' => 'normal',
                'source_type' => \App\Support\Technical\TechnicalWorkItem::SOURCE_MAINTENANCE,
                'title' => 'Việc không có đầu việc',
            ]],
        ])->assertSessionHasErrors();

        $this->assertSame(0, TechnicalPlanItem::query()->where('user_id', $tech->id)->count());
    }
}
