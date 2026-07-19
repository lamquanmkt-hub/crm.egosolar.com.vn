<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SolarProvince;
use App\Models\SolarRegionProfile;

class SolarProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = SolarRegionProfile::pluck('id', 'code');

        $rows = [
            ['name' => 'Hà Nội', 'region' => 'Đông Bắc', 'region_profile_id' => $profiles['dong_bac'] ?? null],
            ['name' => 'Hải Phòng', 'region' => 'Đông Bắc', 'region_profile_id' => $profiles['dong_bac'] ?? null],
            ['name' => 'Quảng Ninh', 'region' => 'Đông Bắc', 'region_profile_id' => $profiles['dong_bac'] ?? null],

            ['name' => 'Lào Cai', 'region' => 'Tây Bắc', 'region_profile_id' => $profiles['tay_bac'] ?? null],
            ['name' => 'Sơn La', 'region' => 'Tây Bắc', 'region_profile_id' => $profiles['tay_bac'] ?? null],
            ['name' => 'Điện Biên', 'region' => 'Tây Bắc', 'region_profile_id' => $profiles['tay_bac'] ?? null],
            ['name' => 'Hòa Bình', 'region' => 'Tây Bắc', 'region_profile_id' => $profiles['tay_bac'] ?? null],
            ['name' => 'Yên Bái', 'region' => 'Tây Bắc', 'region_profile_id' => $profiles['tay_bac'] ?? null],
            ['name' => 'Lai Châu', 'region' => 'Tây Bắc', 'region_profile_id' => $profiles['tay_bac'] ?? null],

            ['name' => 'Thanh Hóa', 'region' => 'Bắc Trung Bộ', 'region_profile_id' => $profiles['bac_trung_bo'] ?? null],
            ['name' => 'Nghệ An', 'region' => 'Bắc Trung Bộ', 'region_profile_id' => $profiles['bac_trung_bo'] ?? null],
            ['name' => 'Hà Tĩnh', 'region' => 'Bắc Trung Bộ', 'region_profile_id' => $profiles['bac_trung_bo'] ?? null],
            ['name' => 'Quảng Bình', 'region' => 'Bắc Trung Bộ', 'region_profile_id' => $profiles['bac_trung_bo'] ?? null],
            ['name' => 'Quảng Trị', 'region' => 'Bắc Trung Bộ', 'region_profile_id' => $profiles['bac_trung_bo'] ?? null],
            ['name' => 'Huế', 'region' => 'Bắc Trung Bộ', 'region_profile_id' => $profiles['bac_trung_bo'] ?? null],

            ['name' => 'Đà Nẵng', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Quảng Nam', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Quảng Ngãi', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Bình Định', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Phú Yên', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Khánh Hòa', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Ninh Thuận', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Bình Thuận', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Kon Tum', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Gia Lai', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Đắk Lắk', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Đắk Nông', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],
            ['name' => 'Lâm Đồng', 'region' => 'Tây Nguyên & Nam Trung Bộ', 'region_profile_id' => $profiles['tay_nguyen_nam_trung_bo'] ?? null],

            ['name' => 'TP Hồ Chí Minh', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Bình Dương', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Đồng Nai', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Long An', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Tây Ninh', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Bà Rịa - Vũng Tàu', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Cần Thơ', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'An Giang', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Kiên Giang', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Cà Mau', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Sóc Trăng', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Bạc Liêu', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Trà Vinh', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Vĩnh Long', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Tiền Giang', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
            ['name' => 'Bến Tre', 'region' => 'Nam Bộ', 'region_profile_id' => $profiles['nam_bo'] ?? null],
        ];

        foreach ($rows as $row) {
            SolarProvince::updateOrCreate(
                ['name' => $row['name']],
                $row
            );
        }
    }
}