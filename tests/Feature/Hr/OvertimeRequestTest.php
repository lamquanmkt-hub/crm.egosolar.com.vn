<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Models\AttendanceRecord;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Đăng ký tăng ca (tự phục vụ) gắn vào Chấm công:
 * - Nhân viên không có quyền trang Nhân sự vẫn đăng ký / xem đơn của mình (không còn 403).
 * - Người duyệt bắt buộc, chỉ nhóm quản lý / trưởng phòng / HR / admin, không phải chính mình.
 * - Không ai tự duyệt đơn của mình; chỉ duyệt / từ chối đơn đang chờ; chặn đơn trùng khung giờ.
 * - Giờ tăng ca đã duyệt ghi vào chấm công và cộng vào Tổng giờ công ở "Chấm công của tôi".
 */
final class OvertimeRequestTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = '/nhan-su/tang-ca';

    private const DATE = '2026-09-15';

    /** @param list<string> $pagePermissions */
    private function userWithPageControlledRole(string $roleName, array $pagePermissions = ['page.dashboard']): User
    {
        $role = Role::findOrCreate($roleName, 'web');

        if (Schema::hasColumn('roles', 'page_access_enabled')) {
            DB::table('roles')->where('id', $role->id)->update(['page_access_enabled' => 1]);
        }

        $role->syncPermissions(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $pagePermissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['is_active' => 1]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function staff(): User
    {
        $user = $this->userWithPageControlledRole('sales', ['page.dashboard', 'page.sales']);
        $this->assertFalse(app(PageAccessServiceInterface::class)->canAccess($user, 'page.hr'));

        return $user;
    }

    private function manager(): User
    {
        return $this->userWithPageControlledRole('technical_manager', ['page.dashboard', 'page.technical']);
    }

    private function hr(): User
    {
        return $this->userWithPageControlledRole('hr', ['page.dashboard', 'page.hr']);
    }

    private function submit(User $as, array $overrides = [])
    {
        return $this->actingAs($as)->post(self::BASE, $overrides + [
            'overtime_date' => self::DATE,
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Xử lý đơn hàng gấp',
        ]);
    }

    public function test_staff_without_hr_page_access_can_open_and_submit_overtime(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();

        $this->actingAs($staff)->get(self::BASE)->assertOk()->assertSee('Tăng ca của tôi');
        $this->actingAs($staff)->get(self::BASE.'/create')->assertOk()->assertSee($manager->name);

        $this->submit($staff, ['approver_id' => $manager->id])->assertRedirect()->assertSessionHas('success');

        $ot = OvertimeRequest::where('user_id', $staff->id)->firstOrFail();
        $this->assertSame('pending', $ot->status);
        $this->assertSame($manager->id, (int) $ot->approver_id);
        $this->assertEqualsWithDelta(2.0, (float) $ot->hours, 0.001);
    }

    public function test_approver_is_required_must_be_a_manager_and_not_yourself(): void
    {
        $staff = $this->staff();
        $otherStaff = $this->staff();
        $manager = $this->manager();

        $html = $this->actingAs($manager)->get(self::BASE.'/create')->assertOk()->getContent();
        $this->assertStringNotContainsString('value="'.$manager->id.'"', $html);   // không tự chọn mình
        $this->assertStringNotContainsString('value="'.$otherStaff->id.'"', $html); // nhân viên thường không phải người duyệt

        $this->submit($staff)->assertSessionHasErrors('approver_id');
        $this->submit($staff, ['approver_id' => $otherStaff->id])->assertSessionHasErrors('approver_id');
        $this->submit($manager, ['approver_id' => $manager->id])->assertSessionHasErrors('approver_id');

        $this->assertSame(0, OvertimeRequest::whereIn('user_id', [$staff->id, $manager->id])->count());
    }

    public function test_overlapping_request_is_blocked_but_overnight_is_allowed(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();

        $this->submit($staff, ['approver_id' => $manager->id])->assertSessionHas('success');
        $this->submit($staff, ['approver_id' => $manager->id, 'start_time' => '19:00', 'end_time' => '21:00'])->assertSessionHas('error');

        // Qua đêm 22:00 → 02:00 hôm sau, không trùng.
        $this->submit($staff, ['approver_id' => $manager->id, 'start_time' => '22:00', 'end_time' => '02:00'])->assertSessionHas('success');
        $night = OvertimeRequest::where('user_id', $staff->id)->orderByDesc('id')->first();
        $this->assertEqualsWithDelta(4.0, (float) $night->hours, 0.001);

        // Quá 16 giờ.
        $this->submit($staff, ['approver_id' => $manager->id, 'overtime_date' => '2026-09-20', 'start_time' => '06:00', 'end_time' => '23:30'])->assertSessionHas('error');

        $this->assertSame(2, OvertimeRequest::where('user_id', $staff->id)->count());
    }

    public function test_nobody_can_approve_their_own_request_even_hr(): void
    {
        $hr = $this->hr();
        $manager = $this->manager();

        $this->submit($hr, ['approver_id' => $manager->id])->assertSessionHas('success');
        $ot = OvertimeRequest::where('user_id', $hr->id)->firstOrFail();

        $this->actingAs($hr)->post(self::BASE.'/'.$ot->id.'/approve')->assertForbidden();
        $this->assertSame('pending', $ot->fresh()->status);
    }

    public function test_only_assigned_approver_or_hr_can_decide_others_requests(): void
    {
        $staff = $this->staff();
        $assigned = $this->manager();
        $otherManager = $this->manager();
        $hr = $this->hr();

        $this->submit($staff, ['approver_id' => $assigned->id]);
        $ot = OvertimeRequest::where('user_id', $staff->id)->firstOrFail();

        $this->actingAs($otherManager)->post(self::BASE.'/'.$ot->id.'/approve')->assertForbidden();
        $this->actingAs($this->staff())->post(self::BASE.'/'.$ot->id.'/reject')->assertForbidden();

        // HR duyệt được đơn của người khác.
        $this->actingAs($hr)->post(self::BASE.'/'.$ot->id.'/reject', ['approval_note' => 'Chưa cần'])->assertSessionHas('success');
        $this->assertSame('rejected', $ot->fresh()->status);
    }

    public function test_approval_writes_attendance_and_adds_hours_to_my_attendance_total(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();

        $this->submit($staff, ['approver_id' => $manager->id]);
        $ot = OvertimeRequest::where('user_id', $staff->id)->firstOrFail();

        // Người duyệt thấy đơn ở tab Cần duyệt.
        $this->actingAs($manager)->get(self::BASE.'?tab=approval&month=2026-09')->assertOk()->assertSee($staff->name)->assertSee('Duyệt');

        $this->actingAs($manager)->post(self::BASE.'/'.$ot->id.'/approve', ['approval_note' => 'OK'])->assertSessionHas('success');
        $this->assertSame('approved', $ot->fresh()->status);

        $record = AttendanceRecord::where('user_id', $staff->id)->whereDate('work_date', self::DATE)->firstOrFail();
        $this->assertStringContainsString('[Tăng ca #'.$ot->id.']', (string) $record->note);

        // Chấm công của tôi: nhãn tăng ca trong lịch sử + cộng vào Tổng giờ công.
        $html = $this->actingAs($staff)->get('/nhan-su/cham-cong-cua-toi?month=2026-09')->assertOk()->getContent();
        $this->assertStringContainsString('Tăng ca 2h', $html);
        $this->assertStringContainsString('Gồm 2 giờ tăng ca đã duyệt', $html);
        $this->assertMatchesRegularExpression('/<label>Tổng giờ công<\/label>\s*<strong>2,0<\/strong>/u', $html);
    }

    public function test_only_pending_requests_can_be_decided(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();

        $this->submit($staff, ['approver_id' => $manager->id]);
        $ot = OvertimeRequest::where('user_id', $staff->id)->firstOrFail();

        $this->actingAs($manager)->post(self::BASE.'/'.$ot->id.'/approve')->assertSessionHas('success');
        $this->actingAs($manager)->post(self::BASE.'/'.$ot->id.'/reject')->assertSessionHas('error');
        $this->actingAs($manager)->post(self::BASE.'/'.$ot->id.'/approve')->assertSessionHas('error');

        $this->assertSame('approved', $ot->fresh()->status);
    }

    public function test_accounting_without_hr_page_access_sees_and_can_decide_all_overtime(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $accountant = $this->userWithPageControlledRole('accounting', ['page.dashboard', 'page.finance', 'menu.dashboard', 'menu.finance']);
        $this->assertFalse(app(PageAccessServiceInterface::class)->canAccess($accountant, 'page.hr'));

        $this->submit($staff, ['approver_id' => $manager->id])->assertSessionHas('success');
        $ot = OvertimeRequest::where('user_id', $staff->id)->firstOrFail();

        // Kế toán vào tab Cần duyệt, thấy đơn giao cho người khác + bộ lọc nhân viên + nút duyệt.
        $html = $this->actingAs($accountant)
            ->get(self::BASE.'?tab=approval&month=2026-09&status=&user_id=')
            ->assertOk()
            ->assertSee('Duyệt đơn tăng ca')
            ->assertSee($staff->name)
            ->assertSee('Kế toán thấy mọi đơn')
            ->getContent();
        $this->assertStringContainsString('name="user_id"', $html);
        $this->assertStringContainsString(route('hr.overtime.approve', $ot), $html);

        // Menu Nhân sự cá nhân có link Tăng ca + Duyệt tăng ca.
        $this->assertStringContainsString('data-overtime-menu', $html);
        $this->assertStringContainsString('data-overtime-review-menu', $html);

        $this->actingAs($accountant)->post(self::BASE.'/'.$ot->id.'/approve', ['approval_note' => 'KT xác nhận'])->assertSessionHas('success');
        $this->assertSame('approved', $ot->fresh()->status);
        $this->assertSame($accountant->id, (int) $ot->fresh()->approved_by);
    }

    public function test_owner_can_edit_and_delete_pending_request(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $otherManager = $this->manager();

        $this->submit($staff, ['approver_id' => $manager->id]);
        $ot = OvertimeRequest::where('user_id', $staff->id)->firstOrFail();

        $this->actingAs($staff)->get(self::BASE.'?month=2026-09')->assertOk()
            ->assertSee('data-overtime-edit', false)->assertSee('data-overtime-delete', false);
        $this->actingAs($staff)->get(self::BASE.'/'.$ot->id.'/edit')->assertOk()
            ->assertSee('Sửa đơn tăng ca')->assertSee('Xử lý đơn hàng gấp');

        // Sửa giờ / người duyệt: không bị coi là trùng với chính đơn đang sửa.
        $this->actingAs($staff)->put(self::BASE.'/'.$ot->id, [
            'overtime_date' => self::DATE,
            'start_time' => '18:30',
            'end_time' => '21:30',
            'approver_id' => $otherManager->id,
            'reason' => 'Đổi giờ tăng ca',
        ])->assertRedirect()->assertSessionHas('success');

        $ot->refresh();
        $this->assertSame('pending', $ot->status);
        $this->assertSame($otherManager->id, (int) $ot->approver_id);
        $this->assertEqualsWithDelta(3.0, (float) $ot->hours, 0.001);
        $this->assertSame('Đổi giờ tăng ca', $ot->reason);

        $this->actingAs($staff)->delete(self::BASE.'/'.$ot->id)->assertSessionHas('success');
        $this->assertNull(OvertimeRequest::find($ot->id));
    }

    public function test_cannot_edit_or_delete_others_or_processed_requests(): void
    {
        $staff = $this->staff();
        $manager = $this->manager();
        $hr = $this->hr();

        $this->submit($staff, ['approver_id' => $manager->id]);
        $ot = OvertimeRequest::where('user_id', $staff->id)->firstOrFail();

        // Người khác (kể cả HR / người duyệt) không sửa / xoá đơn của nhân viên.
        $this->actingAs($hr)->get(self::BASE.'/'.$ot->id.'/edit')->assertForbidden();
        $this->actingAs($manager)->delete(self::BASE.'/'.$ot->id)->assertForbidden();

        // Đã duyệt thì người gửi cũng không sửa / xoá được.
        $this->actingAs($manager)->post(self::BASE.'/'.$ot->id.'/approve');
        $this->actingAs($staff)->get(self::BASE.'?month=2026-09')->assertOk()->assertDontSee('data-overtime-edit', false);
        $this->actingAs($staff)->put(self::BASE.'/'.$ot->id, [
            'overtime_date' => self::DATE, 'start_time' => '18:00', 'end_time' => '23:00',
            'approver_id' => $manager->id, 'reason' => 'Sửa sau khi duyệt',
        ])->assertForbidden();
        $this->actingAs($staff)->delete(self::BASE.'/'.$ot->id)->assertForbidden();

        $this->assertSame('approved', $ot->fresh()->status);
        $this->assertEqualsWithDelta(2.0, (float) $ot->fresh()->hours, 0.001);
    }

    public function test_my_attendance_shows_overtime_buttons_by_role(): void
    {
        $staffHtml = $this->actingAs($this->staff())->get('/nhan-su/cham-cong-cua-toi')->assertOk()->getContent();
        $this->assertStringContainsString('data-overtime-link', $staffHtml);
        $this->assertStringNotContainsString('data-overtime-review-link', $staffHtml);

        $managerHtml = $this->actingAs($this->manager())->get('/nhan-su/cham-cong-cua-toi')->assertOk()->getContent();
        $this->assertStringContainsString('data-overtime-review-link', $managerHtml);
    }
}
