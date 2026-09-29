<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\User;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TRANG KPI KỸ THUẬT THEO TÀI LIỆU CHUẨN (2026-09) — /ky-thuat/kpis
 *
 * Khoá các yêu cầu nghiệm thu:
 *   - Đúng 6 tiêu chí của file chuẩn, tổng trọng số = 100%.
 *   - Tiêu chí 6 luôn "Chờ xác nhận nghiệp vụ", không tính điểm.
 *   - Thiếu dữ liệu => "Chưa đủ dữ liệu", KHÔNG bao giờ hiển thị/tính thành 0.
 *   - Lọc theo tháng, quý, kỹ sư, công trình, trạng thái dữ liệu.
 *   - Minh chứng chưa duyệt không được dùng.
 *   - Trang chỉ đọc: không ghi DB, không có form POST.
 */
class TechnicalKpiStandardPageTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private const MONTH = '2031-03';

    /**
     * Kỹ sư có 1 công trình bàn giao đúng hạn trong tháng 03/2031 + minh chứng đã duyệt:
     * chất lượng đạt, sai lệch vật tư 2,5% (< 3% => đạt), HSE KHÔNG đạt, EVN/App đạt.
     * Kỳ vọng: 30 + 25 + 15 + 0 + 15 = 85 điểm => "Cần cải thiện" (80% lương KPI).
     */
    private function engineerWithEvidence(bool $approved = true): array
    {
        $engineer = $this->technician(['name' => 'KPI Test Kỹ sư A']);
        $siteId = DB::table('sites')->insertGetId([
            'name' => 'CT KPI Test',
            'project_code' => 'CT-KPI-TEST',
            'lead_engineer_id' => $engineer->id,
            'target_completion_at' => '2031-03-20',
            'completed_at' => '2031-03-18',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('technical_kpi_project_evidence')->insert([
            'site_id' => $siteId,
            'user_id' => $engineer->id,
            'payroll_month' => self::MONTH,
            'timeline_excluded' => 0,
            'quality_first_pass' => 1,
            'material_waste_percent' => 2.5,
            'hse_pass' => 0,
            'evn_app_required' => 1,
            'evn_app_completed' => 1,
            'penalty_points' => 0,
            'approved_at' => $approved ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$engineer, $siteId];
    }

    private function kpiHtml(User $viewer, array $query = []): string
    {
        $response = $this->actingAs($viewer)->get(route('ky-thuat.kpis.index', $query));
        $response->assertOk();

        return (string) $response->getContent();
    }

    private function criteriaTable(string $html): string
    {
        $start = strpos($html, 'id="kpxCriteriaTable"');
        $end = strpos($html, '</table>', (int) $start);
        $this->assertNotFalse($start, 'Thiếu bảng tiêu chí KPI.');

        return substr($html, $start, $end - $start);
    }

    public function test_standard_has_exactly_six_criteria_and_weights_sum_to_100_percent(): void
    {
        $evaluator = app(KpiStandardEvaluator::class);
        $criteria = $evaluator->criteria();

        $this->assertCount(6, $criteria);
        $this->assertSame(
            [0.30, 0.25, 0.15, 0.15, 0.15, null],
            array_column($criteria, 'weight'),
            'Trọng số phải đúng file chuẩn (tiêu chí 6 chưa có trọng số).'
        );
        $this->assertEqualsWithDelta(1.0, $evaluator->weightTotal(), 1e-9);
        $this->assertTrue($criteria[5]['pending_confirmation']);
    }

    public function test_page_renders_six_criteria_rows_with_formula_and_pending_criterion_six(): void
    {
        $html = $this->kpiHtml($this->admin());
        $table = $this->criteriaTable($html);

        $this->assertSame(6, substr_count($table, 'data-kpx-criterion='));
        foreach ([
            'Tiến độ hoàn thành lắp đặt hệ thống',
            'Chất lượng thi công &amp; Thẩm mỹ',
            'Khảo sát kỹ thuật &amp; Khối lượng',
            'An toàn lao động (HSE) &amp; Vệ sinh',
            'Hỗ trợ thủ tục EVN &amp; Cài đặt App',
            'THỜI GIAN XỬ LÝ BẢO HÀNH SP',
        ] as $name) {
            $this->assertStringContainsString($name, $table, 'Thiếu tiêu chí: '.$name);
        }
        $this->assertStringContainsString('data-kpx-criterion="6" data-kpx-status="pending"', $table);
        $this->assertStringContainsString('Điểm thành phần = Tỷ lệ đạt × Trọng số', $html);
        $this->assertStringContainsString('Tổng KPI = Tổng điểm thành phần', $html);
        $this->assertStringContainsString('Tổng trọng số = 100%', $html);
        foreach (['Tiêu chí KPI', 'Trọng số', 'Tỷ lệ đạt', 'Điểm thành phần', 'Mục tiêu tiêu chuẩn', 'Dữ liệu nguồn', 'Trạng thái', 'Chi tiết minh chứng'] as $col) {
            $this->assertStringContainsString('>'.$col.'</th>', $table, 'Thiếu cột: '.$col);
        }
    }

    public function test_missing_data_is_shown_as_insufficient_never_as_zero(): void
    {
        $engineer = $this->technician(['name' => 'KPI Test Chưa có dữ liệu']);
        $html = $this->kpiHtml($this->admin(), ['month' => self::MONTH, 'user_id' => $engineer->id]);
        $table = $this->criteriaTable($html);

        $this->assertSame(5, substr_count($table, 'data-kpx-status="no_data"'));
        $this->assertStringContainsString('Chưa đủ dữ liệu', $table);
        $this->assertDoesNotMatchRegularExpression('/>\s*0(,0+)?%?\s*</u', $table, 'Dữ liệu thiếu không được hiện thành 0.');
        $this->assertMatchesRegularExpression('/data-kpx-total>\s*Chưa đủ dữ liệu/u', $html);

        $result = app(KpiStandardEvaluator::class)->score(collect(), collect());
        $this->assertNull($result['total']);
        $this->assertNull($result['tier']);
        foreach ($result['criteria'] as $c) {
            $this->assertNull($c['rate']);
            $this->assertNull($c['score']);
        }
    }

    public function test_engineer_and_month_filter_computes_score_from_verified_evidence(): void
    {
        [$engineer] = $this->engineerWithEvidence();
        $colleague = $this->technician(['name' => 'KPI Test Đồng nghiệp']);
        $html = $this->kpiHtml($this->admin(), ['month' => self::MONTH, 'user_id' => $engineer->id]);
        $table = $this->criteriaTable($html);

        // Bảng so sánh vẫn có các kỹ sư khác khi đang xem chi tiết một người.
        $this->assertStringContainsString('data-kpx-engineer="'.$colleague->id.'"', $html);

        $this->assertStringContainsString('data-kpx-criterion="1" data-kpx-status="pass"', $table);
        $this->assertStringContainsString('data-kpx-criterion="2" data-kpx-status="pass"', $table);
        $this->assertStringContainsString('data-kpx-criterion="3" data-kpx-status="pass"', $table);
        $this->assertStringContainsString('data-kpx-criterion="4" data-kpx-status="fail"', $table);
        $this->assertStringContainsString('data-kpx-criterion="5" data-kpx-status="pass"', $table);
        $this->assertStringContainsString('data-kpx-criterion="6" data-kpx-status="pending"', $table);
        $this->assertMatchesRegularExpression('/data-kpx-total>\s*85,0%/u', $html);
        $this->assertStringContainsString('Cần cải thiện', $html);
        $this->assertStringContainsString('80% tiền lương KPI', $html);

        // Tháng khác của cùng kỹ sư: không có dữ liệu => không chấm.
        $other = $this->kpiHtml($this->admin(), ['month' => '2031-05', 'user_id' => $engineer->id]);
        $this->assertMatchesRegularExpression('/data-kpx-total>\s*Chưa đủ dữ liệu/u', $other);
    }

    public function test_quarter_period_includes_month_data(): void
    {
        [$engineer] = $this->engineerWithEvidence();
        $html = $this->kpiHtml($this->admin(), ['period' => 'quarter', 'quarter' => '2031-Q1', 'user_id' => $engineer->id]);

        $this->assertStringContainsString('Quý 1/2031', $html);
        $this->assertMatchesRegularExpression('/data-kpx-total>\s*85,0%/u', $html);
    }

    public function test_site_filter_limits_projects(): void
    {
        [$engineer, $siteId] = $this->engineerWithEvidence();
        $otherSite = DB::table('sites')->insertGetId(['name' => 'CT khác', 'created_at' => now(), 'updated_at' => now()]);

        $match = $this->kpiHtml($this->admin(), ['month' => self::MONTH, 'user_id' => $engineer->id, 'site_id' => $siteId]);
        $this->assertMatchesRegularExpression('/data-kpx-total>\s*85,0%/u', $match);

        $none = $this->kpiHtml($this->admin(), ['month' => self::MONTH, 'user_id' => $engineer->id, 'site_id' => $otherSite]);
        $this->assertMatchesRegularExpression('/data-kpx-total>\s*Chưa đủ dữ liệu/u', $none);
    }

    public function test_unapproved_evidence_is_not_used(): void
    {
        [$engineer] = $this->engineerWithEvidence(approved: false);
        $table = $this->criteriaTable($this->kpiHtml($this->admin(), ['month' => self::MONTH, 'user_id' => $engineer->id]));

        // Tiến độ lấy từ ngày công trình (vẫn tính); 4 tiêu chí cần minh chứng đã duyệt thì thiếu dữ liệu.
        $this->assertStringContainsString('data-kpx-criterion="1" data-kpx-status="pass"', $table);
        foreach ([2, 3, 4, 5] as $no) {
            $this->assertStringContainsString('data-kpx-criterion="'.$no.'" data-kpx-status="no_data"', $table);
        }
    }

    public function test_data_status_filter(): void
    {
        [$full] = $this->engineerWithEvidence();
        $empty = $this->technician(['name' => 'KPI Test Kỹ sư rỗng']);
        $admin = $this->admin();

        $fullHtml = $this->kpiHtml($admin, ['month' => self::MONTH, 'data_status' => 'full']);
        $this->assertStringContainsString('data-kpx-engineer="'.$full->id.'"', $fullHtml);
        $this->assertStringNotContainsString('data-kpx-engineer="'.$empty->id.'"', $fullHtml);

        $noneHtml = $this->kpiHtml($admin, ['month' => self::MONTH, 'data_status' => 'none']);
        $this->assertStringContainsString('data-kpx-engineer="'.$empty->id.'"', $noneHtml);
        $this->assertStringNotContainsString('data-kpx-engineer="'.$full->id.'"', $noneHtml);
    }

    public function test_bonus_tier_boundaries_follow_the_standard(): void
    {
        $evaluator = app(KpiStandardEvaluator::class);

        $this->assertSame('Vượt chỉ tiêu', $evaluator->tierFor(1.00)['label']);
        $this->assertSame('Đạt yêu cầu', $evaluator->tierFor(0.9999)['label']);
        $this->assertSame('Đạt yêu cầu', $evaluator->tierFor(0.90)['label']);
        $this->assertSame('Cần cải thiện', $evaluator->tierFor(0.8999)['label']);
        $this->assertSame('Cần cải thiện', $evaluator->tierFor(0.75)['label']);
        $this->assertSame('Không đạt', $evaluator->tierFor(0.7499)['label']);
        $this->assertNull($evaluator->tierFor(null));
    }

    public function test_viewing_page_is_read_only(): void
    {
        [$engineer] = $this->engineerWithEvidence();
        $admin = $this->admin();
        $tables = ['technical_kpi_settings', 'technical_kpi_payrolls', 'technical_kpi_payroll_items', 'technical_kpi_project_evidence', 'sites'];
        $before = array_map(fn ($t) => DB::table($t)->count(), array_combine($tables, $tables));

        DB::enableQueryLog();
        $pages = [
            $this->kpiHtml($admin, ['month' => self::MONTH]),
            $this->kpiHtml($admin, ['month' => self::MONTH, 'user_id' => $engineer->id]),
        ];
        $writes = collect(DB::getQueryLog())->filter(
            fn ($q) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $q['query'])
                && preg_match('/technical_kpi|sites|payroll/i', $q['query'])
        );
        DB::disableQueryLog();

        $this->assertCount(0, $writes, 'Trang KPI không được ghi dữ liệu KPI.');
        $this->assertSame($before, array_map(fn ($t) => DB::table($t)->count(), array_combine($tables, $tables)));

        // Vùng nội dung trang KPI (từ khung .kpx tới hộp thoại minh chứng cuối) không có form ghi.
        foreach ($pages as $html) {
            $start = strpos($html, '<div class="kpx">');
            $end = strrpos($html, '</dialog>');
            $this->assertNotFalse($start);
            $this->assertNotFalse($end);
            $body = substr($html, $start, $end - $start);
            $this->assertDoesNotMatchRegularExpression('/<form[^>]+method=["\'](?:post|put|delete)["\']/i', $body);
            $this->assertStringNotContainsString('name="_token"', $body);
        }
    }

    public function test_technician_can_open_page(): void
    {
        $this->kpiHtml($this->technician());
    }
}
