<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmWarehousesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_warehouses')->insert([
		    ['name' => 'Kho Hà Nội', 'location' => 'Số 12 Láng Hạ, Hà Nội', 'manager_id' => 1],
		    ['name' => 'Kho Đà Nẵng', 'location' => '25 Nguyễn Văn Linh, Đà Nẵng', 'manager_id' => 1],
		    ['name' => 'Kho TP.HCM', 'location' => '85 Nguyễn Huệ, Q.1, TP.HCM', 'manager_id' => 1],
	    ]);
    }
}
