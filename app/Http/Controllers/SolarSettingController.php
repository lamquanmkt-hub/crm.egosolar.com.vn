<?php

namespace App\Http\Controllers;

use App\Models\SolarSetting;
use Illuminate\Http\Request;

/**
 * Controller quản lý cài đặt công thức tính toán điện mặt trời.
 */
class SolarSettingController extends Controller
{
    private array $solarDefaults = [
        // Core formula
        'pvout_default' => [
            'label' => 'PVOUT mặc định (kWh/kWp/năm)',
            'group' => 'solar_formula',
            'value' => '1600',
            'type' => 'number',
            'description' => 'Dùng khi tỉnh/thành chưa có dữ liệu bức xạ/PVOUT riêng.',
        ],
        'performance_ratio' => [
            'label' => 'Hiệu suất hệ thống (PR)',
            'group' => 'solar_formula',
            'value' => '0.80',
            'type' => 'number',
            'description' => 'Thông thường từ 0.75 đến 0.85.',
        ],
        'reserve_ratio' => [
            'label' => 'Tỷ lệ dự phòng',
            'group' => 'solar_formula',
            'value' => '1.10',
            'type' => 'number',
            'description' => 'Nhân thêm để dự phòng hao hụt hoặc tăng phụ tải.',
        ],

        // Solar capex
        'cost_small' => [
            'label' => 'Chi phí < 5kWp (VNĐ/kWp)',
            'group' => 'solar_formula',
            'value' => '14000000',
            'type' => 'number',
            'description' => 'Áp dụng cho hệ nhỏ hơn hoặc bằng 5kWp.',
        ],
        'cost_medium' => [
            'label' => 'Chi phí 5 - 10kWp (VNĐ/kWp)',
            'group' => 'solar_formula',
            'value' => '12000000',
            'type' => 'number',
            'description' => 'Áp dụng cho hệ từ trên 5kWp đến 10kWp.',
        ],
        'cost_large' => [
            'label' => 'Chi phí > 10kWp (VNĐ/kWp)',
            'group' => 'solar_formula',
            'value' => '10000000',
            'type' => 'number',
            'description' => 'Áp dụng cho hệ lớn hơn 10kWp.',
        ],

        // Battery capex
        'battery_cost_per_kwh' => [
            'label' => 'Chi phí pin lưu trữ (VNĐ/kWh)',
            'group' => 'solar_formula',
            'value' => '9000000',
            'type' => 'number',
            'description' => 'Chi phí tham chiếu cho 1 kWh pin lưu trữ.',
        ],
        'battery_backup_hours_day' => [
            'label' => 'Giờ lưu trữ cho khách dùng ngày nhiều',
            'group' => 'solar_formula',
            'value' => '2',
            'type' => 'number',
            'description' => 'Giờ pin dự kiến để tối ưu phần dùng ngoài khung nắng.',
        ],
        'battery_backup_hours_balanced' => [
            'label' => 'Giờ lưu trữ cho khách cân bằng',
            'group' => 'solar_formula',
            'value' => '4',
            'type' => 'number',
            'description' => 'Giờ pin dự kiến cho phụ tải cân bằng ngày/đêm.',
        ],
        'battery_backup_hours_night' => [
            'label' => 'Giờ lưu trữ cho khách dùng đêm nhiều',
            'group' => 'solar_formula',
            'value' => '6',
            'type' => 'number',
            'description' => 'Giờ pin dự kiến cho khách chủ yếu dùng đêm.',
        ],

        // Hardware
        'panel_power_wp' => [
            'label' => 'Công suất 1 tấm pin (Wp)',
            'group' => 'solar_formula',
            'value' => '585',
            'type' => 'number',
            'description' => 'Dùng để gợi ý số lượng tấm pin.',
        ],
        'panel_area_m2' => [
            'label' => 'Diện tích 1 tấm pin (m²)',
            'group' => 'solar_formula',
            'value' => '2.6',
            'type' => 'number',
            'description' => 'Dùng để ước tính diện tích mái cần thiết.',
        ],

        // Financial
        'analysis_years' => [
            'label' => 'Số năm phân tích',
            'group' => 'solar_formula',
            'value' => '25',
            'type' => 'number',
            'description' => 'Thời gian phân tích tài chính.',
        ],
        'electricity_price_growth' => [
            'label' => 'Tăng giá điện mỗi năm',
            'group' => 'solar_formula',
            'value' => '0.03',
            'type' => 'number',
            'description' => 'Ví dụ 0.03 = 3%/năm.',
        ],
        'year1_loss' => [
            'label' => 'Hao hụt năm đầu',
            'group' => 'solar_formula',
            'value' => '0.02',
            'type' => 'number',
            'description' => 'Ví dụ 0.02 = hao hụt 2% năm đầu.',
        ],
        'yearly_degradation' => [
            'label' => 'Hao hụt các năm sau',
            'group' => 'solar_formula',
            'value' => '0.005',
            'type' => 'number',
            'description' => 'Ví dụ 0.005 = hao hụt 0.5%/năm.',
        ],
        'export_price' => [
            'label' => 'Giá điện dư (VNĐ/kWh)',
            'group' => 'solar_formula',
            'value' => '1500',
            'type' => 'number',
            'description' => 'Giá trị tham chiếu để tính phần điện dư.',
        ],

        // Usage behavior
        'usage_day_ratio_high' => [
            'label' => 'Tự dùng - khách dùng ngày nhiều',
            'group' => 'solar_formula',
            'value' => '0.85',
            'type' => 'number',
            'description' => 'Tỷ lệ tự dùng cho khách chủ yếu dùng ban ngày.',
        ],
        'usage_balanced_ratio' => [
            'label' => 'Tự dùng - khách cân bằng',
            'group' => 'solar_formula',
            'value' => '0.70',
            'type' => 'number',
            'description' => 'Tỷ lệ tự dùng cho khách dùng điện cân bằng ngày/đêm.',
        ],
        'usage_night_ratio_high' => [
            'label' => 'Tự dùng - khách dùng đêm nhiều',
            'group' => 'solar_formula',
            'value' => '0.40',
            'type' => 'number',
            'description' => 'Tỷ lệ tự dùng cho khách chủ yếu dùng ban đêm.',
        ],

        // System type multipliers
        'system_on_grid_ratio' => [
            'label' => 'Hệ số tự dùng - bám tải',
            'group' => 'solar_formula',
            'value' => '1.00',
            'type' => 'number',
            'description' => 'Hệ bám tải không pin.',
        ],
        'system_hybrid_ratio' => [
            'label' => 'Hệ số tự dùng - hybrid',
            'group' => 'solar_formula',
            'value' => '1.20',
            'type' => 'number',
            'description' => 'Hybrid giúp tăng tỷ lệ tự dùng nhờ pin.',
        ],
        'system_battery_ratio' => [
            'label' => 'Hệ số tự dùng - lưu trữ mạnh',
            'group' => 'solar_formula',
            'value' => '1.40',
            'type' => 'number',
            'description' => 'Hệ pin mạnh giúp tăng đáng kể tỷ lệ tự dùng.',
        ],

        // Electricity price modes
        'electricity_mode_default' => [
            'label' => 'Nhóm giá điện mặc định',
            'group' => 'solar_formula',
            'value' => 'residential',
            'type' => 'text',
            'description' => 'residential | business | production | public',
        ],

        'residential_bac_1' => [
            'label' => 'Sinh hoạt bậc 1',
            'group' => 'solar_formula',
            'value' => '1893',
            'type' => 'number',
            'description' => '0-50 kWh',
        ],
        'residential_bac_2' => [
            'label' => 'Sinh hoạt bậc 2',
            'group' => 'solar_formula',
            'value' => '1956',
            'type' => 'number',
            'description' => '51-100 kWh',
        ],
        'residential_bac_3' => [
            'label' => 'Sinh hoạt bậc 3',
            'group' => 'solar_formula',
            'value' => '2271',
            'type' => 'number',
            'description' => '101-200 kWh',
        ],
        'residential_bac_4' => [
            'label' => 'Sinh hoạt bậc 4',
            'group' => 'solar_formula',
            'value' => '2860',
            'type' => 'number',
            'description' => '201-300 kWh',
        ],
        'residential_bac_5' => [
            'label' => 'Sinh hoạt bậc 5',
            'group' => 'solar_formula',
            'value' => '3197',
            'type' => 'number',
            'description' => '301-400 kWh',
        ],
        'residential_bac_6' => [
            'label' => 'Sinh hoạt bậc 6',
            'group' => 'solar_formula',
            'value' => '3302',
            'type' => 'number',
            'description' => '401+ kWh',
        ],
        'business_price_normal_low_voltage' => [
            'label' => 'Kinh doanh - giờ bình thường',
            'group' => 'solar_formula',
            'value' => '3007',
            'type' => 'number',
            'description' => 'Giá tham chiếu nhóm kinh doanh.',
        ],
        'production_price_normal_low_voltage' => [
            'label' => 'Sản xuất - giờ bình thường',
            'group' => 'solar_formula',
            'value' => '1896',
            'type' => 'number',
            'description' => 'Giá tham chiếu nhóm sản xuất.',
        ],
        'public_price_low_voltage' => [
            'label' => 'Hành chính sự nghiệp',
            'group' => 'solar_formula',
            'value' => '2124',
            'type' => 'number',
            'description' => 'Giá tham chiếu nhóm hành chính sự nghiệp.',
        ],
    ];

