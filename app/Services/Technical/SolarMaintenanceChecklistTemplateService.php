<?php

namespace App\Services\Technical;

use App\Models\SolarMaintenanceChecklistTemplate;
use App\Models\SolarMaintenanceSchedule;
use App\Support\EgoCompanyScope;
use Illuminate\Support\Collection;

class SolarMaintenanceChecklistTemplateService
{
    public const GROUPS = [
        'periodic' => 'Bảo trì / bảo hành định kỳ',
        'incident' => 'Xử lý sự cố',
    ];

    private const DEFAULTS = [
        'periodic' => [
            ['panel_visual', 'Kiểm tra ngoại quan tấm pin'],
            ['panel_cleaning', 'Vệ sinh / kiểm tra bề mặt tấm pin'],
            ['dc_mc4', 'Kiểm tra dây DC và đầu nối MC4'],
            ['inverter', 'Kiểm tra inverter và cảnh báo'],
            ['ac_dc_cabinet', 'Kiểm tra tủ điện AC/DC và SPD'],
            ['grounding', 'Kiểm tra tiếp địa / an toàn điện'],
            ['monitoring', 'Kiểm tra monitoring / kết nối dữ liệu'],
            ['production', 'Ghi nhận sản lượng / thông số vận hành'],
            ['before_after', 'Chụp ảnh trước và sau bảo trì'],
        ],
        'incident' => [
            ['incident_verify', 'Xác minh hiện tượng / lỗi khách hàng phản ánh'],
            ['power_connection', 'Kiểm tra nguồn điện và kết nối hệ thống'],
            ['incident_inverter', 'Kiểm tra inverter / thiết bị liên quan'],
            ['incident_logs', 'Kiểm tra cảnh báo / monitoring / log lỗi'],
            ['root_cause', 'Xác định nguyên nhân và phương án xử lý'],
            ['incident_evidence', 'Chụp ảnh / ghi nhận minh chứng sau xử lý'],
        ],
    ];

    public function currentCompanyId(): ?int
    {
        $companyId = (int) EgoCompanyScope::currentId();

        return $companyId > 0 ? $companyId : null;
    }

    public function groupForType(?string $type): string
    {
        return $type === 'incident' ? 'incident' : 'periodic';
    }

    public function ensureDefaults(?int $companyId, ?int $actorId = null): void
    {
        foreach (self::DEFAULTS as $group => $items) {
            $existing = SolarMaintenanceChecklistTemplate::withTrashed()
                ->where('maintenance_type', $group)
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->when(! $companyId, fn ($query) => $query->whereNull('company_id'))
                ->exists();

            if ($existing) {
                continue;
            }

            foreach ($items as $index => [$key, $label]) {
                SolarMaintenanceChecklistTemplate::create([
                    'company_id' => $companyId,
                    'maintenance_type' => $group,
                    'item_key' => $key,
                    'label' => $label,
                    'sort_order' => ($index + 1) * 10,
                    'is_required' => true,
                    'requires_evidence' => true,
                    'min_evidence' => 1,
                    'is_active' => true,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            }
        }
    }

    public function templatesForSchedule(
        SolarMaintenanceSchedule $schedule
    ): Collection {
        $group = $this->groupForType(
            $schedule->type
        );

        /*
         * PERIODIC:
         * dùng 1 bộ checklist GLOBAL cho toàn bộ công trình.
         *
         * INCIDENT:
         * vẫn giữ theo company.
         */
        if ($group === 'periodic') {
            return SolarMaintenanceChecklistTemplate::query()
                ->whereNull('company_id')
                ->where('maintenance_type', 'periodic')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        $companyId =
            (int) ($schedule->company_id ?: 0)
            ?: null;

        $this->ensureDefaults($companyId);

        return $this->queryForCompany(
            $companyId
        )
            ->where(
                'maintenance_type',
                $group
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
    public function settings(?int $companyId, string $group): Collection
    {
        $this->ensureDefaults($companyId, auth()->id());

        return $this->queryForCompany($companyId)
            ->where('maintenance_type', $group)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function queryForCompany(?int $companyId)
    {
        return SolarMaintenanceChecklistTemplate::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when(! $companyId, fn ($query) => $query->whereNull('company_id'));
    }
}
