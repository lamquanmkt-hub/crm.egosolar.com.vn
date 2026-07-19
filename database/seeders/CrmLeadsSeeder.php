<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmLeadsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
	    DB::table('crm_leads')->insert([
		    [
			    'customer_id' => 1,
			    'source_id' => 1,
			    'assigned_to' => 1,
			    'contact_date' => now()->subDays(2),
			    'status_id' => 2,
			    'note' => 'Khách quan tâm hệ 3kWp',
			    'marketing_note' => 'Có tiềm năng cao',
			    'created_by' => 1,
		    ],
		    [
			    'customer_id' => 2,
			    'source_id' => 2,
			    'assigned_to' => 1,
			    'contact_date' => now()->subDay(),
			    'status_id' => 1,
			    'note' => 'Khách cần hệ 10kWp',
			    'marketing_note' => null,
			    'created_by' => 1,
		    ],
	    ]);
    }
}