    /**
     * Hiển thị trang cài đặt Solar sau khi đồng bộ giá trị mặc định.
     */
    public function index()
    {
        $this->syncDefaults();

        $settings = SolarSetting::where('group', 'solar_formula')
            ->orderBy('id')
            ->get();

        return view('solar.settings', compact('settings'));
    }

    /**
     * Lưu các cài đặt Solar từ form.
     */
    public function update(Request $request)
    {
        $this->syncDefaults();

        $request->validate([
            'settings' => ['required', 'array'],
        ]);

        foreach ($request->input('settings', []) as $key => $value) {
            SolarSetting::where('key', $key)->update([
                'value' => is_array($value) ? json_encode($value) : $value,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã lưu cài đặt Solar thành công.',
            ]);
        }

        return back()->with('success', 'Đã lưu cài đặt Solar thành công.');
    }

    /**
     * Tạo/cập nhật các cài đặt mặc định vào database.
     */
    private function syncDefaults(): void
    {
        foreach ($this->solarDefaults as $key => $item) {
            SolarSetting::updateOrCreate(
                ['key' => $key],
                [
                    'label' => $item['label'],
                    'group' => $item['group'],
                    'value' => $item['value'],
                    'type' => $item['type'],
                    'description' => $item['description'],
                ]
            );
        }
    }
}
