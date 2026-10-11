<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Services\Hr\Kpi\HcnsAdminTasks;
use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Support\EgoCompanyLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Checklist nguồn KPI HCNS trên trang KPI phòng ban và KPI của tôi (đợt hcns-kpi P2/P3/P7 phần đầu).
 * Điểm chính thức chưa đổi khi quy tắc nguồn còn là bản nháp.
 */
final class HcnsKpiChecklistTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-10';

    private User $director;

    private User $hcns;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));
        $this->director = $this->person('management', 'Giám đốc');
        $this->hcns = $this->person('hr', 'Chuyên viên HCNS');
        $this->reviewer = $this->person('hr', 'Người kiểm');
        DB::table('hr_salary_profiles')->insert(['user_id' => $this->hcns->id, 'effective_month' => '2026-01', 'base_salary' => 8000000,
            'kpi_salary' => 2000000, 'kpi_template' => 'hcns', 'insurance_enabled' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, string $name): User
    {
        return $this->userWithRole($role, ['name' => 'HCNS-KPI-TEST '.$name.' '.Str::upper(Str::random(4)), 'email' => 'hcnscl-'.Str::lower(Str::random(10)).'@example.test']);
    }

    private function task(string $title, array $overrides): int
    {
        return (int) DB::table('tasks')->insertGetId($overrides + [
            'title' => $title, 'task_type' => HcnsAdminTasks::TYPE, 'hcns_procedure_type' => 'permit',
            'requester_id' => $this->director->id, 'assignee_id' => $this->hcns->id, 'approver_id' => $this->reviewer->id,
            'priority' => 'medium', 'status' => 'new', 'progress_percent' => 0, 'company_id' => EgoCompanyLock::id(),
            'due_at' => '2026-10-10 17:00:00', 'original_due_at' => '2026-10-10 17:00:00', 'submission_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedTasks(): void
    {
        $this->task('HCNS-KPI-TEST Giấy phép PCCC', ['status' => 'approved', 'first_submitted_at' => '2026-10-09 10:00:00', 'last_submitted_at' => '2026-10-09 10:00:00',
            'submission_count' => 1, 'approved_by' => $this->reviewer->id, 'approved_at' => '2026-10-12 09:00:00']);
        $this->task('HCNS-KPI-TEST Đăng ký thay đổi ĐKKD', ['status' => 'in_progress']);
    }

    public function test_department_page_shows_source_checklist_without_changing_official_score(): void
    {
        $this->seedTasks();
        $template = app(HrKpiEvaluator::class)->template('hcns');
        foreach (app(HrKpiEvaluator::class)->criteria((int) $template->id) as $criterion) {
            DB::table('hr_kpi_scores')->insert(['user_id' => $this->hcns->id, 'payroll_month' => self::MONTH, 'criterion_id' => $criterion->id,
                'achievement' => 100, 'not_applicable' => 0, 'updated_by' => $this->director->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $before = app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns');

        $response = $this->actingAs($this->director)->get(route('hr.kpi.index', ['template' => 'hcns', 'month' => self::MONTH]))->assertOk();
        $html = $response->getContent();
        $text = preg_replace('/\s+/u', ' ', strip_tags($html));

        $this->assertStringContainsString('Checklist nguồn theo tiêu chí', $text);
        $this->assertStringContainsString('1/2 = 50%', $text);
        $this->assertStringContainsString('Quy tắc nháp — chưa duyệt', $text);
        $this->assertStringContainsString('HCNS-KPI-TEST Giấy phép PCCC', $text);
        $this->assertStringContainsString('Quá hạn chưa nộp kết quả', $text);
        $this->assertStringContainsString('Chưa có nguồn tự động', $text);
        $this->assertStringContainsString('data-hk-block="documents"', $html);
        $this->assertStringContainsString(route('tasks.show', DB::table('tasks')->where('title', 'HCNS-KPI-TEST Giấy phép PCCC')->value('id')), $html);

        $after = app(HrKpiEvaluator::class)->forEmployee((int) $this->hcns->id, self::MONTH, 'hcns');
        $this->assertSame($before, $after);
        $this->assertSame(100.0, $after['percent'], 'Điểm chính thức vẫn theo nhập tay khi quy tắc nguồn là bản nháp.');
    }

    public function test_source_link_is_hidden_from_viewer_who_cannot_open_the_task(): void
    {
        $this->seedTasks();
        $otherHr = $this->person('hr', 'HR khác');

        $html = $this->actingAs($otherHr)->get(route('hr.kpi.index', ['template' => 'hcns', 'month' => self::MONTH]))->assertOk()->getContent();
        $taskId = DB::table('tasks')->where('title', 'HCNS-KPI-TEST Giấy phép PCCC')->value('id');

        $this->assertStringNotContainsString(route('tasks.show', $taskId).'"', $html);
        $this->assertStringContainsString('hk-cl-locked', $html);
        $this->actingAs($otherHr)->get(route('tasks.show', $taskId))->assertForbidden();
    }

    public function test_department_page_filters_one_employee(): void
    {
        $this->seedTasks();
        DB::table('hr_salary_profiles')->insert(['user_id' => $this->reviewer->id, 'effective_month' => '2026-01', 'base_salary' => 9000000,
            'kpi_salary' => 2000000, 'kpi_template' => 'hcns', 'insurance_enabled' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $all = $this->actingAs($this->director)->get(route('hr.kpi.index', ['template' => 'hcns', 'month' => self::MONTH]))->assertOk()->getContent();
        $total = substr_count($all, 'data-hk-checklist');
        $this->assertGreaterThanOrEqual(2, $total);
        $this->assertStringContainsString('data-pk-employee', $all);

        $one = $this->actingAs($this->director)->get(route('hr.kpi.index', ['template' => 'hcns', 'month' => self::MONTH, 'employee' => $this->hcns->id]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($one, 'data-hk-checklist'));
        $this->assertStringContainsString('HCNS-KPI-TEST Giấy phép PCCC', $one);
        $this->assertMatchesRegularExpression('/<option value="'.$this->hcns->id.'"\s+selected/', $one);

        // Người không thuộc bộ KPI kỳ này → không lọc, hiện tất cả (không lộ người ngoài bộ).
        $outside = $this->person('sales', 'Ngoài bộ');
        $fallback = $this->actingAs($this->director)->get(route('hr.kpi.index', ['template' => 'hcns', 'month' => self::MONTH, 'employee' => $outside->id]))->assertOk()->getContent();
        $this->assertSame($total, substr_count($fallback, 'data-hk-checklist'));
        $this->assertStringNotContainsString($outside->name, $fallback);
    }

    public function test_my_kpi_page_shows_own_checklist(): void
    {
        $this->seedTasks();

        $html = $this->actingAs($this->hcns)->get(route('hr.kpi-my', ['month' => self::MONTH]))->assertOk()->getContent();

        $this->assertStringContainsString('data-hk-checklist', $html);
        $this->assertStringContainsString('HCNS-KPI-TEST Đăng ký thay đổi ĐKKD', $html);
    }

    public function test_task_page_shows_hc09_status_and_how_it_counts(): void
    {
        $this->seedTasks();
        $id = DB::table('tasks')->where('title', 'HCNS-KPI-TEST Giấy phép PCCC')->value('id');

        $html = $this->actingAs($this->hcns)->get(route('tasks.show', $id))->assertOk()->getContent();

        $this->assertStringContainsString('data-hcns-state="earned"', $html);
        $this->assertStringContainsString('Thủ tục hành chính HCNS · KPI HC09', $html);
        $this->assertStringNotContainsString(route('tasks.destroy', $id).'"', $html, 'Không có nút xóa thủ tục HCNS.');
    }
}
