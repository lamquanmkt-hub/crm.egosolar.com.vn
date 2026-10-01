<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\SolarMaintenanceSchedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tạo lịch bảo trì cho công trình ĐÃ CÓ (công trình cũ): ô chọn thấy cả công trình công ty khác với quản lý,
 * mở form từ trang công trình với công trình chọn sẵn, và quản lý tạo được lịch cho công trình đó.
 */
final class MaintenanceCreateForExistingSiteTest extends TestCase
{
    use DatabaseTransactions;

    private function makeSite(string $name, int $companyId): int
    {
        return (int) DB::table('sites')->insertGetId([
            'name' => $name,
            'contact_name' => 'Khach '.$name,
            'company_id' => $companyId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function search(\App\Models\User $user, string $q): array
    {
        $response = $this->actingAs($user)->getJson('/du-an/bao-tri-bao-hanh/sites/search?q='.urlencode($q))->assertOk();

        return array_map(fn ($i) => $i['text'], (array) $response->json('items'));
    }

    public function test_manager_finds_company_one_site_with_a_label_but_staff_does_not(): void
    {
        $this->makeSite('ZZ Cong trinh cu EGO VN', 1);
        $this->makeSite('ZZ Cong trinh cu Quoc te', 2);
        $manager = $this->userWithRole('technical_manager', ['email' => 'ql.congtrinhcu@egosolar.test']);
        $staff = $this->userWithRole('ky_thuat');

        $managerHits = $this->search($manager, 'ZZ Cong trinh cu');
        $this->assertCount(2, $managerHits);
        $this->assertTrue(collect($managerHits)->contains(fn ($t) => str_contains($t, 'ZZ Cong trinh cu EGO VN') && str_contains($t, 'EGO Việt Nam')));
        $this->assertTrue(collect($managerHits)->contains(fn ($t) => str_contains($t, 'ZZ Cong trinh cu Quoc te') && ! str_contains($t, 'EGO Việt Nam')));

        $staffHits = $this->search($staff, 'ZZ Cong trinh cu');
        $this->assertCount(1, $staffHits);
        $this->assertStringContainsString('Quoc te', $staffHits[0]);
    }

    public function test_manager_can_create_a_series_for_a_company_one_site(): void
    {
        $siteId = $this->makeSite('ZZ Cong trinh cu tao lich', 1);
        $manager = $this->userWithRole('technical_manager', ['email' => 'ql.congtrinhcu@egosolar.test']);
        $sitesBefore = DB::table('sites')->count();

        $this->actingAs($manager)->post(route('projects-unified.maintenance.store'), [
            'site_id' => $siteId,
            'type' => array_key_first(SolarMaintenanceSchedule::TYPES),
            'priority' => array_key_first(SolarMaintenanceSchedule::PRIORITIES),
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'rounds_count' => 2,
            'round_interval_months' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, SolarMaintenanceSchedule::withoutGlobalScopes()->where('site_id', $siteId)->count());
        // Tạo lịch bảo trì KHÔNG tạo thêm công trình mới: các đợt gắn vào đúng công trình cũ.
        $this->assertSame($sitesBefore, DB::table('sites')->count());
        $this->assertSame([$siteId], SolarMaintenanceSchedule::withoutGlobalScopes()->where('site_id', $siteId)->pluck('site_id')->unique()->map(fn ($v) => (int) $v)->all());
    }

    public function test_staff_cannot_create_a_series_at_all(): void
    {
        $siteId = $this->makeSite('ZZ Cong trinh cu nhan vien', 2);
        $staff = $this->userWithRole('ky_thuat');

        $this->actingAs($staff)->post(route('projects-unified.maintenance.store'), [
            'site_id' => $siteId,
            'type' => array_key_first(SolarMaintenanceSchedule::TYPES),
            'priority' => array_key_first(SolarMaintenanceSchedule::PRIORITIES),
            'scheduled_date' => now()->addDays(3)->toDateString(),
        ])->assertForbidden();
    }

    public function test_form_opens_with_the_site_preselected_even_when_it_is_not_among_recent_sites(): void
    {
        $oldSite = $this->makeSite('ZZ Cong trinh rat cu', 2);
        // Đẩy công trình cũ ra ngoài 50 công trình mới nhất.
        for ($i = 0; $i < 55; $i++) {
            $this->makeSite('ZZ Cong trinh moi '.$i, 2);
        }
        $manager = $this->userWithRole('technical_manager', ['email' => 'ql.congtrinhcu@egosolar.test']);

        $html = $this->actingAs($manager)
            ->get('/du-an/bao-tri-bao-hanh?site_id='.$oldSite.'&create=1')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.$oldSite.'" selected/', $html);
        $this->assertStringContainsString('"autoOpenCreate":true', $html);
    }

    public function test_form_does_not_auto_open_without_the_create_flag(): void
    {
        $manager = $this->userWithRole('technical_manager', ['email' => 'ql.congtrinhcu@egosolar.test']);

        $html = $this->actingAs($manager)->get('/du-an/bao-tri-bao-hanh')->assertOk()->getContent();

        $this->assertStringContainsString('"autoOpenCreate":false', $html);
    }

    public function test_maintenance_site_page_opens_for_manager_with_create_button_for_both_companies(): void
    {
        $own = $this->makeSite('ZZ Ho so bao tri cty 2', 2);
        $other = $this->makeSite('ZZ Ho so bao tri cty 1', 1);
        $manager = $this->userWithRole('technical_manager', ['email' => 'ql.congtrinhcu@egosolar.test']);

        foreach ([$own, $other] as $siteId) {
            $html = $this->actingAs($manager)->get('/du-an/bao-tri-bao-hanh/cong-trinh/'.$siteId)->assertOk()->getContent();
            $this->assertStringContainsString('Tạo lịch bảo trì', $html);
            $this->assertStringContainsString('site_id='.$siteId.'&amp;create=1', $html);
        }
    }

    public function test_maintenance_site_page_hides_the_button_from_staff_and_blocks_other_company(): void
    {
        $own = $this->makeSite('ZZ Ho so bao tri nv cty 2', 2);
        $other = $this->makeSite('ZZ Ho so bao tri nv cty 1', 1);
        $staff = $this->userWithRole('ky_thuat');

        // Kỹ thuật viên không được giao công trình này: trang có thể chuyển hướng; nếu mở được thì không có nút tạo lịch.
        $ownResponse = $this->actingAs($staff)->get('/du-an/bao-tri-bao-hanh/cong-trinh/'.$own);
        if ($ownResponse->getStatusCode() === 200) {
            $this->assertStringNotContainsString('Tạo lịch bảo trì', $ownResponse->getContent());
        } else {
            $ownResponse->assertRedirect();
        }

        $this->actingAs($staff)->get('/du-an/bao-tri-bao-hanh/cong-trinh/'.$other)->assertRedirect();
    }
    public function test_project_page_shows_the_create_schedule_button_only_to_people_who_can_create(): void
    {
        $siteId = $this->makeSite('ZZ Cong trinh trang chi tiet', 2);
        $manager = $this->userWithRole('technical_manager', ['email' => 'ql.congtrinhcu@egosolar.test']);
        $staff = $this->userWithRole('ky_thuat');

        $managerHtml = $this->actingAs($manager)->get('/du-an/'.$siteId)->assertOk()->getContent();
        $this->assertStringContainsString('Tạo lịch bảo trì', $managerHtml);
        $this->assertStringContainsString('site_id='.$siteId.'&amp;create=1', $managerHtml);

        $staffResponse = $this->actingAs($staff)->get('/du-an/'.$siteId);
        if ($staffResponse->getStatusCode() === 200) {
            $this->assertStringNotContainsString('Tạo lịch bảo trì', $staffResponse->getContent());
        }
    }
}
