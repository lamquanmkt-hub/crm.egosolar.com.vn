<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmPaymentMethodsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_payment_methods')->insert([
		    ['method_name' => 'Tiền mặt', 'description' => 'Thanh toán trực tiếp bằng tiền mặt'],
		    ['method_name' => 'Chuyển khoản', 'description' => 'Thanh toán qua ngân hàng'],
		    ['method_name' => 'Thẻ tín dụng', 'description' => 'Thanh toán bằng thẻ Visa/Master'],
	    ]);
    }
}
