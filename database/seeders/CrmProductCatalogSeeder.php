<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmProductCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_product_catalog')->insert([
		    ['name' => 'Tấm pin năng lượng 450W', 'sku' => 'SP450', 'unit' => 'Tấm', 'price' => 2500000],
		    ['name' => 'Inverter 5kW', 'sku' => 'INV5KW', 'unit' => 'Cái', 'price' => 8500000],
		    ['name' => 'Ắc quy lưu trữ 12V-200Ah', 'sku' => 'BAT200', 'unit' => 'Cái', 'price' => 3800000],
		    ['name' => 'Khung nhôm lắp đặt', 'sku' => 'FRAME', 'unit' => 'Bộ', 'price' => 1200000],
	    ]);
    }
}
