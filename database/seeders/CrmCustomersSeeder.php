<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class CrmCustomersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_customers')->insert([
		    [
			    'name' => 'Nguyễn Văn A',
			    'phone' => '0901234567',
			    'facebook_name' => 'Nguyen Van A',
			    'facebook_link' => 'https://facebook.com/nguyenvana',
			    'zalo_id' => 'zalo_a',
			    'customer_type' => 'Cá nhân',
			    'region_id' => 1,
		    ],
		    [
			    'name' => 'Công ty TNHH B Mặt Trời',
			    'phone' => '0912345678',
			    'facebook_name' => 'Cong ty B Mat Troi',
			    'facebook_link' => null,
			    'zalo_id' => null,
			    'customer_type' => 'Doanh nghiệp',
			    'region_id' => 3,
		    ],
	    ]);
    }
}
