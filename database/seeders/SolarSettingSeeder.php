<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SolarSetting;

class SolarSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'avg_electricity_price',
                'label' => 'Giá điện bình quân (VNĐ/kWh)',
                'group' => 'solar_formula',
                'value' => '3000',
                'type' => 'number',
                'description' => 'Dùng để quy đổi từ tiền điện sang sản lượng điện tiêu thụ.',
            ],
            [
                'key' => 'performance_ratio',
                'label' => 'Hiệu suất hệ thống',
                'group' => 'solar_formula',
                'value' => '0.80',
                'type' => 'number',
                'description' => 'Hiệu suất thực tế sau tổn hao hệ thống.',
            ],
            [
                'key' => 'reserve_ratio',
                'label' => 'Tỷ lệ dự phòng',
                'group' => 'solar_formula',
                'value' => '1.10',
                'type' => 'number',
                'description' => 'Tăng thêm công suất để dự phòng hao hụt và tăng trưởng nhu cầu.',
            ],
            [
                'key' => 'cost_per_kwp',
                'label' => 'Chi phí đầu tư trung bình (VNĐ/kWp)',
                'group' => 'solar_formula',
                'value' => '12000000',
                'type' => 'number',
                'description' => 'Chi phí đầu tư trung bình trên mỗi kWp.',
            ],
            [
                'key' => 'panel_power_wp',
                'label' => 'Công suất 1 tấm pin (Wp)',
                'group' => 'solar_formula',
                'value' => '585',
                'type' => 'number',
                'description' => 'Dùng để gợi ý số lượng tấm pin.',
            ],
            [
                'key' => 'panel_area_m2',
                'label' => 'Diện tích 1 tấm pin (m2)',
                'group' => 'solar_formula',
                'value' => '2.6',
                'type' => 'number',
                'description' => 'Dùng để tính diện tích mái dự kiến.',
            ],
            [
                'key' => 'yearly_degradation',
                'label' => 'Tỷ lệ hao hụt mỗi năm',
                'group' => 'solar_formula',
                'value' => '0.005',
                'type' => 'number',
                'description' => 'Ví dụ 0.005 = 0.5%/năm.',
            ],
            [
                'key' => 'analysis_years',
                'label' => 'Số năm phân tích',
                'group' => 'solar_formula',
                'value' => '25',
                'type' => 'number',
                'description' => 'Số năm dùng để phân tích sản lượng và hoàn vốn.',
            ],
            [
                'key' => 'electricity_price_growth',
                'label' => 'Tăng giá điện mỗi năm',
                'group' => 'solar_formula',
                'value' => '0.03',
                'type' => 'number',
                'description' => 'Ví dụ 0.03 = tăng 3%/năm.',
            ],
            [
    'key' => 'electricity_mode_default',
    'label' => 'Loại giá điện mặc định',
    'group' => 'solar_formula',
    'value' => 'residential',
    'type' => 'text',
    'description' => 'residential | business | production | public',
],
[
    'key' => 'business_price_normal_low_voltage',
    'label' => 'Kinh doanh - giờ bình thường - dưới 6kV',
    'group' => 'solar_formula',
    'value' => '3007',
    'type' => 'number',
    'description' => 'Theo biểu giá tham khảo.',
],
[
    'key' => 'production_price_normal_low_voltage',
    'label' => 'Sản xuất - giờ bình thường - dưới 6kV',
    'group' => 'solar_formula',
    'value' => '1896',
    'type' => 'number',
    'description' => 'Theo biểu giá tham khảo.',
],
[
    'key' => 'public_price_low_voltage',
    'label' => 'Hành chính sự nghiệp - dưới 6kV',
    'group' => 'solar_formula',
    'value' => '2124',
    'type' => 'number',
    'description' => 'Chiếu sáng công cộng / đơn vị hành chính sự nghiệp.',
],
[
    'key' => 'residential_bac_1',
    'label' => 'Sinh hoạt bậc 1',
    'group' => 'solar_formula',
    'value' => '1893',
    'type' => 'number',
    'description' => '0-50 kWh',
],
[
    'key' => 'residential_bac_2',
    'label' => 'Sinh hoạt bậc 2',
    'group' => 'solar_formula',
    'value' => '1956',
    'type' => 'number',
    'description' => '51-100 kWh',
],
[
    'key' => 'residential_bac_3',
    'label' => 'Sinh hoạt bậc 3',
    'group' => 'solar_formula',
    'value' => '2271',
    'type' => 'number',
    'description' => '101-200 kWh',
],
[
    'key' => 'residential_bac_4',
    'label' => 'Sinh hoạt bậc 4',
    'group' => 'solar_formula',
    'value' => '2860',
    'type' => 'number',
    'description' => '201-300 kWh',
],
[
    'key' => 'residential_bac_5',
    'label' => 'Sinh hoạt bậc 5',
    'group' => 'solar_formula',
    'value' => '3197',
    'type' => 'number',
    'description' => '301-400 kWh',
],
[
    'key' => 'residential_bac_6',
    'label' => 'Sinh hoạt bậc 6',
    'group' => 'solar_formula',
    'value' => '3302',
    'type' => 'number',
    'description' => '401+ kWh',
],
        ];

        foreach ($settings as $item) {
            SolarSetting::updateOrCreate(
                ['key' => $item['key']],
                $item
            );
        }
    }
}