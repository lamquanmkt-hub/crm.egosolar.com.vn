<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmInventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_product_stock')->insert([
		    ['product_id' => 1, 'warehouse_id' => 7, 'qty' => 50],
		    ['product_id' => 2, 'warehouse_id' => 7, 'qty' => 20],
		    ['product_id' => 3, 'warehouse_id' => 7, 'qty' => 10],
		    ['product_id' => 4, 'warehouse_id' => 8, 'qty' => 30],
	    ]);

	    DB::table('crm_stock_movements')->insert([
		    [
			    'product_id' => 1,
			    'warehouse_id' => 7,
			    'change_qty' => -2,
			    'reason' => 'Đặt hàng ORD-0001',
			    'reference_id' => 1,
			    'created_by' => 1,
		    ],
	    ]);
    }
}
