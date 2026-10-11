<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Services\Hr\Kpi\AssetInventories;
use App\Services\Hr\Kpi\HcnsKpiChecklist;
use App\Services\Hr\Kpi\HcnsKpiRules;
use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Services\Hr\Kpi\Sources\AssetsServiceSource;
use App\Services\Hr\Kpi\VppAllocationKpi;
use App\Support\EgoCompanyLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * KPI HCNS — HC06 cấp phát VPP, kiểm kê tài sản, sự cố văn phòng (đợt hcns-kpi P4).
 * Luồng thật qua trang VPP / HC & Vận hành / Sự cố; dữ liệu giả trong transaction trên egosolar_test, tệp trên disk giả.
 */
final class HcnsAssetsServiceKpiTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-10';

    private User $hcns;

    private User $reviewer;

    private User $director;

    private User $requester;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(VppAllocationKpi::ready() && AssetInventories::ready() && AssetsServiceSource::incidentsReady(), 'DB test chưa áp migration HC06.');
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-10-06 09:00:00'));
        $this->hcns = $this->person('hr', 'HCNS xử lý');
        $this->reviewer = $this->person('hr', 'HCNS kiểm');
        $this->director = $this->person('management', 'Giám đốc');
        $this->requester = $this->person('sales', 'Kinh doanh yêu cầu');
        $this->productId = (int) DB::table('hr_vpp_products')->insertGetId(['name' => 'HCNS-KPI-TEST Giấy A4 '.Str::random(5), 'unit' => 'ram',
            'current_stock' => 100, 'min_stock' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, string $name): User
    {
        return $this->userWithRole($role, ['name' => 'HCNS-KPI-TEST '.$name.' '.Str::upper(Str::random(4)), 'email' => 'hcnsasset-'.Str::lower(Str::random(10)).'@example.test']);
    }

    private function rules(array $assets = []): array
    {
        $rules = app(HcnsKpiRules::class)->defaults();
        $rules['assets'] = $assets + $rules['assets'];

        return $rules;
    }

    private function rows(User $user, array $rules = [], ?Carbon $now = null): array
    {
        return app(AssetsServiceSource::class)->rows((int) $user->id, self::MONTH, $this->rules($rules), $now ?? now());
    }

    private function allocate(array $overrides = []): int
    {
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.stock.allocate'), $overrides + [
            'department_name' => 'Kinh doanh', 'receiver_name' => 'HCNS-KPI-TEST Người nhận', 'product_id' => [$this->productId], 'qty' => [2],
            'kpi_tracked' => '1', 'requester_user_id' => $this->requester->id, 'reviewer_id' => $this->reviewer->id,
            'request_received_at' => '2026-10-06 08:00', 'due_at' => '2026-10-07 17:00', 'delivered_at' => '2026-10-06 08:30',
        ])->assertRedirect();

        return (int) DB::table('hr_vpp_requests')->orderByDesc('id')->value('id');
    }

    private function vppReview(int $id): ?object
    {
        return DB::table('hr_kpi_source_reviews')->where('source_type', VppAllocationKpi::SOURCE_TYPE)->where('source_id', $id)->first();
    }

    private function vppPass(): array
    {
        return ['checks' => ['timestamps' => 'pass', 'quantity' => 'pass', 'receipt' => 'pass']];
    }

    private function uploadReceipt(int $id, string $name = 'ky-nhan.jpg'): void
    {
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.files.upload', $id), ['files' => [UploadedFile::fake()->image($name)]])->assertRedirect();
    }

    private function seedOperationAssets(int $count = 2): void
    {
        for ($i = 1; $i <= $count; $i++) {
            DB::table('hr_operation_assets')->insert(['asset_name' => 'HCNS-KPI-TEST Laptop '.$i, 'asset_type' => 'Laptop', 'assigned_to' => 'NV '.$i,
                'status' => 'Đang sử dụng', 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function inventory(array $overrides = []): int
    {
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.store'), $overrides + [
            'title' => 'HCNS-KPI-TEST Kiểm kê tháng 10', 'period_month' => self::MONTH, 'scope' => 'hr_operation',
            'handler_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'due_at' => '2026-10-20 17:00',
        ])->assertRedirect();

        return (int) DB::table('hr_asset_inventories')->orderByDesc('id')->value('id');
    }

    private function fillInventory(int $id, string $result = 'ok'): void
    {
        $items = DB::table('hr_asset_inventory_items')->where('inventory_id', $id)->pluck('id');
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.items', $id), [
            'result' => $items->mapWithKeys(fn ($i) => [$i => $result])->all(),
            'item_note' => $items->mapWithKeys(fn ($i) => [$i => $result === 'ok' ? '' : 'Ghi chú '.$i])->all(),
        ])->assertRedirect();
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.files.upload', $id), ['files' => [UploadedFile::fake()->create('bien-ban.pdf', 8, 'application/pdf')]])->assertRedirect();
    }

    private function incident(array $values = []): int
    {
        return (int) DB::table('hr_office_incidents')->insertGetId($values + [
            'company_id' => EgoCompanyLock::id(), 'code' => 'SC-TEST-'.Str::upper(Str::random(5)), 'reporter_id' => $this->requester->id,
            'location' => 'HCNS-KPI-TEST Phòng họp', 'description' => 'Điều hòa hỏng', 'status' => 'new',
            'created_at' => '2026-10-06 09:00:00', 'updated_at' => now(),
        ]);
    }

    public function test_vpp_tracked_allocation_validation_private_evidence_and_confirm_flow(): void
    {
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.stock.allocate'), [
            'department_name' => 'KD', 'receiver_name' => 'A', 'product_id' => [$this->productId], 'qty' => [1], 'kpi_tracked' => '1',
            'requester_user_id' => $this->requester->id, 'reviewer_id' => $this->hcns->id, 'request_received_at' => '2026-10-06 08:00', 'due_at' => '2026-10-07 17:00',
        ])->assertSessionHasErrors('reviewer_id');
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.stock.allocate'), [
            'department_name' => 'KD', 'receiver_name' => 'A', 'product_id' => [$this->productId], 'qty' => [1], 'kpi_tracked' => '1',
            'requester_user_id' => $this->requester->id, 'reviewer_id' => $this->reviewer->id, 'request_received_at' => '2026-10-06 10:00', 'due_at' => '2026-10-06 09:00',
        ])->assertSessionHasErrors('due_at');
        $this->assertSame(100.0, (float) DB::table('hr_vpp_products')->where('id', $this->productId)->value('current_stock'), 'Lỗi nhập không trừ kho.');

        $id = $this->allocate();
        $row = DB::table('hr_vpp_requests')->find($id);
        $this->assertSame([EgoCompanyLock::id(), 1, (int) $this->hcns->id, '2026-10-06 08:00:00'], [(int) $row->company_id, (int) $row->kpi_tracked, (int) $row->handler_id, (string) $row->request_received_at]);
        $this->assertSame(98.0, (float) DB::table('hr_vpp_products')->where('id', $this->productId)->value('current_stock'));
        $this->assertSame('missing', $this->rows($this->hcns)[0]['kpi_state'], 'Chưa gửi kiểm: chưa tính.');

        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.submit', $id))->assertSessionHas('error');
        $this->uploadReceipt($id);
        $file = DB::table('hr_kpi_source_files')->where('source_type', VppAllocationKpi::SOURCE_TYPE)->where('source_id', $id)->first();
        $this->assertSame('local', $file->disk);
        Storage::disk('local')->assertExists($file->path);
        Storage::disk('public')->assertMissing($file->path);
        $this->actingAs($this->reviewer)->get(route('hr.office-supply-process.kpi.files.show', [$id, $file->id]))->assertOk();
        $this->actingAs($this->reviewer)->get(route('hr.office-supply-process.kpi.files.show', [$id, $file->id]).'?download=1')->assertDownload('ky-nhan.jpg');

        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.submit', $id))->assertForbidden();
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.submit', $id))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('submitted', $this->vppReview($id)->status);
        $this->assertSame('pending', $this->rows($this->hcns)[0]['kpi_state']);

        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.confirm', $id), $this->vppPass())->assertForbidden();
        $this->actingAs($this->requester)->post(route('hr.office-supply-process.kpi.confirm', $id), $this->vppPass())->assertForbidden();
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $id), ['checks' => ['timestamps' => 'pass']])->assertSessionHasErrors('checks');
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $id), $this->vppPass())->assertRedirect();
        $this->assertSame(['confirmed', 'pass'], [$this->vppReview($id)->status, $this->vppReview($id)->result]);
        $this->assertSame('earned', $this->rows($this->hcns)[0]['kpi_state']);

        $page = $this->actingAs($this->reviewer)->get(route('hr.office-supply-process.show', $id))->assertOk()->getContent();
        $this->assertStringContainsString('data-kpi-review-status="confirmed"', $page);
        $this->assertStringNotContainsString('storage/'.$file->path, $page);

        // Người yêu cầu tự xác nhận đã nhận chỉ khi vào được trang Nhân sự (không thay biên bản ký nhận); người xử lý không tự xác nhận thay.
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.receipt', $id))->assertForbidden();
        $this->actingAs($this->requester)->post(route('hr.office-supply-process.kpi.receipt', $id))->assertForbidden();
        $this->assertNull(DB::table('hr_vpp_requests')->where('id', $id)->value('receipt_confirmed_by'));
        $own = $this->allocate(['requester_user_id' => $this->director->id]);
        $this->actingAs($this->director)->post(route('hr.office-supply-process.kpi.receipt', $own))->assertRedirect();
        $this->assertSame((int) $this->director->id, (int) DB::table('hr_vpp_requests')->where('id', $own)->value('receipt_confirmed_by'));
    }

    public function test_vpp_deadline_boundary_late_and_failed_check(): void
    {
        $onBoundary = $this->allocate(['delivered_at' => '2026-10-06 08:30', 'due_at' => '2026-10-06 08:30']);
        Carbon::setTestNow('2026-10-08 10:00:00');
        $late = $this->allocate(['request_received_at' => '2026-10-07 08:00', 'due_at' => '2026-10-07 17:00', 'delivered_at' => '2026-10-08 09:00']);
        $failed = $this->allocate();
        foreach ([$onBoundary, $late, $failed] as $id) {
            $this->uploadReceipt($id);
            $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.submit', $id))->assertSessionHas('success');
        }
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $onBoundary), $this->vppPass());
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $late), $this->vppPass());
        $fail = ['checks' => ['timestamps' => 'pass', 'quantity' => 'fail', 'receipt' => 'pass']];
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $failed), $fail)->assertSessionHasErrors('note');
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $failed), $fail + ['note' => 'Thiếu 1 ram'])->assertRedirect();

        $states = collect($this->rows($this->hcns))->pluck('kpi_state', 'source_id');
        $this->assertSame(['earned', 'lost', 'lost'], [$states[$onBoundary], $states[$late], $states[$failed]], 'Giao đúng bằng hạn là đúng hạn; trễ và kiểm không đạt đều vào mẫu số.');
        $this->assertStringContainsString('Đúng loại, đủ số lượng', collect($this->rows($this->hcns))->firstWhere('source_id', $failed)['reason']);
    }

    public function test_vpp_change_person_deadline_file_after_confirm_requires_resubmit_and_delete_blocked(): void
    {
        $id = $this->allocate();
        $this->uploadReceipt($id);
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.submit', $id));
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $id), $this->vppPass());

        $edit = ['requester_user_id' => $this->requester->id, 'handler_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id,
            'request_received_at' => '2026-10-06 08:00', 'due_at' => '2026-10-08 17:00', 'delivered_at' => '2026-10-06 08:30'];
        $this->actingAs($this->reviewer)->put(route('hr.office-supply-process.kpi.update', $id), $edit)->assertForbidden();
        $this->actingAs($this->hcns)->put(route('hr.office-supply-process.kpi.update', $id), ['reviewer_id' => $this->hcns->id] + $edit)->assertSessionHasErrors('reviewer_id');
        $this->actingAs($this->hcns)->put(route('hr.office-supply-process.kpi.update', $id), $edit)->assertRedirect();
        $this->assertSame('changed', $this->vppReview($id)->status);
        $this->actingAs($this->hcns)->put(route('hr.office-supply-process.kpi.update', $id), ['due_at' => '2026-10-07 17:00'] + $edit)->assertRedirect();
        $this->assertSame('changed', $this->vppReview($id)->status, 'Đổi về hạn cũ không làm xác nhận sống lại.');
        $this->assertSame('pending', $this->rows($this->hcns)[0]['kpi_state']);
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $id), $this->vppPass())->assertSessionHasErrors('kpi');

        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.submit', $id))->assertSessionHas('success');
        $this->assertSame(2, (int) $this->vppReview($id)->submission_count);
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $id), $this->vppPass());

        // Đổi người xử lý: nguồn chuyển sang người mới và phải gửi lại.
        $other = $this->person('hr', 'HCNS khác');
        $this->actingAs($this->director)->put(route('hr.office-supply-process.kpi.update', $id), ['handler_id' => $other->id] + $edit)->assertRedirect();
        $this->assertSame('changed', $this->vppReview($id)->status);
        $this->assertSame([], $this->rows($this->hcns));
        $this->assertSame($id, $this->rows($other)[0]['source_id']);

        // Xóa tệp sau khi gửi → hết hiệu lực; phiếu đã gửi không xóa được.
        $this->actingAs($other)->post(route('hr.office-supply-process.kpi.submit', $id))->assertSessionHas('success');
        $file = DB::table('hr_kpi_source_files')->where('source_type', VppAllocationKpi::SOURCE_TYPE)->where('source_id', $id)->first();
        $this->actingAs($other)->delete(route('hr.office-supply-process.kpi.files.delete', [$id, $file->id]))->assertRedirect();
        $this->assertSame('changed', $this->vppReview($id)->status);
        $this->actingAs($this->director)->delete(route('hr.office-supply-process.destroy', $id))->assertSessionHas('error');
        $this->assertNotNull(DB::table('hr_vpp_requests')->find($id));
        $this->assertTrue(DB::table('hr_kpi_source_review_events')->where('review_id', $this->vppReview($id)->id)->where('action', 'invalidated')->count() >= 3);
    }

    public function test_vpp_locked_payroll_month_blocks_every_change(): void
    {
        $id = $this->allocate();
        $this->uploadReceipt($id);
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.submit', $id));
        DB::table('hr_payroll_periods')->insert(['payroll_month' => self::MONTH, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $file = DB::table('hr_kpi_source_files')->where('source_type', VppAllocationKpi::SOURCE_TYPE)->where('source_id', $id)->first();
        $edit = ['requester_user_id' => $this->requester->id, 'handler_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id,
            'request_received_at' => '2026-10-06 08:00', 'due_at' => '2026-10-07 17:00', 'delivered_at' => '2026-10-06 07:00'];

        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $id), $this->vppPass())->assertSessionHasErrors('kpi');
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.return', $id), ['reason' => 'Trả sau chốt'])->assertSessionHasErrors('kpi');
        $this->actingAs($this->director)->put(route('hr.office-supply-process.kpi.update', $id), $edit)->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.files.upload', $id), ['files' => [UploadedFile::fake()->image('x.jpg')]])->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->delete(route('hr.office-supply-process.kpi.files.delete', [$id, $file->id]))->assertSessionHasErrors('kpi');
        $this->actingAs($this->director)->delete(route('hr.office-supply-process.destroy', $id))->assertSessionHas('error');
        $this->assertSame('submitted', $this->vppReview($id)->status);
        $this->assertSame('2026-10-06 08:30:00', (string) DB::table('hr_vpp_requests')->where('id', $id)->value('delivered_at'));
        $this->assertSame(1, DB::table('hr_kpi_source_files')->where('source_type', VppAllocationKpi::SOURCE_TYPE)->where('source_id', $id)->count());

        // Không theo dõi KPI → cấp phát thường không bị khóa.
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.stock.allocate'), ['department_name' => 'KD', 'receiver_name' => 'B',
            'product_id' => [$this->productId], 'qty' => [1]])->assertSessionHas('success');
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.stock.allocate'), ['due_at' => '2026-10-20 17:00', 'request_received_at' => '2026-10-06 08:00'] + [
            'department_name' => 'KD', 'receiver_name' => 'C', 'product_id' => [$this->productId], 'qty' => [1], 'kpi_tracked' => '1',
            'requester_user_id' => $this->requester->id, 'reviewer_id' => $this->reviewer->id])->assertSessionHasErrors('due_at');
    }

    public function test_inventory_plan_items_evidence_submit_return_and_completion_time(): void
    {
        DB::table('finance_assets')->where('company_id', EgoCompanyLock::id())->update(['deleted_at' => now()]);
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.store'), ['title' => 'Kỳ trống', 'period_month' => self::MONTH, 'scope' => 'finance',
            'handler_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'due_at' => '2026-10-20 17:00'])->assertSessionHasErrors('scope');
        $this->seedOperationAssets(2);
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.store'), ['title' => 'X', 'period_month' => self::MONTH, 'scope' => 'hr_operation',
            'handler_id' => $this->hcns->id, 'reviewer_id' => $this->hcns->id, 'due_at' => '2026-10-20 17:00'])->assertSessionHasErrors('reviewer_id');

        $id = $this->inventory();
        $inv = DB::table('hr_asset_inventories')->find($id);
        $total = DB::table('hr_operation_assets')->count();
        $this->assertSame([EgoCompanyLock::id(), 'planned', $total], [(int) $inv->company_id, $inv->status, DB::table('hr_asset_inventory_items')->where('inventory_id', $id)->count()]);
        $this->assertSame('waiting', $this->rows($this->hcns)[0]['kpi_state'], 'Còn hạn, chưa làm: chưa tính.');

        $items = DB::table('hr_asset_inventory_items')->where('inventory_id', $id)->pluck('id');
        $this->actingAs($this->reviewer)->post(route('hr.asset-inventories.items', $id), ['result' => [$items[0] => 'ok']])->assertForbidden();
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.items', $id), ['result' => [$items[0] => 'missing']])->assertSessionHasErrors('item_note');
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.items', $id), ['result' => [$items[0] => 'ok']])->assertRedirect();
        $this->assertSame('in_progress', DB::table('hr_asset_inventories')->where('id', $id)->value('status'));
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.kpi.submit', $id))->assertSessionHas('error');

        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->fillInventory($id);
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.kpi.submit', $id))->assertSessionHas('success');
        $this->assertSame(['completed', '2026-10-15 10:00:00'], [DB::table('hr_asset_inventories')->where('id', $id)->value('status'), (string) DB::table('hr_asset_inventories')->where('id', $id)->value('completed_at')]);
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.cancel', $id), ['cancel_reason' => 'Hủy sau khi gửi'])->assertSessionHas('error');

        $this->actingAs($this->reviewer)->post(route('hr.asset-inventories.kpi.return', $id), ['reason' => 'Thiếu ảnh tem tài sản'])->assertRedirect();
        $this->assertSame('in_progress', DB::table('hr_asset_inventories')->where('id', $id)->value('status'));
        $this->assertSame('pending', $this->rows($this->hcns)[0]['kpi_state']);

        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.kpi.submit', $id))->assertSessionHas('success');
        $this->actingAs($this->reviewer)->post(route('hr.asset-inventories.kpi.confirm', $id), ['checks' => ['results' => 'pass', 'evidence' => 'pass']])->assertRedirect();
        $row = $this->rows($this->hcns, [], Carbon::parse('2026-10-21 09:00:00'))[0];
        $this->assertSame('lost', $row['kpi_state'], 'Lần gửi được xác nhận (21/10) sau hạn 20/10 → không đúng hạn.');
        $this->assertSame('2026-10-15 10:00:00', (string) DB::table('hr_kpi_source_reviews')->where('source_type', AssetInventories::SOURCE_TYPE)->where('source_id', $id)->value('first_submitted_at'));
        $this->assertTrue(DB::table('hr_kpi_source_logs')->where('source_type', AssetInventories::SOURCE_TYPE)->where('source_id', $id)->where('action', 'completed')->count() === 2);

        $page = $this->actingAs($this->reviewer)->get(route('hr.asset-inventories.show', $id))->assertOk()->getContent();
        $this->assertStringContainsString('data-kpi-review-status="confirmed"', $page);
        $this->assertStringContainsString('Lịch sử đợt kiểm kê', $page);
        $this->actingAs($this->hcns)->get(route('hr.operations.index', ['tab' => 'assets']))->assertOk()->assertSee('data-hc-inventory-row="'.$id.'"', false);
    }

    public function test_inventory_cancel_overdue_waiting_lock_and_change_after_confirm(): void
    {
        $this->seedOperationAssets(1);
        $cancelledEarly = $this->inventory(['due_at' => '2026-10-10 17:00']);
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.cancel', $cancelledEarly), ['cancel_reason' => 'Dời sang kỳ sau'])->assertRedirect();
        $overdue = $this->inventory(['due_at' => '2026-10-07 17:00']);
        $cancelledLate = $this->inventory(['due_at' => '2026-10-07 17:00']);
        $confirmed = $this->inventory(['due_at' => '2026-10-20 17:00']);
        $this->fillInventory($confirmed);
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.kpi.submit', $confirmed));
        $this->actingAs($this->reviewer)->post(route('hr.asset-inventories.kpi.confirm', $confirmed), ['checks' => ['results' => 'pass', 'evidence' => 'pass']]);

        Carbon::setTestNow('2026-10-08 09:00:00');
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.cancel', $cancelledLate), ['cancel_reason' => 'Hủy khi đã quá hạn'])->assertRedirect();
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.items', $cancelledLate), ['result' => []])->assertSessionHasErrors('kpi');

        $states = collect($this->rows($this->hcns))->pluck('kpi_state', 'source_id');
        $this->assertSame(['excluded', 'lost', 'lost', 'earned'], [$states[$cancelledEarly], $states[$overdue], $states[$cancelledLate], $states[$confirmed]]);

        // Đổi người phụ trách sau xác nhận → phải gửi lại; đổi kế hoạch chỉ người phụ trách/Ban Giám đốc.
        $plan = ['title' => 'HCNS-KPI-TEST Kiểm kê tháng 10', 'handler_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'due_at' => '2026-10-22 17:00'];
        $this->actingAs($this->reviewer)->put(route('hr.asset-inventories.update', $confirmed), $plan)->assertForbidden();
        $this->actingAs($this->hcns)->put(route('hr.asset-inventories.update', $confirmed), $plan)->assertRedirect();
        $this->assertSame('changed', DB::table('hr_kpi_source_reviews')->where('source_type', AssetInventories::SOURCE_TYPE)->where('source_id', $confirmed)->value('status'));
        $this->assertSame('in_progress', DB::table('hr_asset_inventories')->where('id', $confirmed)->value('status'));

        DB::table('hr_payroll_periods')->insert(['payroll_month' => self::MONTH, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.kpi.submit', $confirmed))->assertSessionHasErrors('kpi');
        $this->actingAs($this->director)->put(route('hr.asset-inventories.update', $confirmed), ['due_at' => '2026-10-25 17:00'] + $plan)->assertSessionHasErrors('kpi');
        $this->actingAs($this->hcns)->post(route('hr.asset-inventories.store'), ['title' => 'Sau chốt', 'period_month' => self::MONTH, 'scope' => 'hr_operation',
            'handler_id' => $this->hcns->id, 'reviewer_id' => $this->reviewer->id, 'due_at' => '2026-10-28 17:00'])->assertSessionHasErrors('period_month');
        $this->assertSame('2026-10-22 17:00:00', (string) DB::table('hr_asset_inventories')->where('id', $confirmed)->value('due_at'));
    }

    public function test_incident_handler_wait_first_resolution_cancel_and_lock(): void
    {
        $settings = \App\Services\Hr\OfficeIncidents::settings();
        $id = $this->incident();
        // Phân loại: người xử lý mặc định là người phân loại; chi phí vượt ngưỡng → bắt đầu chờ duyệt.
        $this->actingAs($this->hcns)->post(route('hr.incidents.classify', $id), ['level' => 2, 'handler_type' => 'internal',
            'estimated_cost' => (float) $settings['incident_cost_approval_threshold'] + 100000])->assertRedirect();
        $row = DB::table('hr_office_incidents')->find($id);
        $this->assertSame([(int) $this->hcns->id, 'waiting_approval', '2026-10-06 09:00:00'], [(int) $row->handler_id, $row->status, (string) $row->waiting_since]);

        Carbon::setTestNow('2026-10-06 11:00:00');
        $this->actingAs($this->director)->post(route('hr.incidents.approve-cost', $id), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame([120, null], [(int) DB::table('hr_office_incidents')->where('id', $id)->value('approval_wait_minutes'), DB::table('hr_office_incidents')->where('id', $id)->value('waiting_since')]);

        $due = Carbon::parse(DB::table('hr_office_incidents')->where('id', $id)->value('due_at'));
        Carbon::setTestNow($due->copy()->subMinutes(30));
        $this->actingAs($this->hcns)->post(route('hr.incidents.resolve', $id), ['result_note' => 'Đã thay tụ'])->assertRedirect();
        $first = (string) DB::table('hr_office_incidents')->where('id', $id)->value('first_resolved_at');
        $this->actingAs($this->requester)->post(route('hr.incidents.accept', $id), ['decision' => 'reject', 'feedback' => 'Vẫn kêu to'])->assertRedirect();
        Carbon::setTestNow($due->copy()->addMinutes(60));
        $this->actingAs($this->hcns)->post(route('hr.incidents.resolve', $id), ['result_note' => 'Đã thay quạt'])->assertRedirect();
        $this->assertSame($first, (string) DB::table('hr_office_incidents')->where('id', $id)->value('first_resolved_at'), 'Giữ lần báo xong đầu tiên.');
        $this->actingAs($this->requester)->post(route('hr.incidents.accept', $id), ['decision' => 'accept', 'rating' => 4])->assertRedirect();

        $now = $due->copy()->addHours(2);
        $state = fn (array $r) => collect($this->rows($this->hcns, $r, $now))->firstWhere('source_id', $id)['kpi_state'];
        $this->assertSame('lost', $state([]), 'Mặc định nháp: lần xử lý xong được nghiệm thu, giờ liên tục.');
        $this->assertSame('earned', $state(['incident_measure' => 'first_resolution']));
        $this->assertSame('earned', $state(['incident_wait' => 'exclude_approval_wait']), 'Trừ 120 phút chờ duyệt chi: hạn lùi 2 giờ.');
        $this->assertSame([], $this->rows($this->reviewer, [], $now), 'Chỉ tính cho người xử lý bằng ID.');

        // Hủy trước hạn: loại; hủy sau hạn khi chưa xử lý: không đạt.
        Carbon::setTestNow('2026-10-06 09:00:00');
        $early = $this->incident();
        $this->actingAs($this->hcns)->post(route('hr.incidents.classify', $early), ['level' => 2, 'handler_type' => 'internal']);
        $this->actingAs($this->hcns)->post(route('hr.incidents.cancel', $early), ['note' => 'Báo trùng'])->assertRedirect();
        $late = $this->incident();
        $this->actingAs($this->hcns)->post(route('hr.incidents.classify', $late), ['level' => 1, 'handler_type' => 'internal']);
        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->actingAs($this->hcns)->post(route('hr.incidents.cancel', $late), ['note' => 'Hủy muộn'])->assertRedirect();
        $states = collect($this->rows($this->hcns))->pluck('kpi_state', 'source_id');
        $this->assertSame(['excluded', 'lost'], [$states[$early], $states[$late]]);

        // Khóa kỳ: không phân loại lại/hủy; xử lý, nghiệm thu vẫn làm được.
        $open = $this->incident(['created_at' => '2026-10-07 09:00:00']);
        $this->actingAs($this->hcns)->post(route('hr.incidents.classify', $open), ['level' => 3, 'handler_type' => 'internal'])->assertRedirect();
        DB::table('hr_payroll_periods')->insert(['payroll_month' => self::MONTH, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->hcns)->post(route('hr.incidents.classify', $open), ['level' => 1, 'handler_type' => 'internal'])->assertSessionHas('error');
        $this->actingAs($this->hcns)->post(route('hr.incidents.cancel', $open), ['note' => 'x'])->assertSessionHas('error');
        $this->assertSame(3, (int) DB::table('hr_office_incidents')->where('id', $open)->value('level'));
        $this->actingAs($this->hcns)->post(route('hr.incidents.resolve', $open), ['result_note' => 'Đã sửa sau chốt'])->assertRedirect();
        $this->assertSame('waiting_acceptance', DB::table('hr_office_incidents')->where('id', $open)->value('status'));

        $page = $this->actingAs($this->hcns)->get(route('hr.incidents.show', $id))->assertOk()->getContent();
        $this->assertStringContainsString('Số đo KPI HC06', $page);
        $this->assertStringContainsString('data-ic-kpi="lost"', $page);
    }

    public function test_groups_aggregation_scope_and_official_only_when_applied_with_aggregation(): void
    {
        // Cấp phát: 1 đạt. Kiểm kê: 1 quá hạn (không đạt). Sự cố: 1 đúng hạn đã nghiệm thu + 1 công ty khác.
        $vpp = $this->allocate();
        $this->uploadReceipt($vpp);
        $this->actingAs($this->hcns)->post(route('hr.office-supply-process.kpi.submit', $vpp));
        $this->actingAs($this->reviewer)->post(route('hr.office-supply-process.kpi.confirm', $vpp), $this->vppPass());
        $this->seedOperationAssets(1);
        $this->inventory(['due_at' => '2026-10-07 17:00']);
        $this->incident(['handler_id' => $this->hcns->id, 'status' => 'closed', 'level' => 2, 'due_at' => '2026-10-06 17:00:00',
            'resolved_at' => '2026-10-06 15:00:00', 'first_resolved_at' => '2026-10-06 15:00:00', 'accepted_at' => '2026-10-06 16:00:00', 'accepted_by' => $this->requester->id]);
        $this->incident(['handler_id' => $this->hcns->id, 'company_id' => EgoCompanyLock::id() + 1000, 'status' => 'closed', 'level' => 2,
            'due_at' => '2026-10-06 17:00:00', 'resolved_at' => '2026-10-06 18:00:00']);
        DB::table('hr_vpp_requests')->insert(['code' => 'VPP-TEST-'.Str::random(6), 'department_name' => 'KD', 'status' => 'completed', 'kpi_tracked' => 0,
            'handler_id' => $this->hcns->id, 'due_at' => '2026-10-07 17:00:00', 'created_at' => now(), 'updated_at' => now()]);

        $now = Carbon::parse('2026-11-02 09:00:00');
        $rows = $this->rows($this->hcns, [], $now);
        $this->assertSame(['allocation' => 1, 'inventory' => 1, 'incident' => 1], collect($rows)->countBy('group')->all(), 'Phiếu không theo dõi và khác công ty không vào.');

        $svc = app(AssetsServiceSource::class);
        $unset = $svc->summarize($rows, self::MONTH, $now, $this->rules());
        $groups = collect($unset['groups'])->keyBy('key');
        $this->assertSame([100.0, 0.0, 100.0], [$groups['allocation']['value'], $groups['inventory']['value'], $groups['incident']['value']]);
        $this->assertNull($unset['value'], 'Chưa chốt cách gộp → không có tỷ lệ chung.');
        $this->assertStringContainsString('H-D04', $unset['combined_reason']);
        $this->assertSame(66.67, $svc->summarize($rows, self::MONTH, $now, $this->rules(['aggregation' => 'pooled']))['value']);
        $this->assertSame(66.67, $svc->summarize($rows, self::MONTH, $now, $this->rules(['aggregation' => 'average_groups']))['value']);

        // Bộ KPI HCNS + phiên bản quy tắc áp dụng: chưa chọn cách gộp → HC06 vẫn chấm tay; chọn gộp → lấy nguồn.
        $template = DB::table('hr_kpi_templates')->where('code', 'hcns')->first();
        $this->assertNotNull($template, 'DB test cần bộ KPI hcns.');
        $criteria = DB::table('hr_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->get();
        $assets = $criteria->firstWhere('code', 'assets');
        $versionId = DB::table('hr_kpi_rule_versions')->insertGetId(['template_code' => 'hcns', 'version' => 900 + random_int(1, 99), 'status' => 'applied',
            'effective_month' => self::MONTH, 'rules' => json_encode($this->rules()), 'created_by' => $this->director->id, 'approved_by' => $this->director->id,
            'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $evaluator = app(HrKpiEvaluator::class);
        $overrides = $evaluator->sourceOverrides($template, $criteria, (int) $this->hcns->id, self::MONTH, collect(), $now);
        $this->assertArrayNotHasKey((int) $assets->id, $overrides, 'Cách gộp chưa chốt → không ghi đè điểm nhập tay HC06.');

        $checklist = app(HcnsKpiChecklist::class)->forUser((int) $this->hcns->id, self::MONTH, $template, $criteria, collect(), $this->director, $now);
        $block = collect($checklist['blocks'])->firstWhere('code', 'assets');
        $this->assertTrue($block['auto']);
        $this->assertFalse($block['official']);
        $this->assertNotSame('source', $block['official_score']['from'], 'Điểm chính thức HC06 vẫn chấm tay.');
        $this->assertStringContainsString('H-D04', $block['note']);
        $this->assertCount(3, $block['summary']['groups']);
        $incidentRow = collect($block['rows'])->firstWhere('group', 'incident');
        $this->assertTrue($incidentRow['can_open']);
        $outsiderView = app(HcnsKpiChecklist::class)->forViewer($checklist, $this->person('sales', 'Người ngoài'));
        $this->assertFalse(collect(collect($outsiderView['blocks'])->firstWhere('code', 'assets')['rows'])->firstWhere('group', 'incident')['can_open']);

        DB::table('hr_kpi_rule_versions')->where('id', $versionId)->update(['rules' => json_encode($this->rules(['aggregation' => 'pooled']))]);
        $overrides = $evaluator->sourceOverrides($template, $criteria, (int) $this->hcns->id, self::MONTH, collect(), $now);
        $this->assertArrayHasKey((int) $assets->id, $overrides);
        $this->assertSame(66.67, $overrides[(int) $assets->id]['actual']);
        $this->assertNotNull($overrides[(int) $assets->id]['achievement']);
    }

    public function test_vpp_and_inventory_files_need_hr_page_access(): void
    {
        $id = $this->allocate();
        $this->uploadReceipt($id);
        $file = DB::table('hr_kpi_source_files')->where('source_type', VppAllocationKpi::SOURCE_TYPE)->where('source_id', $id)->first();
        $outsiderRole = 'kpi_outsider_'.Str::lower(Str::random(6));
        $outsider = $this->person($outsiderRole, 'Người ngoài');
        DB::table('roles')->where('name', $outsiderRole)->update(['page_access_enabled' => 1]);
        $this->actingAs($outsider)->get(route('hr.office-supply-process.kpi.files.show', [$id, $file->id]))->assertForbidden();
        $this->actingAs($outsider)->get(route('hr.office-supply-process.show', $id))->assertForbidden();
        $this->actingAs($this->hcns)->get(route('hr.office-supply-process.kpi.files.show', [$id, $file->id + 999999]))->assertNotFound();
    }
}
