<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Models\AttendanceCorrectionAttachment;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Hồi quy phân quyền chức năng Yêu cầu sửa chấm công (/nhan-su/cham-cong/yeu-cau-sua).
 *
 * Nhân viên không có quyền trang `page.hr` (Sales, Kỹ thuật...) vẫn phải tự phục vụ
 * được: xem, gửi, hủy đơn và tải file của chính mình. Duyệt/từ chối chỉ dành cho role `hr`.
 *
 * Chạy trên DB egosolar_test, rollback sau từng test nhờ DatabaseTransactions.
 */
final class AttendanceCorrectionAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE_URL = '/nhan-su/cham-cong/yeu-cau-sua';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * Tạo user có role bật kiểm soát quyền trang, giống cấu hình production:
     * `sales`/`ky_thuat` không có `page.hr`, `hr` có `page.hr`.
     *
     * @param  list<string>  $pagePermissions
     */
    private function userWithPageControlledRole(string $roleName, array $pagePermissions): User
    {
        $role = Role::findOrCreate($roleName, 'web');

        if (Schema::hasColumn('roles', 'page_access_enabled')) {
            DB::table('roles')->where('id', $role->id)->update(['page_access_enabled' => 1]);
        }

        $role->syncPermissions(array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            $pagePermissions
        ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function salesUser(): User
    {
        $user = $this->userWithPageControlledRole('sales', ['page.dashboard', 'page.sales']);

        // Tiền đề của lỗi gốc: user này thật sự không có quyền trang Nhân sự.
        $this->assertFalse(app(PageAccessServiceInterface::class)->canAccess($user, 'page.hr'));

        return $user;
    }

    private function technicalUser(): User
    {
        $user = $this->userWithPageControlledRole('ky_thuat', ['page.dashboard', 'page.technical']);

        $this->assertFalse(app(PageAccessServiceInterface::class)->canAccess($user, 'page.hr'));

        return $user;
    }

    private function hrUser(): User
    {
        return $this->userWithPageControlledRole('hr', ['page.dashboard', 'page.hr']);
    }

    private function makeRecord(User $user, int $daysAgo = 3, string $checkIn = '09:15', ?string $checkOut = '17:00'): AttendanceRecord
    {
        $date = now()->subDays($daysAgo)->toDateString();

        return AttendanceRecord::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'check_in_at' => $date.' '.$checkIn.':00',
            'check_out_at' => $checkOut ? $date.' '.$checkOut.':00' : null,
            'late_minutes' => 45,
            'early_leave_minutes' => 0,
            'work_minutes' => 0,
            'status' => 'late',
        ]);
    }

    private function makePendingCorrection(User $user, ?AttendanceRecord $record = null): AttendanceCorrectionRequest
    {
        $record ??= $this->makeRecord($user);

        return AttendanceCorrectionRequest::create([
            'attendance_record_id' => $record->id,
            'user_id' => $user->id,
            'work_date' => $record->work_date->toDateString(),
            'original_check_in_at' => $record->check_in_at,
            'original_check_out_at' => $record->check_out_at,
            'requested_check_in_at' => $record->work_date->toDateString().' 08:25:00',
            'requested_check_out_at' => $record->work_date->toDateString().' 18:00:00',
            'reason' => 'Quên bấm chấm công',
            'status' => 'pending',
            'pending_guard' => 1,
        ]);
    }

    private function makeAttachment(AttendanceCorrectionRequest $correction): AttendanceCorrectionAttachment
    {
        $path = 'private/attendance-corrections/'.$correction->id.'/giai-trinh.pdf';
        Storage::disk('local')->put($path, 'noi dung giai trinh');

        return AttendanceCorrectionAttachment::create([
            'attendance_correction_request_id' => $correction->id,
            'uploaded_by' => $correction->user_id,
            'disk' => 'local',
            'original_name' => 'giai-trinh.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 19,
        ]);
    }

    /* =====================================================================
     * 1-4. Nhân viên không có page.hr tự phục vụ được
     * ===================================================================== */

    public function test_sales_without_page_hr_can_open_correction_page(): void
    {
        $this->actingAs($this->salesUser())
            ->get(self::BASE_URL)
            ->assertOk();
    }

    public function test_technical_without_page_hr_can_open_correction_page(): void
    {
        $this->actingAs($this->technicalUser())
            ->get(self::BASE_URL)
            ->assertOk();
    }

    public function test_sales_can_submit_correction_for_own_record(): void
    {
        $sales = $this->salesUser();
        $record = $this->makeRecord($sales);

        $this->actingAs($sales)
            ->post(self::BASE_URL, [
                'attendance_record_id' => $record->id,
                'requested_check_in_time' => '08:25',
                'requested_check_out_time' => '18:00',
                'reason' => 'Quên bấm chấm công',
                'attachments' => [UploadedFile::fake()->create('giai-trinh.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect(route('hr.attendance-corrections.index', ['tab' => 'mine']))
            ->assertSessionHasNoErrors();

        $correction = AttendanceCorrectionRequest::where('attendance_record_id', $record->id)->first();
        $this->assertNotNull($correction);
        $this->assertSame('pending', $correction->status);
        $this->assertSame($sales->id, (int) $correction->user_id);
        $this->assertCount(1, $correction->attachments);
    }

    public function test_sales_can_cancel_own_pending_correction(): void
    {
        $sales = $this->salesUser();
        $correction = $this->makePendingCorrection($sales);

        $this->actingAs($sales)
            ->post(self::BASE_URL.'/'.$correction->id.'/cancel')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $correction->fresh()->status);
    }

    public function test_sales_cannot_cancel_own_correction_once_processed(): void
    {
        $sales = $this->salesUser();
        $correction = $this->makePendingCorrection($sales);
        $correction->update(['status' => 'approved', 'pending_guard' => null]);

        $this->actingAs($sales)
            ->post(self::BASE_URL.'/'.$correction->id.'/cancel')
            ->assertSessionHasErrors('status');

        $this->assertSame('approved', $correction->fresh()->status);
    }

    public function test_sales_can_download_own_attachment(): void
    {
        $sales = $this->salesUser();
        $correction = $this->makePendingCorrection($sales);
        $attachment = $this->makeAttachment($correction);

        $this->actingAs($sales)
            ->get(self::BASE_URL.'/'.$correction->id.'/attachments/'.$attachment->id.'/download')
            ->assertOk()
            ->assertDownload('giai-trinh.pdf');
    }

    /* =====================================================================
     * 5. Không đụng được dữ liệu của người khác
     * ===================================================================== */

    public function test_sales_cannot_submit_correction_for_another_users_record(): void
    {
        $sales = $this->salesUser();
        $otherRecord = $this->makeRecord($this->technicalUser());

        $this->actingAs($sales)
            ->post(self::BASE_URL, [
                'attendance_record_id' => $otherRecord->id,
                'requested_check_in_time' => '08:25',
                'reason' => 'Sửa hộ',
            ])
            ->assertForbidden();

        $this->assertFalse(AttendanceCorrectionRequest::where('attendance_record_id', $otherRecord->id)->exists());
    }

    public function test_sales_cannot_cancel_another_users_correction(): void
    {
        $sales = $this->salesUser();
        $correction = $this->makePendingCorrection($this->technicalUser());

        $this->actingAs($sales)
            ->post(self::BASE_URL.'/'.$correction->id.'/cancel')
            ->assertForbidden();

        $this->assertSame('pending', $correction->fresh()->status);
    }

    public function test_sales_cannot_download_another_users_attachment(): void
    {
        $sales = $this->salesUser();
        $correction = $this->makePendingCorrection($this->technicalUser());
        $attachment = $this->makeAttachment($correction);

        $this->actingAs($sales)
            ->get(self::BASE_URL.'/'.$correction->id.'/attachments/'.$attachment->id.'/download')
            ->assertForbidden();
    }

    public function test_sales_cannot_see_other_users_corrections_on_index(): void
    {
        $sales = $this->salesUser();
        $other = $this->makePendingCorrection($this->technicalUser());

        $this->actingAs($sales)
            ->get(self::BASE_URL.'?tab=approval&user_id='.$other->user_id)
            ->assertOk()
            ->assertViewHas('tab', 'mine')
            ->assertViewHas('canReview', false)
            ->assertViewHas('corrections', fn ($corrections) => $corrections->every(
                fn ($item) => (int) $item->user_id === (int) $sales->id
            ));
    }

    /* =====================================================================
     * 6. Không gọi được approve/reject
     * ===================================================================== */

    public function test_sales_cannot_approve_or_reject_correction(): void
    {
        $sales = $this->salesUser();
        $correction = $this->makePendingCorrection($this->technicalUser());

        $this->actingAs($sales)
            ->post(self::BASE_URL.'/'.$correction->id.'/approve', ['approved_check_in_time' => '08:25'])
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(self::BASE_URL.'/'.$correction->id.'/reject', ['review_note' => 'Không hợp lệ'])
            ->assertForbidden();

        $this->assertSame('pending', $correction->fresh()->status);
    }

    public function test_sales_cannot_approve_or_reject_own_correction(): void
    {
        $sales = $this->salesUser();
        $correction = $this->makePendingCorrection($sales);

        $this->actingAs($sales)
            ->post(self::BASE_URL.'/'.$correction->id.'/approve', ['approved_check_in_time' => '08:25'])
            ->assertForbidden();

        $this->actingAs($sales)
            ->post(self::BASE_URL.'/'.$correction->id.'/reject', ['review_note' => 'Tự từ chối'])
            ->assertForbidden();

        $this->assertSame('pending', $correction->fresh()->status);
    }

    public function test_admin_without_hr_role_cannot_approve_or_reject(): void
    {
        $admin = $this->userWithRole('admin');
        $correction = $this->makePendingCorrection($this->salesUser());

        $this->actingAs($admin)
            ->post(self::BASE_URL.'/'.$correction->id.'/approve', ['approved_check_in_time' => '08:25'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(self::BASE_URL.'/'.$correction->id.'/reject', ['review_note' => 'Không hợp lệ'])
            ->assertForbidden();

        $this->assertSame('pending', $correction->fresh()->status);
    }

    /* =====================================================================
     * 7. Khách chưa đăng nhập
     * ===================================================================== */

    public function test_guest_is_redirected_to_login(): void
    {
        $correction = $this->makePendingCorrection($this->salesUser());
        $attachment = $this->makeAttachment($correction);

        $this->get(self::BASE_URL)->assertRedirect(route('login'));
        $this->post(self::BASE_URL, [])->assertRedirect(route('login'));
        $this->post(self::BASE_URL.'/'.$correction->id.'/cancel')->assertRedirect(route('login'));
        $this->get(self::BASE_URL.'/'.$correction->id.'/attachments/'.$attachment->id.'/download')
            ->assertRedirect(route('login'));
        $this->post(self::BASE_URL.'/'.$correction->id.'/approve')->assertRedirect(route('login'));
        $this->post(self::BASE_URL.'/'.$correction->id.'/reject')->assertRedirect(route('login'));

        $this->assertSame('pending', $correction->fresh()->status);
    }

    /* =====================================================================
     * 8. HR vẫn duyệt / từ chối như trước
     * ===================================================================== */

    public function test_hr_can_approve_and_recalculate_attendance(): void
    {
        $hr = $this->hrUser();
        $sales = $this->salesUser();
        $record = $this->makeRecord($sales);
        $correction = $this->makePendingCorrection($sales, $record);

        $this->actingAs($hr)
            ->post(self::BASE_URL.'/'.$correction->id.'/approve', [
                'approved_check_in_time' => '08:20',
                'approved_check_out_time' => '18:00',
                'review_note' => 'Đã xác minh',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $correction->refresh();
        $record->refresh();

        $this->assertSame('approved', $correction->status);
        $this->assertSame($hr->id, (int) $correction->reviewed_by);
        $this->assertSame('08:20', $record->check_in_at->format('H:i'));
        $this->assertSame('18:00', $record->check_out_at->format('H:i'));
        $this->assertSame(0, (int) $record->late_minutes);
        $this->assertSame(580, (int) $record->work_minutes);
    }

    public function test_hr_can_reject_with_reason(): void
    {
        $hr = $this->hrUser();
        $correction = $this->makePendingCorrection($this->salesUser());

        $this->actingAs($hr)
            ->post(self::BASE_URL.'/'.$correction->id.'/reject', ['review_note' => 'Không có minh chứng'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $correction->refresh();
        $this->assertSame('rejected', $correction->status);
        $this->assertSame('Không có minh chứng', $correction->review_note);
    }

    public function test_hr_can_download_attachment_of_employee(): void
    {
        $hr = $this->hrUser();
        $correction = $this->makePendingCorrection($this->salesUser());
        $attachment = $this->makeAttachment($correction);

        $this->actingAs($hr)
            ->get(self::BASE_URL.'/'.$correction->id.'/attachments/'.$attachment->id.'/download')
            ->assertOk();
    }

    public function test_hr_cannot_approve_or_reject_own_correction(): void
    {
        $hr = $this->hrUser();
        $correction = $this->makePendingCorrection($hr);

        $this->actingAs($hr)
            ->post(self::BASE_URL.'/'.$correction->id.'/approve', ['approved_check_in_time' => '08:25'])
            ->assertForbidden();

        $this->actingAs($hr)
            ->post(self::BASE_URL.'/'.$correction->id.'/reject', ['review_note' => 'Tự từ chối'])
            ->assertForbidden();

        $this->assertSame('pending', $correction->fresh()->status);
    }
}
