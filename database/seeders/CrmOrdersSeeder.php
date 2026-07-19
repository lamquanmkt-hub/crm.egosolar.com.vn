<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmOrdersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
//	    DB::table('crm_orders')->insert([
//		    [
//			    'lead_id' => 1,
//			    'order_code' => 'ORD-0001',
//			    'order_date' => now()->toDateString(),
//			    'status' => 'PENDING_APPROVAL',
//			    'warehouse_id' => 7,
//			    'total_amount' => 2500000 + 8500000,
//			    'created_by' => 1,
//			    'payment_recorded' => false,
//		    ],
//	    ]);

	    DB::table('crm_order_items')->insert([
		    [
			    'order_id' => 2,
			    'product_id' => 1,
			    'quantity' => 2,
			    'unit_price' => 2500000,
		    ],
		    [
			    'order_id' => 2,
			    'product_id' => 2,
			    'quantity' => 1,
			    'unit_price' => 8500000,
		    ],
	    ]);
    }
}
