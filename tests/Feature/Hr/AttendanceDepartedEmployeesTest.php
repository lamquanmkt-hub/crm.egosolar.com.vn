<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tài khoản đã xóa / khóa (is_active = 0) vẫn có công trong tháng đang xem:
 * hiện ở cuối Bảng công, Excel, PDF với nhãn "Đã nghỉ" (bỏ tiền tố "[Đã xóa]");
 * tháng không có công thì không hiện; thẻ tổng quan chỉ tính người đang hoạt động.
 */
final class AttendanceDepartedEmployeesTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-05';

    private const OTHER_MONTH = '2026-08';

    private function hr(): User
    {
        $role = Role::findOrCreate('hr', 'web');
        if (Schema::hasColumn('roles', 'page_access_enabled')) {
            DB::table('roles')->where('id', $role->id)->update(['page_access_enabled' => 1]);
        }
        $role->syncPermissions([Permission::findOrCreate('page.dashboard', 'web'), Permission::findOrCreate('page.hr', 'web')]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['is_active' => 1, 'name' => 'ZZ Nhan su HR']);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function employee(string $name, bool $active): User
    {
        return User::factory()->create(['name' => $name, 'is_active' => $active ? 1 : 0]);
    }

    private function work(User $user, string $date, string $status = 'completed', int $minutes = 480): void
    {
        AttendanceRecord::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'check_in_at' => $date.' 08:00:00',
            'check_out_at' => $date.' 17:00:00',
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'work_minutes' => $minutes,
            'status' => $status,
        ]);
    }

    public function test_departed_employee_with_attendance_in_month_shows_at_bottom_with_label(): void
    {
        $hr = $this->hr();
        $active = $this->employee('AA Nhan vien dang lam', true);
        $departed = $this->employee('[Đã xóa] BB Nhan vien nghi', false);
        $this->work($active, '2026-05-05');
        $this->work($departed, '2026-05-06');
        $this->work($departed, '2026-05-07');

        $html = $this->actingAs($hr)->get('/nhan-su/cham-cong?month='.self::MONTH)->assertOk()->getContent();

        $this->assertStringContainsString('BB Nhan vien nghi', $html);
        $this->assertStringNotContainsString('[Đã xóa] BB', $html);
        $this->assertStringContainsString('Đã nghỉ', $html);
        // Người đang làm đứng trước người đã nghỉ trong bảng thống kê.
        $this->assertLessThan(strpos($html, 'BB Nhan vien nghi'), strpos($html, 'AA Nhan vien dang lam'));
    }

    public function test_departed_employee_is_hidden_in_months_without_attendance(): void
    {
        $hr = $this->hr();
        $departed = $this->employee('[Đã xóa] CC Khong co cong thang 8', false);
        $this->work($departed, '2026-05-06');

        $this->actingAs($hr)->get('/nhan-su/cham-cong?month='.self::OTHER_MONTH)->assertOk()->assertDontSee('CC Khong co cong thang 8');
        $this->actingAs($hr)->get('/nhan-su/cham-cong?month='.self::MONTH)->assertOk()->assertSee('CC Khong co cong thang 8');
    }

    public function test_departed_employee_with_only_an_empty_placeholder_record_is_not_shown(): void
    {
        $hr = $this->hr();
        $departed = $this->employee('[Đã xóa] HH Chi co dong trong', false);
        // Dòng công trống (vắng, không check-in) như dòng sinh ra khi duyệt tăng ca: chưa chấm công thật.
        AttendanceRecord::create([
            'user_id' => $departed->id, 'work_date' => '2026-05-12', 'late_minutes' => 0,
            'early_leave_minutes' => 0, 'work_minutes' => 0, 'status' => 'absent',
        ]);

        $this->actingAs($hr)->get('/nhan-su/cham-cong?month='.self::MONTH)->assertOk()->assertDontSee('HH Chi co dong trong');
    }

    public function test_summary_cards_count_only_active_employees(): void
    {
        $hr = $this->hr();
        $departed = $this->employee('[Đã xóa] DD Da nghi', false);
        $this->work($departed, '2026-05-06');
        $this->work($departed, '2026-05-07');

        $activeCount = User::where('is_active', 1)->count();
        $with = $this->actingAs($hr)->get('/nhan-su/cham-cong?month='.self::MONTH);
        $with->assertOk();
        $this->assertSame($activeCount, $with->viewData('summary')['employees']);
        $this->assertSame(0, $with->viewData('summary')['valid_days'] - $with->viewData('employeeStats')->where('departed', false)->sum('valid_days'));

        $row = $with->viewData('employeeStats')->firstWhere('user_id', $departed->id);
        $this->assertNotNull($row);
        $this->assertTrue($row->departed);
        $this->assertSame(2, $row->valid_days);
        $this->assertSame('Đã nghỉ', $row->rank_label);
    }

    public function test_user_filter_can_select_departed_employee_for_the_month(): void
    {
        $hr = $this->hr();
        $departed = $this->employee('[Đã xóa] EE Loc theo nguoi', false);
        $this->work($departed, '2026-05-08');

        $res = $this->actingAs($hr)->get('/nhan-su/cham-cong?month='.self::MONTH.'&user_id='.$departed->id);
        $res->assertOk();
        $this->assertCount(1, $res->viewData('employeeStats'));
        $this->assertSame(1, $res->viewData('employeeStats')->first()->valid_days);
    }

    public function test_excel_export_has_a_sheet_for_departed_employee_with_label(): void
    {
        $hr = $this->hr();
        $departed = $this->employee('[Đã xóa] FF Excel nghi', false);
        $this->work($departed, '2026-05-06');

        $response = $this->actingAs($hr)->get('/nhan-su/cham-cong/export-excel?month='.self::MONTH.'&user_id='.$departed->id)->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'att').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());

        $book = IOFactory::load($tmp);
        @unlink($tmp);
        $titles = $book->getSheetNames();
        $this->assertTrue(collect($titles)->contains(fn ($t) => str_contains($t, 'FF Excel nghi')), 'Thiếu sheet của người đã nghỉ: '.implode(' | ', $titles));
        $summary = $book->getSheet(0)->toArray();
        $this->assertStringContainsString('FF Excel nghi (Đã nghỉ)', json_encode($summary, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('[Đã xóa]', json_encode($summary, JSON_UNESCAPED_UNICODE));
    }

    public function test_pdf_export_succeeds_with_departed_employee(): void
    {
        $hr = $this->hr();
        $departed = $this->employee('[Đã xóa] GG Pdf nghi', false);
        $this->work($departed, '2026-05-06');

        $res = $this->actingAs($hr)->get('/nhan-su/cham-cong/export-pdf?month='.self::MONTH.'&user_id='.$departed->id)->assertOk();
        $this->assertStringContainsString('pdf', (string) $res->headers->get('content-type'));
    }

    public function test_user_helpers_strip_prefix_and_flag_departed(): void
    {
        $gone = new User(['name' => '[Đã xóa] Thu Thảo', 'is_active' => 0]);
        $this->assertTrue($gone->is_departed);
        $this->assertSame('Thu Thảo', $gone->attendance_name);
        $this->assertSame('Thu Thảo (Đã nghỉ)', $gone->attendance_label);

        $active = new User(['name' => 'Nguyễn Văn A', 'is_active' => 1]);
        $this->assertFalse($active->is_departed);
        $this->assertSame('Nguyễn Văn A', $active->attendance_label);
    }
}
