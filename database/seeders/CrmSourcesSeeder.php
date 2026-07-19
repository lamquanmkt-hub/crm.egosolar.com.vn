<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_sources')->insert([
		    ['name' => 'Facebook', 'description' => 'Khách đến từ quảng cáo Facebook'],
		    ['name' => 'Google Ads', 'description' => 'Khách tìm kiếm qua Google'],
		    ['name' => 'Zalo', 'description' => 'Liên hệ qua Zalo'],
		    ['name' => 'Giới thiệu', 'description' => 'Từ khách hàng cũ giới thiệu'],
	    ]);
    }
}
