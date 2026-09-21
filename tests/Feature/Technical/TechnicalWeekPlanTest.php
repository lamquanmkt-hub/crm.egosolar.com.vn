<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalPlanHistory;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Kế hoạch tuần của tôi" — nhân viên tự lập kế hoạch 7 ngày.
 */
final class TechnicalWeekPlanTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    public function test_routes_exist(): void
    {
        foreach ([
            'technical.week-plan.index',
            'technical.week-plan.items.store',
            'technical.week-plan.items.update',
            'technical.week-plan.items.destroy',
            'technical.week-plan.copy-previous',
            'technical.week-plan.mark-day',
            'technical.week-plan.finalize',
            'technical.today',
        ] as $name) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($name), "Thiếu route: {$name}");
        }
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('technical.week-plan.index'))->assertRedirect();
    }

    public function test_non_technical_user_is_forbidden(): void
    {
        $this->actingAs($this->outsider())
            ->get(route('technical.week-plan.index'))
            ->assertForbidden();
    }

    /** Nhân viên lập kế hoạch ĐỦ 7 ngày rồi hoàn tất được. */
    public function test_technician_can_plan_all_seven_days_and_finalize(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        foreach (TechnicalWeekPlan::weekDays($weekStart) as $index => $day) {
            $this->actingAs($tech)
                ->post(route('technical.week-plan.items.store'), $this->planItemPayload([
                    'week' => $weekStart->toDateString(),
                    'plan_date' => $day->toDateString(),
                    'title' => 'Công việc ngày '.($index + 1),
                    'estimated_minutes' => 240,
                ]))
                ->assertRedirect();
        }

        $this->assertSame(7, TechnicalPlanItem::where('user_id', $tech->id)->count());

        $this->actingAs($tech)
            ->post(route('technical.week-plan.finalize'), ['week' => $weekStart->toDateString()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $plan = TechnicalWeekPlan::where('user_id', $tech->id)->firstOrFail();
        $this->assertSame(TechnicalWeekPlan::STATUS_FINALIZED, $plan->status);
        $this->assertNotNull($plan->finalized_at);
    }

    /** Một ngày có thể chứa nhiều việc. */
    public function test_a_single_day_can_hold_several_items(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $day = $weekStart->copy()->addDays(2);

        foreach (['Việc sáng' => 'morning', 'Việc chiều' => 'afternoon'] as $title => $part) {
            $this->actingAs($tech)
                ->post(route('technical.week-plan.items.store'), $this->planItemPayload([
                    'week' => $weekStart->toDateString(),
                    'plan_date' => $day->toDateString(),
                    'day_part' => $part,
                    'title' => $title,
                    'estimated_minutes' => 180,
                ]))
                ->assertRedirect();
        }

        $this->assertSame(
            2,
            TechnicalPlanItem::where('user_id', $tech->id)->whereDate('plan_date', $day)->count(),
        );

        $this->actingAs($tech)
            ->get(route('technical.week-plan.index', ['week' => $weekStart->toDateString()]))
            ->assertOk()
            ->assertSee('Việc sáng')
            ->assertSee('Việc chiều');
    }

    /** Chưa lập đủ ngày làm việc => KHÔNG hoàn tất được (lỗi chặn). */
    public function test_finalize_is_blocked_when_a_working_day_has_no_plan(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        $this->actingAs($tech)
            ->post(route('technical.week-plan.items.store'), $this->planItemPayload([
                'week' => $weekStart->toDateString(),
                'plan_date' => $weekStart->toDateString(),
            ]))
            ->assertRedirect();

        $this->actingAs($tech)
            ->post(route('technical.week-plan.finalize'), ['week' => $weekStart->toDateString()])
            ->assertSessionHasErrors('week');

        $plan = TechnicalWeekPlan::where('user_id', $tech->id)->firstOrFail();
        $this->assertNotSame(TechnicalWeekPlan::STATUS_FINALIZED, $plan->status);
    }

    /** Ngày được đánh dấu nghỉ / chờ phân công thì không còn là lỗi chặn. */
    public function test_marked_days_satisfy_the_finalize_check(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $days = TechnicalWeekPlan::weekDays($weekStart);

        // Chỉ lập kế hoạch cho thứ Hai; 5 ngày làm việc còn lại đánh dấu nghỉ.
        $this->actingAs($tech)
            ->post(route('technical.week-plan.items.store'), $this->planItemPayload([
                'week' => $weekStart->toDateString(),
                'plan_date' => $days[0]->toDateString(),
            ]))
            ->assertRedirect();

        foreach (array_slice($days, 1, 5) as $day) {
            $this->actingAs($tech)
                ->post(route('technical.week-plan.mark-day'), [
                    'week' => $weekStart->toDateString(),
                    'plan_date' => $day->toDateString(),
                    'mark' => 'leave',
                    'reason' => 'Nghỉ phép năm',
                ])
                ->assertRedirect();
        }

        $this->actingAs($tech)
            ->post(route('technical.week-plan.finalize'), ['week' => $weekStart->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            TechnicalWeekPlan::STATUS_FINALIZED,
            TechnicalWeekPlan::where('user_id', $tech->id)->value('status'),
        );
    }

    /** Nhân viên chỉ thấy kế hoạch của CHÍNH MÌNH. */
    public function test_technician_sees_only_their_own_plan(): void
    {
        $mine = $this->technician();
        $other = $this->technician();
        $weekStart = $this->currentWeekStart();

        $otherPlan = $this->makeWeekPlan($other, $weekStart);
        $this->makePlanItem($other, $otherPlan, [
            'plan_date' => $weekStart->toDateString(),
            'title' => 'VIEC CUA NGUOI KHAC',
        ]);

        $minePlan = $this->makeWeekPlan($mine, $weekStart);
        $this->makePlanItem($mine, $minePlan, [
            'plan_date' => $weekStart->toDateString(),
            'title' => 'VIEC CUA TOI',
        ]);

        $this->actingAs($mine)
            ->get(route('technical.week-plan.index', ['week' => $weekStart->toDateString()]))
            ->assertOk()
            ->assertSee('VIEC CUA TOI')
            ->assertDontSee('VIEC CUA NGUOI KHAC');
    }

    /** Không thể sửa / xoá dòng kế hoạch của người khác bằng cách đổi id. */
    public function test_technician_cannot_touch_someone_elses_plan_item(): void
    {
        $mine = $this->technician();
        $other = $this->technician();
        $weekStart = $this->currentWeekStart();

        $otherPlan = $this->makeWeekPlan($other, $weekStart);
        $itemId = $this->makePlanItem($other, $otherPlan, ['plan_date' => $weekStart->toDateString()]);
        $item = TechnicalPlanItem::findOrFail($itemId);

        $this->actingAs($mine)
            ->put(route('technical.week-plan.items.update', $item), $this->planItemPayload([
                'plan_date' => $weekStart->toDateString(),
                'title' => 'Cướp việc của người khác',
            ]))
            ->assertForbidden();

        $this->actingAs($mine)
            ->delete(route('technical.week-plan.items.destroy', $item))
            ->assertForbidden();

        $this->assertDatabaseHas('technical_plan_items', ['id' => $itemId, 'user_id' => $other->id]);
    }

    /**
     * Chống giả mạo: gửi thẳng source_id của việc ĐƯỢC GIAO CHO NGƯỜI KHÁC
     * cũng không thêm được vào kế hoạch của mình.
     */
    public function test_cannot_plan_a_work_item_assigned_to_someone_else(): void
    {
        $mine = $this->technician();
        $other = $this->technician();
        $siteId = $this->makeSite();
        $foreignTaskId = $this->makeTask($siteId, (int) $other->id);

        $this->actingAs($mine)
            ->post(route('technical.week-plan.items.store'), $this->planItemPayload([
                'week' => $this->currentWeekStart()->toDateString(),
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $foreignTaskId,
            ]))
            ->assertSessionHasErrors('source_id');

        $this->assertDatabaseMissing('technical_plan_items', [
            'user_id' => $mine->id,
            'source_type' => TechnicalWorkItem::SOURCE_TASK,
            'source_id' => $foreignTaskId,
        ]);
    }

    /** Việc được giao cho chính mình thì thêm được và giữ liên kết nguồn. */
    public function test_assigned_work_item_can_be_planned_and_keeps_the_link(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $taskId = $this->makeTask($siteId, (int) $tech->id);
        $weekStart = $this->currentWeekStart();

        $this->actingAs($tech)
            ->post(route('technical.week-plan.items.store'), $this->planItemPayload([
                'week' => $weekStart->toDateString(),
                'plan_date' => $weekStart->toDateString(),
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $taskId,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('technical_plan_items', [
            'user_id' => $tech->id,
            'source_type' => TechnicalWorkItem::SOURCE_TASK,
            'source_id' => $taskId,
            'site_id' => $siteId,
        ]);
    }

    /** Cùng một việc nguồn không được thêm hai lần vào cùng một ngày. */
    public function test_same_source_cannot_be_planned_twice_in_one_day(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $taskId = $this->makeTask($siteId, (int) $tech->id);
        $weekStart = $this->currentWeekStart();

        $payload = $this->planItemPayload([
            'week' => $weekStart->toDateString(),
            'plan_date' => $weekStart->toDateString(),
            'source_type' => TechnicalWorkItem::SOURCE_TASK,
            'source_id' => $taskId,
        ]);

        $this->actingAs($tech)->post(route('technical.week-plan.items.store'), $payload)->assertRedirect();
        $this->actingAs($tech)->post(route('technical.week-plan.items.store'), $payload)
            ->assertSessionHasErrors('source_id');

        $this->assertSame(1, TechnicalPlanItem::where('user_id', $tech->id)->count());
    }

    /** Quá tải trong ngày là CẢNH BÁO, không chặn hoàn tất. */
    public function test_overload_is_a_warning_not_a_blocking_error(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $days = TechnicalWeekPlan::weekDays($weekStart);

        foreach ($days as $index => $day) {
            $this->actingAs($tech)
                ->post(route('technical.week-plan.items.store'), $this->planItemPayload([
                    'week' => $weekStart->toDateString(),
                    'plan_date' => $day->toDateString(),
                    'title' => 'Việc ngày '.($index + 1),
                    // Thứ Hai cố tình 12 giờ => vượt giờ chuẩn nhưng dưới trần cứng.
                    'estimated_minutes' => $index === 0 ? 720 : 120,
                ]))
                ->assertRedirect();
        }

        $response = $this->actingAs($tech)
            ->get(route('technical.week-plan.index', ['week' => $weekStart->toDateString()]));

        $response->assertOk();
        $check = $response->viewData('check');

        $this->assertSame([], $check['errors'], 'Quá tải không được là lỗi chặn.');
        $this->assertNotEmpty($check['warnings']);
        $this->assertStringContainsString('quá tải', mb_strtolower(implode(' ', $check['warnings'])));

        $this->actingAs($tech)
            ->post(route('technical.week-plan.finalize'), ['week' => $weekStart->toDateString()])
            ->assertSessionHasNoErrors();
    }

    /** Trùng khung giờ trong cùng một ngày sinh cảnh báo. */
    public function test_time_conflict_raises_a_warning(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $day = $weekStart->copy()->addDay();

        foreach (['Việc A', 'Việc B'] as $title) {
            $this->actingAs($tech)
                ->post(route('technical.week-plan.items.store'), $this->planItemPayload([
                    'week' => $weekStart->toDateString(),
                    'plan_date' => $day->toDateString(),
                    'day_part' => 'custom',
                    'start_time' => '09:00',
                    'end_time' => '11:00',
                    'title' => $title,
                    'estimated_minutes' => 120,
                ]))
                ->assertRedirect();
        }

        $check = $this->actingAs($tech)
            ->get(route('technical.week-plan.index', ['week' => $weekStart->toDateString()]))
            ->viewData('check');

        $this->assertStringContainsString('trùng khung giờ', implode(' ', $check['warnings']));
    }

    /** Chuyển việc sang ngày khác được ghi nhật ký. */
    public function test_moving_an_item_is_logged(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);
        $itemId = $this->makePlanItem($tech, $planId, ['plan_date' => $weekStart->toDateString()]);
        $item = TechnicalPlanItem::findOrFail($itemId);

        $target = $weekStart->copy()->addDays(3);

        $this->actingAs($tech)
            ->post(route('technical.week-plan.items.move', $item), ['plan_date' => $target->toDateString()])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame($target->toDateString(), $item->plan_date->toDateString());
        $this->assertSame($weekStart->toDateString(), $item->moved_from_date->toDateString());

        $this->assertDatabaseHas('technical_plan_histories', [
            'plan_item_id' => $itemId,
            'action' => TechnicalPlanHistory::ACTION_MOVE,
        ]);
    }

    /** Sao chép kế hoạch tuần trước. */
    public function test_copying_the_previous_week(): void
    {
        $tech = $this->technician();
        $thisWeek = $this->currentWeekStart();
        $lastWeek = $thisWeek->copy()->subWeek();

        $lastPlan = $this->makeWeekPlan($tech, $lastWeek);
        $this->makePlanItem($tech, $lastPlan, [
            'plan_date' => $lastWeek->toDateString(),
            'title' => 'Việc lặp lại hằng tuần',
        ]);

        $this->actingAs($tech)
            ->post(route('technical.week-plan.copy-previous'), ['week' => $thisWeek->toDateString()])
            ->assertRedirect();

        $this->assertDatabaseHas('technical_plan_items', [
            'user_id' => $tech->id,
            'plan_date' => $thisWeek->toDateString(),
            'title' => 'Việc lặp lại hằng tuần',
        ]);
    }

    /** Dòng kế hoạch đã có báo cáo thì không xoá được. */
    public function test_item_with_a_report_cannot_be_deleted(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);
        $itemId = $this->makePlanItem($tech, $planId, ['plan_date' => $weekStart->toDateString()]);

        $this->makeDailyReport($tech, [
            'plan_item_id' => $itemId,
            'week_plan_id' => $planId,
            'report_date' => $weekStart->toDateString(),
        ]);

        $this->actingAs($tech)
            ->delete(route('technical.week-plan.items.destroy', TechnicalPlanItem::findOrFail($itemId)))
            ->assertSessionHasErrors('id');

        $this->assertDatabaseHas('technical_plan_items', ['id' => $itemId, 'deleted_at' => null]);
    }

    /** Lập kế hoạch KHÔNG được ghi gì vào module Công trình. */
    public function test_planning_never_writes_to_project_module(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $taskId = $this->makeTask($siteId, (int) $tech->id);
        $weekStart = $this->currentWeekStart();

        $before = [
            'sites' => DB::table('sites')->count(),
            'steps' => DB::table('project_workflow_steps')->count(),
            'assignments' => DB::table('project_workflow_assignments')->count(),
            'task' => DB::table('tasks')->where('id', $taskId)->value('updated_at'),
        ];

        $this->actingAs($tech)
            ->post(route('technical.week-plan.items.store'), $this->planItemPayload([
                'week' => $weekStart->toDateString(),
                'plan_date' => $weekStart->toDateString(),
                'source_type' => TechnicalWorkItem::SOURCE_TASK,
                'source_id' => $taskId,
            ]))
            ->assertRedirect();

        $this->assertSame($before['sites'], DB::table('sites')->count());
        $this->assertSame($before['steps'], DB::table('project_workflow_steps')->count());
        $this->assertSame($before['assignments'], DB::table('project_workflow_assignments')->count());
        $this->assertSame($before['task'], DB::table('tasks')->where('id', $taskId)->value('updated_at'));
    }

    /** Trang "Công việc hôm nay" chỉ hiện việc của chính mình trong ngày. */
    public function test_today_page_shows_only_my_items_for_the_day(): void
    {
        $mine = $this->technician();
        $other = $this->technician();
        $weekStart = $this->currentWeekStart();

        $minePlan = $this->makeWeekPlan($mine, $weekStart);
        $this->makePlanItem($mine, $minePlan, [
            'plan_date' => Carbon::today()->toDateString(),
            'title' => 'VIEC HOM NAY CUA TOI',
        ]);

        $otherPlan = $this->makeWeekPlan($other, $weekStart);
        $this->makePlanItem($other, $otherPlan, [
            'plan_date' => Carbon::today()->toDateString(),
            'title' => 'VIEC HOM NAY NGUOI KHAC',
        ]);

        $this->actingAs($mine)
            ->get(route('technical.today'))
            ->assertOk()
            ->assertSee('VIEC HOM NAY CUA TOI')
            ->assertDontSee('VIEC HOM NAY NGUOI KHAC');
    }

    /** Số truy vấn của trang kế hoạch tuần không tăng theo số dòng (N+1). */
    public function test_week_plan_page_does_not_issue_queries_per_row(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);

        for ($i = 0; $i < 3; $i++) {
            $this->makePlanItem($tech, $planId, ['plan_date' => $weekStart->copy()->addDay()->toDateString()]);
        }

        $small = $this->countQueries($tech, $weekStart);

        for ($i = 0; $i < 20; $i++) {
            $this->makePlanItem($tech, $planId, [
                'plan_date' => $weekStart->copy()->addDays(2 + ($i % 5))->toDateString(),
            ]);
        }

        $large = $this->countQueries($tech, $weekStart);

        $this->assertLessThanOrEqual(
            $small + 3,
            $large,
            "Số truy vấn tăng theo số dòng (N+1): {$small} → {$large}.",
        );
    }

    private function countQueries($user, Carbon $weekStart): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->get(route('technical.week-plan.index', ['week' => $weekStart->toDateString()]))
            ->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }
}
