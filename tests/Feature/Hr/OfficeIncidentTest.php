<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Services\Hr\OfficeIncidents;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sự cố văn phòng theo quy trình 4 bước trong tài liệu KPI HCNS:
 * báo kèm ảnh → HCNS phân loại (hạn theo mức) → chi > 500.000đ chờ Giám đốc duyệt → xử lý → người báo nghiệm thu, chấm sao.
 */
final class OfficeIncidentTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private User $hr;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        DB::table('hr_payroll_settings')->where('setting_key', 'like', 'incident_%')->delete();
        $this->staff = $this->userWithRole('sales', ['name' => 'Nhân viên báo lỗi']);
        $this->hr = $this->userWithRole('hr');
        $this->director = $this->userWithRole('management');
    }

    private function report(): object
    {
        $this->actingAs($this->staff)->post(route('hr.incidents.store'), [
            'location' => 'Phòng họp tầng 2', 'description' => 'Điều hoà chảy nước',
            'files' => [UploadedFile::fake()->image('dieu-hoa.jpg')],
        ])->assertRedirect();

        return DB::table('hr_office_incidents')->where('reporter_id', $this->staff->id)->latest('id')->first();
    }

    public function test_full_flow_with_cost_approval_and_acceptance(): void
    {
        $incident = $this->report();
        $this->assertSame('new', $incident->status);
        $this->assertSame(1, DB::table('hr_office_incident_files')->where('incident_id', $incident->id)->where('stage', 'report')->count());

        // Nhân viên thường không phân loại được.
        $this->actingAs($this->staff)->post(route('hr.incidents.classify', $incident->id), ['level' => 2, 'handler_type' => 'vendor'])->assertForbidden();

        // HCNS phân loại Mức 2, thuê ngoài 1.200.000đ => chờ Giám đốc duyệt; hạn = lúc báo + 8 giờ.
        $this->actingAs($this->hr)->post(route('hr.incidents.classify', $incident->id), [
            'level' => 2, 'handler_type' => 'vendor', 'vendor_name' => 'Điện lạnh A', 'estimated_cost' => 1200000,
        ])->assertSessionHas('success');
        $incident = DB::table('hr_office_incidents')->find($incident->id);
        $this->assertSame('waiting_approval', $incident->status);
        $this->assertSame('pending', $incident->cost_approval);
        $this->assertSame(Carbon::parse($incident->created_at)->addHours(8)->toDateTimeString(), Carbon::parse($incident->due_at)->toDateTimeString());

        $this->actingAs($this->director)->get(route('hr.incidents.show', $incident->id))->assertOk()->assertSee('Duyệt chi phí sửa chữa');
        $this->actingAs($this->hr)->post(route('hr.incidents.approve-cost', $incident->id), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->director)->post(route('hr.incidents.approve-cost', $incident->id), ['decision' => 'approve'])->assertSessionHas('success');
        $this->assertSame('in_progress', DB::table('hr_office_incidents')->where('id', $incident->id)->value('status'));
        $this->actingAs($this->hr)->get(route('hr.incidents.show', $incident->id))->assertOk()->assertSee('Báo đã xử lý xong')->assertSee('Phân loại lại');

        $this->actingAs($this->hr)->post(route('hr.incidents.resolve', $incident->id), [
            'actual_cost' => 1100000, 'result_note' => 'Thay ống thoát nước',
            'files' => [UploadedFile::fake()->image('sau-khi-sua.jpg')],
        ])->assertSessionHas('success');
        $this->assertSame('waiting_acceptance', DB::table('hr_office_incidents')->where('id', $incident->id)->value('status'));

        $this->actingAs($this->staff)->get(route('hr.incidents.show', $incident->id))->assertOk()->assertSee('Nghiệm thu')->assertSee('sau-khi-sua.jpg');

        // Người báo chưa đạt => quay lại xử lý; lần 2 đạt, chấm 4 sao => đóng, đúng hạn.
        $this->actingAs($this->staff)->post(route('hr.incidents.accept', $incident->id), ['decision' => 'reject', 'feedback' => 'Vẫn còn nhỏ giọt'])->assertSessionHas('success');
        $this->assertSame('in_progress', DB::table('hr_office_incidents')->where('id', $incident->id)->value('status'));
        $this->actingAs($this->hr)->post(route('hr.incidents.resolve', $incident->id), ['actual_cost' => 1100000, 'result_note' => 'Siết lại khớp nối']);
        $this->actingAs($this->staff)->post(route('hr.incidents.accept', $incident->id), ['decision' => 'accept', 'rating' => 4])->assertSessionHas('success');

        $closed = DB::table('hr_office_incidents')->find($incident->id);
        $this->assertSame('closed', $closed->status);
        $this->assertSame(4, (int) $closed->rating);
        $this->assertSame(1, (int) $closed->reopen_count);
        $this->assertTrue((bool) $closed->on_time);
        // Báo → phân loại → duyệt chi → xử lý → chưa đạt → xử lý lại → nghiệm thu.
        $this->assertSame(
            ['reported', 'classified', 'cost_approved', 'resolved', 'reopened', 'resolved', 'accepted'],
            DB::table('hr_office_incident_logs')->where('incident_id', $incident->id)->orderBy('id')->pluck('action')->all()
        );
    }

    public function test_cost_over_threshold_needs_approval_before_resolving(): void
    {
        $incident = $this->report();
        $this->actingAs($this->hr)->post(route('hr.incidents.classify', $incident->id), ['level' => 3, 'handler_type' => 'internal', 'estimated_cost' => 0]);
        $this->assertSame('in_progress', DB::table('hr_office_incidents')->where('id', $incident->id)->value('status'));

        $this->actingAs($this->hr)->post(route('hr.incidents.resolve', $incident->id), ['actual_cost' => 800000, 'result_note' => 'Mua ghế mới'])
            ->assertSessionHas('error');
        $this->assertSame('in_progress', DB::table('hr_office_incidents')->where('id', $incident->id)->value('status'));
    }

    public function test_staff_only_sees_own_incidents_and_files(): void
    {
        $incident = $this->report();
        $other = $this->userWithRole('sales');
        $file = DB::table('hr_office_incident_files')->where('incident_id', $incident->id)->first();

        $this->actingAs($other)->get(route('hr.incidents.show', $incident->id))->assertForbidden();
        $this->actingAs($other)->get(route('hr.incidents.file', [$incident->id, $file->id]))->assertForbidden();
        $this->actingAs($other)->get(route('hr.incidents.index'))->assertOk()->assertDontSee($incident->code);

        $this->actingAs($this->staff)->get(route('hr.incidents.show', $incident->id))->assertOk()->assertSee('Phòng họp tầng 2');
        $this->actingAs($this->staff)->get(route('hr.incidents.file', [$incident->id, $file->id]))->assertOk();
        $this->actingAs($this->hr)->get(route('hr.incidents.index'))->assertOk()->assertSee($incident->code)->assertSee('ĐÚNG HẠN');
    }

    public function test_sla_stats_feed_hcns_kpi(): void
    {
        $month = now()->format('Y-m');
        $base = ['reporter_id' => $this->staff->id, 'location' => 'X', 'description' => 'Y', 'level' => 2, 'created_at' => now(), 'updated_at' => now()];
        DB::table('hr_office_incidents')->insert([
            $base + ['code' => 'T-1', 'status' => 'closed', 'due_at' => now()->addHour(), 'resolved_at' => now()],
            $base + ['code' => 'T-2', 'status' => 'closed', 'due_at' => now()->subHours(2), 'resolved_at' => now()],
            $base + ['code' => 'T-3', 'status' => 'waiting_acceptance', 'due_at' => now()->addHour(), 'resolved_at' => now()],
        ]);

        $before = OfficeIncidents::slaStats($month);
        $this->assertGreaterThanOrEqual(3, $before['total']);

        $this->actingAs($this->director)->get(route('hr.kpi.index', ['template' => 'hcns', 'month' => $month]))->assertOk();
        $hcnsTemplate = DB::table('hr_kpi_templates')->where('code', 'hcns')->first();
        $this->assertNotNull($hcnsTemplate);
    }
}
