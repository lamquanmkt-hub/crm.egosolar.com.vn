<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * V7 (2026-09) — LUỒNG TẠO KẾ HOẠCH / GIAO VIỆC TRỰC TIẾP.
 *
 * Luồng CŨ (đã bỏ): "Tạo / Giao kế hoạch" -> cuộn tới khung chọn nhân viên
 * (`?focus=assign`) -> chọn nhân viên -> bấm "Chọn nhân viên" -> sang trang chi
 * tiết -> mới thấy form "Giao thêm việc" (6 bước).
 *
 * Luồng MỚI được khoá ở đây: header có HAI nút riêng, mỗi nút mở THẲNG form
 * thật (drawer render server-side ngay trong trang ma trận), lưu xong quay về
 * đúng tuần đang xem.
 *
 * Mọi thao tác ghi đi qua ĐÚNG MỘT endpoint `technical.manager.plan.store`.
 */
class TechnicalPlanCreateFlowTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    /** Năm bảng dữ liệu NGUỒN mà module Kỹ thuật chỉ được phép ĐỌC. */
    private const SOURCE_TABLES = [
        'sites',
        'tasks',
        'project_workflow_steps',
        'project_workflow_assignments',
        'solar_maintenance_schedules',
    ];

    /*
    |--------------------------------------------------------------------------
    | 1–5. Nút mở THẲNG form thật, không còn bước chọn nhân viên
    |--------------------------------------------------------------------------
    */

    public function test_board_header_has_two_separate_buttons_and_no_combined_one(): void
    {
        $html = $this->actingAs($this->technicalManager())
            ->get(route('technical.manager.board'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-tp-cta="create-plan"', $html);
        $this->assertStringContainsString('data-tp-cta="assign-work"', $html);
        $this->assertStringNotContainsString('Tạo / Giao kế hoạch', $html);
    }

    public function test_board_no_longer_requires_the_member_picker_step(): void
    {
        $html = $this->actingAs($this->technicalManager())
            ->get(route('technical.manager.board'))
            ->assertOk()
            ->getContent();

        // Khối chọn nhân viên trung gian và nút submit "Chọn nhân viên" đã bị gỡ.
        $this->assertStringNotContainsString('id="tp-assign-picker"', $html);
        $this->assertStringNotContainsString('id="tp-assign-member"', $html);
        $this->assertStringNotContainsString('Chọn nhân viên', $html);
    }

    public function test_create_plan_form_is_rendered_with_a_member_dropdown(): void
    {
        $tech = $this->technician();

        $html = $this->actingAs($this->technicalManager())
            ->get(route('technical.manager.board'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="tpCreateDrawer"', $html);
        $this->assertStringContainsString('id="tp-create-user"', $html);
        $this->assertStringContainsString('Nhân viên nhận kế hoạch', $html);
        $this->assertStringContainsString('>'.$tech->name.'<', $html);
        // Form thật: POST tới lối ghi dùng chung.
        $this->assertStringContainsString(route('technical.manager.plan.store'), $html);
    }

    public function test_assign_form_is_rendered_with_a_member_dropdown(): void
    {
        $html = $this->actingAs($this->technicalManager())
            ->get(route('technical.manager.board'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="tpAssignDrawer"', $html);
        $this->assertStringContainsString('id="tp-assign-user"', $html);
        $this->assertStringContainsString('Lý do giao việc', $html);
    }

    public function test_deep_links_render_the_matching_drawer_already_open(): void
    {
        $manager = $this->technicalManager();

        foreach (['create' => 'tpCreateDrawer', 'assign' => 'tpAssignDrawer'] as $open => $id) {
            $html = $this->actingAs($manager)
                ->get(route('technical.manager.board', ['open' => $open]))
                ->assertOk()
                ->getContent();

            $this->assertMatchesRegularExpression(
                '/id="'.$id.'"[^>]*data-tp-open="1"/s',
                $this->normaliseDrawerTag($html, $id),
                'Deep link ?open='.$open.' phải render drawer ở trạng thái mở.',
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 6–7. Ghi thật qua HTTP
    |--------------------------------------------------------------------------
    */

    public function test_admin_creates_a_multi_row_plan_over_http(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $before = $this->countPlanRows($tech->id);

        $response = $this->actingAs($admin)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Phân công kế hoạch thi công tuần',
            'confirm_warnings' => 1,
            'items' => [
                $this->row(['plan_date' => $week->toDateString(), 'title' => 'Lắp đặt khung đỡ']),
                $this->row(['plan_date' => $week->copy()->addDay()->toDateString(), 'title' => 'Đấu nối tủ điện']),
                $this->row(['plan_date' => $week->copy()->addDays(2)->toDateString(), 'title' => 'Nghiệm thu nội bộ']),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame($before + 3, $this->countPlanRows($tech->id));
        $this->assertStringContainsString(
            'Đã giao kế hoạch cho',
            (string) session('success'),
        );
    }

    public function test_manager_assigns_a_single_work_item(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $this->actingAs($manager)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Bổ sung nhân lực công trình gấp',
            'confirm_warnings' => 1,
            'items' => [$this->row(['plan_date' => $week->toDateString(), 'title' => 'Hỗ trợ kéo cáp DC'])],
        ])->assertRedirect();

        $item = TechnicalPlanItem::query()->where('user_id', $tech->id)->latest('id')->first();

        $this->assertNotNull($item);
        $this->assertSame(1, (int) $item->is_manager_assigned);
        $this->assertSame((int) $manager->id, (int) $item->assigned_by);
        $this->assertNotNull($item->assigned_at);
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Nhân viên KHÔNG chạm được các route mới
    |--------------------------------------------------------------------------
    */

    public function test_staff_is_forbidden_on_every_new_route(): void
    {
        $tech = $this->technician();
        $other = $this->technician();

        $this->actingAs($tech)
            ->get(route('technical.manager.work-items', ['user_id' => $other->id]))
            ->assertForbidden();

        $this->actingAs($tech)
            ->post(route('technical.manager.plan.store'), [
                'user_id' => $other->id,
                'week' => $this->currentWeekStart()->toDateString(),
                'reason' => 'Thử vượt quyền',
                'items' => [$this->row()],
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->countPlanRows($other->id));
    }

    public function test_outsider_is_forbidden_on_the_json_work_items_endpoint(): void
    {
        $this->actingAs($this->outsider())
            ->get(route('technical.manager.work-items', ['user_id' => $this->technician()->id]))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | 9. Ô ngày trong ma trận
    |--------------------------------------------------------------------------
    */

    public function test_matrix_day_cell_opens_the_assign_form_with_member_and_date(): void
    {
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $html = $this->actingAs($this->technicalManager())
            ->get(route('technical.manager.board', ['week' => $week->toDateString()]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-tp-assign-cell', $html);
        $this->assertStringContainsString('data-tp-user="'.$tech->id.'"', $html);
        $this->assertStringContainsString('data-tp-date="'.$week->toDateString().'"', $html);
    }

    public function test_deep_link_preselects_member_and_date_in_the_assign_form(): void
    {
        $tech = $this->technician();
        $week = $this->currentWeekStart();
        $date = $week->copy()->addDays(2)->toDateString();

        $response = $this->actingAs($this->technicalManager())->get(route('technical.manager.board', [
            'open' => 'assign',
            'week' => $week->toDateString(),
            'user_id' => $tech->id,
            'date' => $date,
        ]));

        $response->assertOk();
        $this->assertSame($tech->id, (int) $response->viewData('selectedMember'));
        $this->assertSame($date, (string) $response->viewData('selectedDate'));
        // Ô "Ngày thực hiện" của drawer Giao việc đã CHỌN SẴN đúng ngày.
        $flat = (string) preg_replace('/\s+/', ' ', $response->getContent());
        $this->assertStringContainsString('value="'.$date.'" selected', $flat);
        $this->assertStringContainsString('value="'.$tech->id.'" selected', $flat);
    }

    /*
    |--------------------------------------------------------------------------
    | 10–11. Sau khi lưu
    |--------------------------------------------------------------------------
    */

    public function test_saving_returns_to_the_same_week_and_shows_the_new_item(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart()->copy()->addWeeks(2);
        $day = $week->copy()->addDays(3);

        $response = $this->actingAs($manager)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Kế hoạch tuần kế tiếp theo tiến độ',
            'confirm_warnings' => 1,
            'items' => [$this->row(['plan_date' => $day->toDateString(), 'title' => 'Kiểm tra inverter'])],
        ]);

        $response->assertRedirect(route('technical.manager.board', ['week' => $week->toDateString()]));
        $this->assertStringContainsString('Đã giao kế hoạch cho', (string) session('success'));

        // Ma trận của ĐÚNG tuần đó hiển thị công việc vừa giao, đúng người/ngày.
        $board = $this->actingAs($manager)->get(route('technical.manager.board', ['week' => $week->toDateString()]));
        $board->assertOk();

        $row = collect($board->viewData('matrix'))->firstWhere('user_id', (int) $tech->id);

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['cells'][$day->toDateString()]['count']);
    }

    public function test_saving_keeps_the_active_filters(): void
    {
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $response = $this->actingAs($this->technicalManager())->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Giữ nguyên bộ lọc sau khi lưu',
            'confirm_warnings' => 1,
            'view_user_id' => $tech->id,
            'view_status' => 'planned',
            'items' => [$this->row(['plan_date' => $week->toDateString()])],
        ]);

        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('week='.$week->toDateString(), $location);
        $this->assertStringContainsString('user_id='.$tech->id, $location);
        $this->assertStringContainsString('status=planned', $location);
    }

    /*
    |--------------------------------------------------------------------------
    | 12. Thiếu lý do
    |--------------------------------------------------------------------------
    */

    public function test_assigning_without_a_reason_is_blocked_and_keeps_old_input(): void
    {
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $response = $this->actingAs($this->technicalManager())
            ->from(route('technical.manager.board'))
            ->post(route('technical.manager.plan.store'), [
                'user_id' => $tech->id,
                'week' => $week->toDateString(),
                'mode' => 'assign',
                'items' => [$this->row(['plan_date' => $week->toDateString(), 'title' => 'Việc không có lý do'])],
            ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(0, $this->countPlanRows($tech->id));

        // Dữ liệu đã nhập được giữ nguyên (cả mảng nhiều dòng).
        $this->assertSame('Việc không có lý do', (string) old('items.0.title'));
    }

    /*
    |--------------------------------------------------------------------------
    | 13. Cảnh báo trùng lịch / quá tải
    |--------------------------------------------------------------------------
    */

    public function test_conflicting_assignment_is_not_written_until_it_is_confirmed(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();
        $day = $week->toDateString();

        $planId = $this->makeWeekPlan($tech, $week);
        $this->makePlanItem($tech, $planId, [
            'plan_date' => $day,
            'day_part' => 'custom',
            'start_time' => '08:00',
            'end_time' => '11:00',
            'estimated_minutes' => 180,
        ]);

        $payload = [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Ghép thêm việc vào khung giờ đang bận',
            'items' => [$this->row([
                'plan_date' => $day,
                'day_part' => 'custom',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'estimated_minutes' => 120,
                'title' => 'Việc chồng giờ',
            ])],
        ];

        $before = $this->countPlanRows($tech->id);

        // Chưa xác nhận -> KHÔNG ghi, trả về cảnh báo.
        $blocked = $this->actingAs($manager)->post(route('technical.manager.plan.store'), $payload);
        $blocked->assertSessionHasErrors('confirm_warnings');
        $this->assertSame($before, $this->countPlanRows($tech->id));

        // Có xác nhận + lý do -> ghi.
        $this->actingAs($manager)
            ->post(route('technical.manager.plan.store'), $payload + ['confirm_warnings' => 1])
            ->assertRedirect();

        $this->assertSame($before + 1, $this->countPlanRows($tech->id));
    }

    /**
     * Cảnh báo được đếm theo SỐ LẦN, không theo tập hợp: một xung đột mới vẫn
     * phải chặn kể cả khi câu cảnh báo TRÙNG HỆT một cảnh báo đã có (hai dòng
     * cùng tên, cùng khung giờ).
     */
    public function test_a_repeated_identical_warning_still_requires_confirmation(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();
        $day = $week->toDateString();

        $planId = $this->makeWeekPlan($tech, $week);

        foreach ([['08:00', '11:00', 'Việc nền'], ['10:00', '12:00', 'Việc chồng']] as [$s, $e, $t]) {
            $this->makePlanItem($tech, $planId, [
                'plan_date' => $day, 'day_part' => 'custom',
                'start_time' => $s, 'end_time' => $e, 'title' => $t, 'estimated_minutes' => 120,
            ]);
        }

        $before = $this->countPlanRows($tech->id);

        // Dòng thứ ba TRÙNG TÊN dòng thứ hai -> sinh câu cảnh báo y hệt.
        $this->actingAs($manager)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Thêm một dòng trùng giờ và trùng tên',
            'items' => [$this->row([
                'plan_date' => $day, 'day_part' => 'custom',
                'start_time' => '10:00', 'end_time' => '12:00',
                'title' => 'Việc chồng', 'estimated_minutes' => 120,
            ])],
        ])->assertSessionHasErrors('confirm_warnings');

        $this->assertSame($before, $this->countPlanRows($tech->id));
    }

    public function test_the_warning_list_is_shown_next_to_a_confirmation_checkbox(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $planId = $this->makeWeekPlan($tech, $week);
        $this->makePlanItem($tech, $planId, [
            'plan_date' => $week->toDateString(),
            'day_part' => 'custom',
            'start_time' => '08:00',
            'end_time' => '11:00',
        ]);

        $this->actingAs($manager)
            ->from(route('technical.manager.board', ['week' => $week->toDateString()]))
            ->post(route('technical.manager.plan.store'), [
                'user_id' => $tech->id,
                'week' => $week->toDateString(),
                'mode' => 'assign',
                'tp_form' => 'assign',
                'reason' => 'Ghép thêm việc vào khung giờ đang bận',
                'items' => [$this->row([
                    'plan_date' => $week->toDateString(),
                    'day_part' => 'custom',
                    'start_time' => '10:00',
                    'end_time' => '12:00',
                ])],
            ])->assertSessionHasErrors('confirm_warnings');

        $html = $this->actingAs($manager)
            ->get(route('technical.manager.board', ['week' => $week->toDateString()]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Tôi xác nhận vẫn giao dù có cảnh báo', $html);
        $this->assertStringContainsString('trùng khung giờ', $html);
    }

    /*
    |--------------------------------------------------------------------------
    | 14. Lịch sử trước / sau
    |--------------------------------------------------------------------------
    */

    public function test_every_saved_row_writes_a_history_entry_with_the_reason(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $before = DB::table('technical_plan_histories')->count();

        $this->actingAs($manager)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Ghi nhật ký đầy đủ cho hai dòng',
            'confirm_warnings' => 1,
            'items' => [
                $this->row(['plan_date' => $week->toDateString(), 'title' => 'Dòng một']),
                $this->row(['plan_date' => $week->copy()->addDay()->toDateString(), 'title' => 'Dòng hai']),
            ],
        ])->assertRedirect();

        $this->assertGreaterThanOrEqual($before + 2, DB::table('technical_plan_histories')->count());

        $history = DB::table('technical_plan_histories')
            ->where('target_user_id', $tech->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('Ghi nhật ký đầy đủ cho hai dòng', (string) $history->reason);
        // Ảnh chụp "sau" luôn được ghi; "trước" là null vì đây là dòng mới tạo.
        $this->assertNotNull($history->changes_after);
        $this->assertStringContainsString('Dòng hai', (string) $history->changes_after);
    }

    /*
    |--------------------------------------------------------------------------
    | 15–17. Không đụng dữ liệu nguồn / báo cáo / KPI
    |--------------------------------------------------------------------------
    */

    public function test_creating_a_plan_never_touches_the_five_source_tables(): void
    {
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $before = $this->countSourceTables();

        $this->actingAs($this->technicalManager())->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Không được nhân bản dữ liệu nguồn',
            'confirm_warnings' => 1,
            'items' => [$this->row(['plan_date' => $week->toDateString()])],
        ])->assertRedirect();

        $this->assertSame($before, $this->countSourceTables());
    }

    public function test_the_new_flow_does_not_disturb_daily_reports_or_kpis(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $before = [
            'technical_daily_reports' => DB::table('technical_daily_reports')->count(),
            'technical_daily_report_files' => DB::table('technical_daily_report_files')->count(),
            'technical_daily_report_histories' => DB::table('technical_daily_report_histories')->count(),
        ];

        $this->actingAs($manager)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Không ảnh hưởng luồng báo cáo',
            'confirm_warnings' => 1,
            'items' => [$this->row(['plan_date' => $week->toDateString()])],
        ])->assertRedirect();

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table.' không được thay đổi.');
        }

        // Hai tab báo cáo ngày/tuần vẫn mở bình thường.
        $this->actingAs($manager)->get(route('technical.daily-reports.index'))->assertOk();
        $this->actingAs($manager)->get(route('technical.daily-reports.index', ['tab' => 'weekly-summary']))->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | 18. Company scope
    |--------------------------------------------------------------------------
    */

    public function test_a_member_outside_the_managed_scope_cannot_be_targeted(): void
    {
        $outsider = $this->outsider();
        $week = $this->currentWeekStart();

        $this->actingAs($this->technicalManager())
            ->post(route('technical.manager.plan.store'), [
                'user_id' => $outsider->id,
                'week' => $week->toDateString(),
                'mode' => 'assign',
                'reason' => 'Người ngoài phạm vi quản lý',
                'confirm_warnings' => 1,
                'items' => [$this->row(['plan_date' => $week->toDateString()])],
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->countPlanRows($outsider->id));

        $this->actingAs($this->technicalManager())
            ->get(route('technical.manager.work-items', ['user_id' => $outsider->id]))
            ->assertForbidden();
    }

    public function test_the_member_dropdown_only_lists_technical_staff(): void
    {
        $outsider = $this->outsider();

        $this->actingAs($this->technicalManager())
            ->get(route('technical.manager.board'))
            ->assertOk()
            ->assertDontSee($outsider->name);
    }

    /*
    |--------------------------------------------------------------------------
    | 19. Không redirect loop
    |--------------------------------------------------------------------------
    */

    public function test_the_board_and_its_deep_links_never_redirect(): void
    {
        $manager = $this->technicalManager();

        foreach ([[], ['open' => 'create'], ['open' => 'assign'], ['focus' => 'assign']] as $query) {
            $response = $this->actingAs($manager)->get(route('technical.manager.board', $query));

            $response->assertOk();
            $this->assertFalse($response->isRedirect());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Nhiều dòng: all-or-nothing, giới hạn, ngày ngoài tuần
    |--------------------------------------------------------------------------
    */

    public function test_one_bad_row_rolls_back_every_row(): void
    {
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $before = $this->countPlanRows($tech->id);

        $this->actingAs($this->technicalManager())->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Một dòng sai thì không dòng nào được ghi',
            'confirm_warnings' => 1,
            'items' => [
                $this->row(['plan_date' => $week->toDateString(), 'title' => 'Dòng hợp lệ']),
                $this->row(['plan_date' => $week->toDateString(), 'title' => 'X']), // title quá ngắn
            ],
        ])->assertSessionHasErrors('items.1.title');

        $this->assertSame($before, $this->countPlanRows($tech->id));
    }

    public function test_a_date_outside_the_week_is_rejected_without_writing_anything(): void
    {
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $before = $this->countPlanRows($tech->id);

        $this->actingAs($this->technicalManager())->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Ngày nằm ngoài tuần đang lập',
            'confirm_warnings' => 1,
            'items' => [
                $this->row(['plan_date' => $week->toDateString(), 'title' => 'Dòng trong tuần']),
                $this->row(['plan_date' => $week->copy()->addDays(20)->toDateString(), 'title' => 'Dòng ngoài tuần']),
            ],
        ])->assertSessionHasErrors('plan_date');

        $this->assertSame($before, $this->countPlanRows($tech->id));
    }

    public function test_too_many_rows_are_rejected(): void
    {
        $tech = $this->technician();
        $week = $this->currentWeekStart();
        $limit = (int) config('technical.week_plan.max_items_per_day', 12) * 7;

        $rows = [];

        for ($i = 0; $i <= $limit; $i++) {
            $rows[] = $this->row(['plan_date' => $week->toDateString()]);
        }

        $this->actingAs($this->technicalManager())->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Vượt giới hạn số dòng một lần lưu',
            'confirm_warnings' => 1,
            'items' => $rows,
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, $this->countPlanRows($tech->id));
    }

    public function test_an_empty_row_set_is_rejected(): void
    {
        $tech = $this->technician();

        $this->actingAs($this->technicalManager())->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $this->currentWeekStart()->toDateString(),
            'mode' => 'assign',
            'reason' => 'Không có dòng công việc nào',
            'items' => [],
        ])->assertSessionHasErrors('items');
    }

    /*
    |--------------------------------------------------------------------------
    | Lưu nháp vs Lưu và giao
    |--------------------------------------------------------------------------
    */

    public function test_draft_mode_saves_rows_without_marking_them_assigned(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $response = $this->actingAs($manager)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'draft',
            'reason' => 'Soạn trước kế hoạch, chưa giao',
            'confirm_warnings' => 1,
            'items' => [$this->row(['plan_date' => $week->toDateString(), 'title' => 'Việc soạn nháp'])],
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('Đã lưu nháp kế hoạch cho', (string) session('success'));

        $item = TechnicalPlanItem::query()->where('user_id', $tech->id)->latest('id')->first();

        $this->assertNotNull($item);
        $this->assertSame(0, (int) $item->is_manager_assigned);
        $this->assertNull($item->assigned_by);
        // Người tạo vẫn là quản lý — không giả mạo tác giả.
        $this->assertSame((int) $manager->id, (int) $item->created_by);

        // Tuần KHÔNG bị đẩy sang "Đã được Trưởng phòng điều chỉnh".
        $plan = TechnicalWeekPlan::query()
            ->where('user_id', $tech->id)
            ->whereDate('week_start', $week->toDateString())
            ->first();

        $this->assertNotNull($plan);
        $this->assertNotSame(TechnicalWeekPlan::STATUS_ADJUSTED, $plan->status);
    }

    public function test_assign_mode_marks_the_week_as_adjusted_by_the_manager(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();
        $week = $this->currentWeekStart();

        $this->actingAs($manager)->post(route('technical.manager.plan.store'), [
            'user_id' => $tech->id,
            'week' => $week->toDateString(),
            'mode' => 'assign',
            'reason' => 'Giao kế hoạch chính thức',
            'confirm_warnings' => 1,
            'items' => [$this->row(['plan_date' => $week->toDateString(), 'title' => 'Việc đã giao'])],
        ])->assertRedirect();

        $plan = TechnicalWeekPlan::query()
            ->where('user_id', $tech->id)
            ->whereDate('week_start', $week->toDateString())
            ->first();

        $this->assertNotNull($plan);
        $this->assertSame(TechnicalWeekPlan::STATUS_ADJUSTED, $plan->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Endpoint JSON đầu việc
    |--------------------------------------------------------------------------
    */

    public function test_work_items_endpoint_returns_only_items_assigned_to_that_member(): void
    {
        $manager = $this->technicalManager();
        $mine = $this->technician();
        $other = $this->technician();

        $siteId = $this->makeSite();
        $taskId = $this->makeTask($siteId, $mine->id);
        $otherTaskId = $this->makeTask($siteId, $other->id);

        $response = $this->actingAs($manager)
            ->get(route('technical.manager.work-items', ['user_id' => $mine->id]));

        $response->assertOk();

        $ids = collect($response->json('items'))->pluck('source_id')->map(fn ($v): int => (int) $v);

        $this->assertTrue($ids->contains($taskId));
        $this->assertFalse($ids->contains($otherTaskId));
    }

    /*
    |--------------------------------------------------------------------------
    | Trợ giúp
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'plan_date' => $this->currentWeekStart()->toDateString(),
            'day_part' => 'full_day',
            'title' => 'Công việc kế hoạch tuần',
            'objective' => 'Hoàn thành đúng tiến độ',
            'note' => '',
            'estimated_minutes' => 120,
            'priority' => 'normal',
            'source_type' => TechnicalPlanItem::SOURCE_MANAGER_ASSIGNED,
            'source_id' => '',
        ], $overrides);
    }

    private function countPlanRows(int $userId): int
    {
        return (int) DB::table('technical_plan_items')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->count();
    }

    /** @return array<string, int> */
    private function countSourceTables(): array
    {
        $counts = [];

        foreach (self::SOURCE_TABLES as $table) {
            $counts[$table] = (int) DB::table($table)->count();
        }

        return $counts;
    }

    /** Thu gọn thẻ mở của một offcanvas về một dòng để regex không vướng xuống dòng. */
    private function normaliseDrawerTag(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');

        if ($start === false) {
            return $html;
        }

        $open = strrpos(substr($html, 0, $start), '<div');

        return (string) preg_replace('/\s+/', ' ', substr($html, (int) $open, $start - (int) $open + 200));
    }

    private function currentWeekStartCarbon(): Carbon
    {
        return $this->currentWeekStart();
    }
}
