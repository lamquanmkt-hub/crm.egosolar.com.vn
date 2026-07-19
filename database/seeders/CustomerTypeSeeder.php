<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_customer_types')->insert([
		    ['name' => 'Cá nhân', 'description' => null],
		    ['name' => 'Đại lý', 'description' => null],
		    ['name' => 'Dự án', 'description' => null],
		    ['name' => 'Lắp mới', 'description' => null],
		    ['name' => 'Mở rộng', 'description' => null],
	    ]);
    }
}
