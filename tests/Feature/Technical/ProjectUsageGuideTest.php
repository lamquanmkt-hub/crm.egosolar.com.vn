<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Projects\Site;
use App\Models\User;
use App\Services\Projects\ProjectWorkflowV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nút + trang "Hướng dẫn sử dụng" của Công trình (đợt project-usage-guide G1–G4):
 * cùng quyền xem công trình, chỉ đọc, quay lại đúng công trình/bước, ảnh thật không placeholder.
 *
 * Dữ liệu hoàn toàn giả, trong transaction (rollback sau mỗi test), chỉ chạy trên DB egosolar_test.
 */
final class ProjectUsageGuideTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private const IMAGES = [
        'p01-cong-trinh-nut-huong-dan.png', 'p02-ho-so-nghiem-thu.png', 'p03-ky-thuat-khai-ket-qua.png', 'p04-truong-phong-xac-nhan.png',
        'p05-tra-bo-sung.png', 'p06-admin-duyet-nghiem-thu.png', 'p07-checklist-kpi.png', 'p08-khoa-ky.png',
    ];

    private User $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-07 09:00:00'));
        $this->engineer = $this->person('ky_thuat');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role): User
    {
        return $this->userWithRole($role, [
            'name' => 'Hướng dẫn giả '.Str::upper(Str::random(5)),
            'email' => 'hd-'.Str::lower(Str::random(12)).'@example.test',
        ]);
    }

    /** Công trình ở bước Nghiệm thu, giao cho $engineer. */
    private function site(?int $companyId = null): Site
    {
        $site = Site::query()->withoutGlobalScopes()->findOrFail($this->makeSite($companyId, ['lead_engineer_id' => null]));
        if ($companyId === null) {
            app(ProjectWorkflowV2Service::class)->ensure($site);
            $stepId = (int) DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', 'acceptance')->value('id');
            DB::table('project_workflow_assignments')->insert([
                'workflow_step_id' => $stepId, 'user_id' => $this->engineer->id, 'assignment_role' => 'primary',
                'status' => 'in_progress', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $site;
    }

    public function test_project_page_shows_guide_button_keeping_current_step(): void
    {
        $site = $this->site();

        $this->actingAs($this->engineer)->get(route('projects-unified.show', ['site' => $site->id, 'step' => 'acceptance']))
            ->assertOk()
            ->assertSee('data-pword-guide', false)
            ->assertSee('Hướng dẫn sử dụng')
            ->assertSee(route('projects-unified.guide', ['site' => $site->id, 'step' => 'acceptance']), false);
    }

    public function test_assigned_engineer_opens_guide_with_all_real_images(): void
    {
        $site = $this->site();

        $response = $this->actingAs($this->engineer)->get(route('projects-unified.guide', ['site' => $site->id, 'step' => 'acceptance', 'anchor' => 'acceptance-kpi']))
            ->assertOk()
            ->assertSee('Hướng dẫn công trình và nghiệm thu tính KPI')
            ->assertSee('data-pg-role="staff"', false)
            ->assertSee('id="tgLightbox"', false)
            ->assertDontSee('chưa sẵn sàng');

        foreach (self::IMAGES as $image) {
            $this->assertFileExists(public_path('images/guides/projects/'.$image));
            $response->assertSee('images/guides/projects/'.$image, false);
        }
        $back = route('projects-unified.show', ['site' => $site->id, 'step' => 'acceptance']).'#acceptance-kpi';
        $this->assertSame(2, substr_count($response->getContent(), 'href="'.e($back).'" class="btn btn-primary btn-sm" data-pg-return'));
    }

    public function test_guide_states_current_weights_and_key_distinctions(): void
    {
        $site = $this->site();

        $html = $this->actingAs($this->engineer)->get(route('projects-unified.guide', $site))->assertOk()->getContent();
        $text = preg_replace('/\s+/u', ' ', strip_tags($html));

        foreach (['25%', '15%', '10%', '5%', '30%'] as $weight) {
            $this->assertStringContainsString($weight, $text);
        }
        $this->assertStringContainsString('không phải điểm KPI', $text);
        $this->assertStringContainsString('lưu dữ liệu bước ≠ lưu khai báo KPI', $text);
        $this->assertStringContainsString('Xác nhận nhóm không phải duyệt nghiệm thu', $text);
        $this->assertStringContainsString('Khóa này khác với “bước nghiệm thu đã duyệt”', $text);
        $this->assertStringNotContainsString('45 điểm', $text);
    }

    public function test_return_link_only_accepts_known_steps_and_safe_anchor(): void
    {
        $site = $this->site();
        $overview = route('projects-unified.show', ['site' => $site->id]);

        $html = $this->actingAs($this->engineer)->get(route('projects-unified.guide', [
            'site' => $site->id, 'step' => 'https://evil.example/x', 'anchor' => '"><script>alert(1)</script>',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.e($overview).'" class="btn btn-primary btn-sm" data-pg-return', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }

    public function test_finance_step_is_not_kept_for_people_without_finance_view(): void
    {
        $site = $this->site();

        $html = $this->actingAs($this->engineer)->get(route('projects-unified.guide', ['site' => $site->id, 'step' => 'finance']))->assertOk()->getContent();

        $this->assertStringNotContainsString('step=finance', $html);
    }

    public function test_viewer_role_card_matches_manager_and_admin(): void
    {
        $site = $this->site();

        $this->actingAs($this->person('technical_manager'))->get(route('projects-unified.guide', $site))
            ->assertOk()->assertSee('data-pg-role="manager"', false);
        $this->actingAs($this->person('admin'))->get(route('projects-unified.guide', $site))
            ->assertOk()->assertSee('data-pg-role="admin"', false);
    }

    public function test_people_who_cannot_view_the_project_cannot_open_its_guide(): void
    {
        $site = $this->site();
        // Vai trò không có quyền phạm vi rộng (kho/tài chính/quản lý) và không được giao công trình.
        $stranger = $this->person('hd_nguoi_ngoai');

        $this->actingAs($stranger)->get(route('projects-unified.show', $site))->assertForbidden();
        $this->actingAs($stranger)->get(route('projects-unified.guide', $site))->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $site = $this->site();

        $this->get(route('projects-unified.guide', $site))->assertRedirect(route('login'));
    }

    public function test_other_company_project_guide_is_blocked_like_the_project_page(): void
    {
        $site = $this->site(1);
        $admin = $this->person('admin');

        $show = $this->actingAs($admin)->get(route('projects-unified.show', $site))->getStatusCode();
        $guide = $this->actingAs($admin)->get(route('projects-unified.guide', $site))->getStatusCode();

        $this->assertGreaterThanOrEqual(400, $show);
        $this->assertSame($show, $guide);
    }

    public function test_guide_is_read_only(): void
    {
        $site = $this->site();

        $this->actingAs($this->engineer)->post(route('projects-unified.show', $site).'/huong-dan')->assertStatus(405);
    }
}
