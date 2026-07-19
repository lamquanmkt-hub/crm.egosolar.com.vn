<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class CrmLeadStatusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_lead_statuses')->insert([
		    ['name' => 'Mới', 'color_code' => '#00bcd4'],
		    ['name' => 'Đang chăm sóc', 'color_code' => '#ffc107'],
		    ['name' => 'Chốt đơn', 'color_code' => '#4caf50'],
		    ['name' => 'Thất bại', 'color_code' => '#f44336'],
	    ]);
    }
}
