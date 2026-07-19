<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmRegionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_regions')->insert([
		    ['name' => 'Miền Bắc'],
		    ['name' => 'Miền Trung'],
		    ['name' => 'Miền Nam'],
	    ]);
    }
}
