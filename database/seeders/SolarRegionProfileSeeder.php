<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SolarRegionProfile;

class SolarRegionProfileSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'dong_bac',
                'name' => 'Đông Bắc',
                'irradiation_min' => 3.3,
                'irradiation_max' => 4.1,
                'irradiation_default' => 3.7,
                'sun_hours_min' => 1600,
                'sun_hours_max' => 1750,
            ],
            [
                'code' => 'tay_bac',
                'name' => 'Tây Bắc',
                'irradiation_min' => 4.1,
                'irradiation_max' => 4.9,
                'irradiation_default' => 4.5,
                'sun_hours_min' => 1750,
                'sun_hours_max' => 1800,
            ],
            [
                'code' => 'bac_trung_bo',
                'name' => 'Bắc Trung Bộ',
                'irradiation_min' => 4.6,
                'irradiation_max' => 5.2,
                'irradiation_default' => 4.9,
                'sun_hours_min' => 1700,
                'sun_hours_max' => 2000,
            ],
            [
                'code' => 'tay_nguyen_nam_trung_bo',
                'name' => 'Tây Nguyên & Nam Trung Bộ',
                'irradiation_min' => 4.9,
                'irradiation_max' => 5.7,
                'irradiation_default' => 5.3,
                'sun_hours_min' => 2000,
                'sun_hours_max' => 2600,
            ],
            [
                'code' => 'nam_bo',
                'name' => 'Nam Bộ',
                'irradiation_min' => 4.3,
                'irradiation_max' => 4.9,
                'irradiation_default' => 4.6,
                'sun_hours_min' => 2200,
                'sun_hours_max' => 2500,
            ],
        ];

        foreach ($rows as $row) {
            SolarRegionProfile::updateOrCreate(
                ['code' => $row['code']],
                $row
            );
        }
    }
}