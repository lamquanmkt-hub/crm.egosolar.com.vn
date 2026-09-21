<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Http\Controllers\Technical\TechnicalDailyReportController;
use App\Models\Technical\TechnicalPlanHistory;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * NGHIỆP VỤ MỚI 2026-09 — ADMIN / GIÁM ĐỐC ĐƯỢC TẠO KẾ HOẠCH VÀ GIAO VIỆC.
 *
 * Đảo ngược đặc tả "Admin chỉ xem" của đợt trước. Bộ test này khoá:
 *   1. Admin tạo / giao việc thành công qua HTTP THẬT, dữ liệu gắn đúng nhân
 *      viên / ngày / nguồn, và ghi rõ "do quản lý giao" (`is_manager_assigned`,
 *      `assigned_by`).
 *   2. KHÔNG ghi ngược vào bảng NGUỒN: đếm dòng `sites`, `tasks`,
 *      `project_workflow_*`, bảo trì trước và sau — phải bằng nhau.
 *   3. Lý do BẮT BUỘC (kể cả khi có cảnh báo trùng lịch / quá tải); cảnh báo là
 *      warning-only, không chặn cứng khi đã có lý do.
 *   4. Giao thêm việc tạo DÒNG MỚI, không ghi đè việc nhân viên tự lập.
 *   5. Mọi thao tác ghi nhật ký đầy đủ (người / trước / sau / thời gian / lý do).
 *   6. Trang `/ky-thuat/bao-cao-ngay` đúng phạm vi cho cả ba vai trò.
 *
 * Quyền của TRƯỞNG PHÒNG và của NHÂN VIÊN KHÔNG đổi — chỉ mở rộng cho Admin.
 */
final class TechnicalAdminAssignsWorkTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    /** Các bảng NGUỒN không bao giờ được ghi khi lập/giao kế hoạch. */
    private const SOURCE_TABLES = [
        'sites',
        'tasks',
        'project_workflow_steps',
        'project_workflow_assignments',
        'solar_maintenance_schedules',
    ];

    /*
    |--------------------------------------------------------------------------
    | 1–2. Admin giao việc thật, không đụng dữ liệu nguồn
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_assign_work_to_a_technician_over_http(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        $before = $this->sourceCounts();

        $this->actingAs($admin)
            ->post(route('technical.manager.assign', $tech), $this->planItemPayload([
                'plan_date' => $weekStart->toDateString(),
                'title' => 'Giam doc giao viec kiem thu',
                'reason' => 'Giám đốc điều phối bổ sung nhân lực.',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = TechnicalPlanItem::where('user_id', $tech->id)->firstOrFail();

        $this->assertSame($weekStart->toDateString(), $item->plan_date->toDateString());
        $this->assertSame('Giam doc giao viec kiem thu', $item->title);
        $this->assertSame(TechnicalPlanItem::SOURCE_MANAGER_ASSIGNED, $item->source_type);
        $this->assertTrue((bool) $item->is_manager_assigned);
        $this->assertSame((int) $admin->id, (int) $item->assigned_by);
        $this->assertSame((int) $admin->id, (int) $item->created_by);
        $this->assertSame($this->companyId(), (int) $item->company_id);

        // Lịch sử đầy đủ: người / hành động / lý do.
        $this->assertDatabaseHas('technical_plan_histories', [
            'plan_item_id' => $item->id,
            'action' => TechnicalPlanHistory::ACTION_ASSIGN,
            'reason' => 'Giám đốc điều phối bổ sung nhân lực.',
            'user_id' => $admin->id,
        ]);

        // KHÔNG ghi ngược vào bất kỳ bảng nguồn nào.
        $this->assertSame($before, $this->sourceCounts());
    }

    /** Admin chọn được nhân viên trong TOÀN ĐỘI, không chỉ một người. */
    public function test_admin_can_assign_to_any_technician_of_the_team(): void
    {
        $admin = $this->admin();
        $first = $this->technician(['name' => 'Ky Thuat Mot Test']);
        $second = $this->technician(['name' => 'Ky Thuat Hai Test']);
        $weekStart = $this->currentWeekStart();

        foreach ([$first, $second] as $index => $tech) {
            $this->actingAs($admin)
                ->post(route('technical.manager.assign', $tech), $this->planItemPayload([
                    'plan_date' => $weekStart->copy()->addDays($index)->toDateString(),
                    'title' => 'Viec giao cheo '.$index,
                    'reason' => 'Phân công theo năng lực từng người.',
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(1, TechnicalPlanItem::where('user_id', $first->id)->count());
        $this->assertSame(1, TechnicalPlanItem::where('user_id', $second->id)->count());

        // Cả hai đều xuất hiện trong bảng kế hoạch của Admin.
        $this->actingAs($admin)
            ->get(route('technical.dashboard.plans', ['week' => $weekStart->toDateString()]))
            ->assertOk()
            ->assertSee('Ky Thuat Mot Test')
            ->assertSee('Ky Thuat Hai Test');
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Lý do bắt buộc + cảnh báo trùng lịch / quá tải
    |--------------------------------------------------------------------------
    */

    public function test_assigning_without_a_reason_is_rejected(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();

        $this->actingAs($admin)
            ->post(route('technical.manager.assign', $tech), $this->planItemPayload([
                'plan_date' => $this->currentWeekStart()->toDateString(),
            ]))
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, TechnicalPlanItem::where('user_id', $tech->id)->count());
    }

    /**
     * Giao TRÙNG khung giờ: không có lý do => bị chặn; có lý do => vẫn lưu được
     * (cảnh báo là warning-only, đúng hành vi sẵn có) và cảnh báo hiện lên.
     */
    public function test_conflicting_assignment_needs_a_reason_but_is_not_hard_blocked(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $day = $weekStart->toDateString();

        $planId = $this->makeWeekPlan($tech, $weekStart);
        $this->makePlanItem($tech, $planId, [
            'plan_date' => $day,
            'day_part' => 'custom',
            'start_time' => '08:00',
            'end_time' => '11:00',
            'estimated_minutes' => 180,
        ]);

        $payload = $this->planItemPayload([
            'plan_date' => $day,
            'day_part' => 'custom',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'estimated_minutes' => 120,
            'title' => 'Viec trung khung gio',
        ]);

        // Không lý do => lỗi validate, không ghi gì.
        $this->actingAs($admin)
            ->post(route('technical.manager.assign', $tech), $payload)
            ->assertSessionHasErrors('reason');

        $this->assertSame(1, TechnicalPlanItem::where('user_id', $tech->id)->count());

        // Có lý do => lưu được, cảnh báo vẫn hiển thị cho người điều phối.
        $this->actingAs($admin)
            ->post(route('technical.manager.assign', $tech), array_merge($payload, [
                'reason' => 'Chấp nhận chồng lịch vì khách yêu cầu gấp.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, TechnicalPlanItem::where('user_id', $tech->id)->count());

        $check = $this->actingAs($admin)
            ->get(route('technical.dashboard.plans.detail', [
                'member' => $tech->id,
                'week' => $weekStart->toDateString(),
            ]))
            ->assertOk()
            ->viewData('check');

        $this->assertNotSame([], $check['warnings'], 'Phải có cảnh báo trùng khung giờ.');
    }

    /*
    |--------------------------------------------------------------------------
    | 4–5. Không ghi đè việc nhân viên tự lập; điều chỉnh ghi trước/sau
    |--------------------------------------------------------------------------
    */

    public function test_assigning_never_overwrites_a_row_the_technician_created(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        $planId = $this->makeWeekPlan($tech, $weekStart);
        $ownItemId = $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->toDateString(),
            'title' => 'Viec nhan vien tu lap',
        ]);

        $this->actingAs($admin)
            ->post(route('technical.manager.assign', $tech), $this->planItemPayload([
                'plan_date' => $weekStart->toDateString(),
                'title' => 'Viec quan ly giao them',
                'reason' => 'Giao thêm việc cho ngày đầu tuần.',
            ]))
            ->assertSessionHasNoErrors();

        // Dòng cũ còn nguyên, dòng mới được THÊM chứ không ghi đè.
        $own = TechnicalPlanItem::findOrFail($ownItemId);
        $this->assertSame('Viec nhan vien tu lap', $own->title);
        $this->assertFalse((bool) $own->is_manager_assigned);

        $this->assertSame(2, TechnicalPlanItem::where('user_id', $tech->id)->count());
    }

    public function test_admin_adjustment_records_before_after_and_reason(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        $planId = $this->makeWeekPlan($tech, $weekStart);
        $itemId = $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->toDateString(),
            'title' => 'Tieu de truoc khi sua',
            'estimated_minutes' => 120,
        ]);

        // Thiếu lý do => chặn.
        $this->actingAs($admin)
            ->put(route('technical.manager.items.adjust', $itemId), $this->planItemPayload([
                'plan_date' => $weekStart->toDateString(),
                'title' => 'Tieu de sau khi sua',
            ]))
            ->assertSessionHasErrors('reason');

        $this->assertSame('Tieu de truoc khi sua', TechnicalPlanItem::findOrFail($itemId)->title);

        $this->actingAs($admin)
            ->put(route('technical.manager.items.adjust', $itemId), $this->planItemPayload([
                'plan_date' => $weekStart->toDateString(),
                'title' => 'Tieu de sau khi sua',
                'reason' => 'Đổi nội dung theo yêu cầu khách hàng.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Tieu de sau khi sua', TechnicalPlanItem::findOrFail($itemId)->title);

        $history = TechnicalPlanHistory::where('plan_item_id', $itemId)
            ->where('action', TechnicalPlanHistory::ACTION_UPDATE)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame((int) $admin->id, (int) $history->user_id);
        $this->assertSame('Đổi nội dung theo yêu cầu khách hàng.', $history->reason);
        $this->assertStringContainsString('Tieu de truoc khi sua', (string) $history->changes_before);
        $this->assertStringContainsString('Tieu de sau khi sua', (string) $history->changes_after);
        $this->assertNotNull($history->created_at);

        // Kế hoạch tuần được đánh dấu "đã điều chỉnh", lịch sử cũ không bị xoá.
        $this->assertSame(
            TechnicalWeekPlan::STATUS_ADJUSTED,
            TechnicalWeekPlan::where('user_id', $tech->id)->value('status'),
        );
        $this->assertGreaterThanOrEqual(
            1,
            TechnicalPlanHistory::where('plan_item_id', $itemId)->count(),
        );
    }

    /** Nhân viên thường vẫn KHÔNG được giao việc cho người khác. */
    public function test_technician_still_cannot_assign_work_to_others(): void
    {
        $tech = $this->technician();
        $other = $this->technician();

        $this->actingAs($tech)
            ->post(route('technical.manager.assign', $other), $this->planItemPayload([
                'plan_date' => $this->currentWeekStart()->toDateString(),
                'reason' => 'Thử vượt quyền.',
            ]))
            ->assertForbidden();

        $this->assertSame(0, TechnicalPlanItem::where('user_id', $other->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Trang Báo cáo ngày/tuần dùng chung — phạm vi theo vai trò
    |--------------------------------------------------------------------------
    */

    public function test_shared_report_page_opens_for_all_three_roles(): void
    {
        foreach ([$this->admin(), $this->technicalManager(), $this->technician()] as $user) {
            $this->actingAs($user)
                ->get(route('technical.daily-reports.index'))
                ->assertOk();

            $this->actingAs($user)
                ->get(route('technical.daily-reports.index', ['tab' => TechnicalDailyReportController::TAB_WEEKLY]))
                ->assertOk();
        }
    }

    /** Nhân viên chỉ thấy báo cáo của mình và KHÔNG có bộ lọc nhân sự toàn đội. */
    public function test_technician_sees_only_own_reports_and_no_team_filter(): void
    {
        $mine = $this->technician();
        $other = $this->technician();

        $this->makeDailyReport($mine, ['work_title' => 'BAO CAO CUA TOI']);
        $this->makeDailyReport($other, ['work_title' => 'BAO CAO NGUOI KHAC']);

        $response = $this->actingAs($mine)->get(route('technical.daily-reports.index'));

        $response->assertOk();
        $response->assertSee('BAO CAO CUA TOI');
        $response->assertDontSee('BAO CAO NGUOI KHAC');
        $response->assertDontSee('Tất cả nhân sự');
        $response->assertDontSee('id="r-user"', false);

        // Kể cả khi tự nhét user_id của người khác vào URL.
        $forced = $this->actingAs($mine)->get(route('technical.daily-reports.index', [
            'user_id' => $other->id,
        ]));
        $forced->assertOk();
        $forced->assertDontSee('BAO CAO NGUOI KHAC');
    }

    /** Tab Tổng hợp tuần của nhân viên chỉ gồm chính họ. */
    public function test_weekly_summary_tab_is_scoped_for_a_technician(): void
    {
        $mine = $this->technician(['name' => 'Ky Thuat Chinh Chu Test']);
        $other = $this->technician(['name' => 'Ky Thuat Nguoi Khac Test']);

        $response = $this->actingAs($mine)->get(route('technical.daily-reports.index', [
            'tab' => TechnicalDailyReportController::TAB_WEEKLY,
            'user_id' => $other->id,
        ]));

        $response->assertOk();

        $ids = $response->viewData('rows')->pluck('user_id')->map(fn ($id): int => (int) $id)->all();

        $this->assertSame([(int) $mine->id], $ids);
        $response->assertDontSee('Ky Thuat Nguoi Khac Test');
    }

    /** Quản lý thấy bộ lọc nhân sự và dữ liệu toàn đội. */
    public function test_manager_and_admin_see_the_team_filter(): void
    {
        $tech = $this->technician();
        $this->makeDailyReport($tech, ['work_title' => 'BAO CAO TOAN DOI']);

        foreach ([$this->admin(), $this->technicalManager()] as $user) {
            $response = $this->actingAs($user)->get(route('technical.daily-reports.index'));

            $response->assertOk();
            $response->assertSee('BAO CAO TOAN DOI');
            $response->assertSee('id="r-user"', false);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Hỗ trợ
    |--------------------------------------------------------------------------
    */

    /** @return array<string, int> */
    private function sourceCounts(): array
    {
        $counts = [];

        foreach (self::SOURCE_TABLES as $table) {
            $counts[$table] = (int) DB::table($table)->count();
        }

        return $counts;
    }
}
