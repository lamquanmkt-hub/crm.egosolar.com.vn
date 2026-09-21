<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalPlanHistory;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Điều phối của TRƯỞNG PHÒNG KỸ THUẬT.
 */
final class TechnicalPlanBoardTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    public function test_board_is_restricted_to_managers(): void
    {
        $this->actingAs($this->technician())
            ->get(route('technical.manager.board'))
            ->assertForbidden();

        $this->actingAs($this->outsider())
            ->get(route('technical.manager.board'))
            ->assertForbidden();

        $this->actingAs($this->technicalManager())
            ->get(route('technical.manager.board'))
            ->assertOk();
    }

    /** Trưởng phòng thấy kế hoạch của TOÀN BỘ nhân viên trong phạm vi. */
    public function test_manager_sees_the_whole_team(): void
    {
        $manager = $this->technicalManager();
        $techA = $this->technician(['name' => 'Ky Thuat Alpha Test']);
        $techB = $this->technician(['name' => 'Ky Thuat Beta Test']);
        $weekStart = $this->currentWeekStart();

        foreach ([$techA, $techB] as $tech) {
            $planId = $this->makeWeekPlan($tech, $weekStart);
            $this->makePlanItem($tech, $planId, ['plan_date' => $weekStart->toDateString()]);
        }

        $this->actingAs($manager)
            ->get(route('technical.manager.board', ['week' => $weekStart->toDateString()]))
            ->assertOk()
            ->assertSee('Ky Thuat Alpha Test')
            ->assertSee('Ky Thuat Beta Test');
    }

    /** Ai chưa lập kế hoạch tuần thì hiện rõ trên tổng quan phòng. */
    public function test_overview_flags_staff_without_a_week_plan(): void
    {
        $manager = $this->technicalManager();
        $lazy = $this->technician(['name' => 'Ky Thuat Chua Lap Ke Hoach']);
        $weekStart = $this->currentWeekStart();

        $response = $this->actingAs($manager)
            ->get(route('technical.manager.overview', ['week' => $weekStart->toDateString()]));

        $response->assertOk()->assertSee('Ky Thuat Chua Lap Ke Hoach');

        $rows = $response->viewData('rows');
        $row = collect($rows)->firstWhere('user_id', (int) $lazy->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['has_week_plan']);
    }

    /** Trưởng phòng giao thêm việc — bắt buộc lý do và ghi nhật ký. */
    public function test_manager_can_assign_extra_work_with_a_mandatory_reason(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        // Thiếu lý do => bị chặn.
        $this->actingAs($manager)
            ->post(route('technical.manager.assign', $tech), $this->planItemPayload([
                'plan_date' => $weekStart->toDateString(),
                'title' => 'Việc trưởng phòng giao',
            ]))
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, TechnicalPlanItem::where('user_id', $tech->id)->count());

        // Có lý do => giao được.
        $this->actingAs($manager)
            ->post(route('technical.manager.assign', $tech), $this->planItemPayload([
                'plan_date' => $weekStart->toDateString(),
                'title' => 'Việc trưởng phòng giao',
                'reason' => 'Bổ sung nhân lực cho công trình gấp.',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = TechnicalPlanItem::where('user_id', $tech->id)->firstOrFail();
        $this->assertTrue((bool) $item->is_manager_assigned);
        $this->assertSame((int) $manager->id, (int) $item->assigned_by);
        $this->assertSame(TechnicalPlanItem::SOURCE_MANAGER_ASSIGNED, $item->source_type);

        $this->assertDatabaseHas('technical_plan_histories', [
            'plan_item_id' => $item->id,
            'action' => TechnicalPlanHistory::ACTION_ASSIGN,
            'reason' => 'Bổ sung nhân lực cho công trình gấp.',
            'user_id' => $manager->id,
        ]);

        // Kế hoạch tuần chuyển sang trạng thái "đã được Trưởng phòng điều chỉnh".
        $this->assertSame(
            TechnicalWeekPlan::STATUS_ADJUSTED,
            TechnicalWeekPlan::where('user_id', $tech->id)->value('status'),
        );
    }

    /** Điều chỉnh lưu ĐẦY ĐỦ: người chỉnh / trước / sau / thời gian / lý do. */
    public function test_adjustment_records_before_and_after_with_a_reason(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        $planId = $this->makeWeekPlan($tech, $weekStart);
        $itemId = $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->toDateString(),
            'title' => 'Tiêu đề ban đầu',
            'estimated_minutes' => 120,
            'priority' => 'normal',
        ]);
        $item = TechnicalPlanItem::findOrFail($itemId);

        $this->actingAs($manager)
            ->put(route('technical.manager.items.adjust', $item), $this->planItemPayload([
                'plan_date' => $weekStart->copy()->addDay()->toDateString(),
                'title' => 'Tiêu đề sau điều chỉnh',
                'estimated_minutes' => 300,
                'priority' => 'high',
            ]))
            ->assertSessionHasErrors('reason');

        $this->actingAs($manager)
            ->put(route('technical.manager.items.adjust', $item), $this->planItemPayload([
                'plan_date' => $weekStart->copy()->addDay()->toDateString(),
                'title' => 'Tiêu đề sau điều chỉnh',
                'estimated_minutes' => 300,
                'priority' => 'high',
                'reason' => 'Dời lịch do khách đổi ngày tiếp nhận.',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $history = TechnicalPlanHistory::query()
            ->where('plan_item_id', $itemId)
            ->whereIn('action', [TechnicalPlanHistory::ACTION_UPDATE, TechnicalPlanHistory::ACTION_MOVE])
            ->latest('id')
            ->firstOrFail();

        $this->assertSame((int) $manager->id, (int) $history->user_id);
        $this->assertSame('Dời lịch do khách đổi ngày tiếp nhận.', $history->reason);
        $this->assertNotNull($history->created_at);

        $before = $history->beforeArray();
        $after = $history->afterArray();

        $this->assertSame('Tiêu đề ban đầu', $before['title'] ?? null);
        $this->assertSame('Tiêu đề sau điều chỉnh', $after['title'] ?? null);
        $this->assertSame(120, (int) ($before['estimated_minutes'] ?? 0));
        $this->assertSame(300, (int) ($after['estimated_minutes'] ?? 0));
        $this->assertSame('normal', $before['priority'] ?? null);
        $this->assertSame('high', $after['priority'] ?? null);
    }

    /** Yêu cầu nhân viên cập nhật lại kế hoạch — bắt buộc lý do. */
    public function test_request_update_requires_a_reason(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $this->makeWeekPlan($tech, $weekStart);

        $this->actingAs($manager)
            ->post(route('technical.manager.request-update', $tech), ['week' => $weekStart->toDateString()])
            ->assertSessionHasErrors('reason');

        $this->actingAs($manager)
            ->post(route('technical.manager.request-update', $tech), [
                'week' => $weekStart->toDateString(),
                'reason' => 'Thiếu kế hoạch thứ Năm và thứ Sáu.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $plan = TechnicalWeekPlan::where('user_id', $tech->id)->firstOrFail();
        $this->assertTrue((bool) $plan->update_requested);
        $this->assertSame('Thiếu kế hoạch thứ Năm và thứ Sáu.', $plan->update_request_note);

        $this->assertDatabaseHas('technical_plan_histories', [
            'week_plan_id' => $plan->id,
            'action' => TechnicalPlanHistory::ACTION_REQUEST_UPDATE,
        ]);
    }

    /** Trưởng phòng KHÔNG có endpoint nhập hộ báo cáo kết quả. */
    public function test_manager_has_no_endpoint_to_write_reports_for_staff(): void
    {
        $names = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->map(fn ($route): ?string => $route->getName())
            ->filter()
            ->filter(fn (string $name): bool => str_starts_with($name, 'technical.manager.'))
            ->values()
            ->all();

        sort($names);

        /*
         * V7 (2026-09): thêm ĐÚNG HAI tên route cho luồng tạo/giao kế hoạch
         * trực tiếp — `plan.store` (lối GHI dùng chung của hai drawer) và
         * `work-items` (endpoint CHỈ ĐỌC nạp danh sách đầu việc).
         * Ý nghĩa của test KHÔNG đổi: vẫn không tồn tại endpoint nào cho phép
         * trưởng phòng nhập hộ BÁO CÁO KẾT QUẢ của nhân viên.
         */
        $this->assertSame([
            'technical.manager.assign',
            'technical.manager.board',
            'technical.manager.detail',
            'technical.manager.items.adjust',
            'technical.manager.items.move',
            'technical.manager.overview',
            'technical.manager.plan.store',
            'technical.manager.request-update',
            'technical.manager.weekly-summary',
            'technical.manager.work-items',
        ], $names);

        // Không route nào của trưởng phòng đụng tới bảng báo cáo ngày.
        foreach ($names as $name) {
            $this->assertStringNotContainsString('report', $name);
            $this->assertStringNotContainsString('bao-cao', $name);
        }
    }

    /** Không rò dữ liệu của công ty khác. */
    public function test_board_never_leaks_another_company(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        $planId = $this->makeWeekPlan($tech, $weekStart);
        $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->toDateString(),
            'title' => 'VIEC CONG TY HIEN TAI',
        ]);
        $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->copy()->addDay()->toDateString(),
            'title' => 'VIEC CONG TY KHAC',
            'company_id' => $this->companyId() + 9_999,
        ]);

        $this->actingAs($manager)
            ->get(route('technical.manager.detail', ['member' => $tech->id, 'week' => $weekStart->toDateString()]))
            ->assertOk()
            ->assertSee('VIEC CONG TY HIEN TAI')
            ->assertDontSee('VIEC CONG TY KHAC');
    }

    /** Điều phối KHÔNG ghi gì vào module Công trình. */
    public function test_coordination_never_writes_to_project_module(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $weekStart = $this->currentWeekStart();

        $before = [
            'sites' => DB::table('sites')->count(),
            'assignments' => DB::table('project_workflow_assignments')->count(),
            'site_updated' => DB::table('sites')->where('id', $siteId)->value('updated_at'),
        ];

        $this->actingAs($manager)
            ->post(route('technical.manager.assign', $tech), $this->planItemPayload([
                'plan_date' => $weekStart->toDateString(),
                'reason' => 'Giao thêm việc cho công trình mới.',
            ]))
            ->assertRedirect();

        $this->assertSame($before['sites'], DB::table('sites')->count());
        $this->assertSame($before['assignments'], DB::table('project_workflow_assignments')->count());
        $this->assertSame($before['site_updated'], DB::table('sites')->where('id', $siteId)->value('updated_at'));
    }

    /** Ma trận không truy vấn theo từng ô (N+1). */
    public function test_board_does_not_issue_queries_per_cell(): void
    {
        $manager = $this->technicalManager();
        $weekStart = $this->currentWeekStart();

        $small = $this->countBoardQueries($manager, $weekStart);

        for ($i = 0; $i < 4; $i++) {
            $tech = $this->technician();
            $planId = $this->makeWeekPlan($tech, $weekStart);

            foreach (TechnicalWeekPlan::weekDays($weekStart) as $day) {
                $this->makePlanItem($tech, $planId, ['plan_date' => $day->toDateString()]);
            }
        }

        $large = $this->countBoardQueries($manager, $weekStart);

        $this->assertLessThanOrEqual(
            $small + 3,
            $large,
            "Số truy vấn tăng theo số ô (N+1): {$small} → {$large}.",
        );
    }

    private function countBoardQueries($user, $weekStart): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->get(route('technical.manager.board', ['week' => $weekStart->toDateString()]))
            ->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }
}
