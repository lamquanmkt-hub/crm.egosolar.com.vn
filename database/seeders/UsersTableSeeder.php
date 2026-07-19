<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
//	    DB::table('users')->truncate();

	    DB::table('users')->insert([
		    [
			    'name' => 'Admin System',
			    'email' => 'admin@egosolar.vn',
			    'password' => Hash::make('admin123'),
			    'email_verified_at' => now(),
			    'created_at' => now(),
			    'updated_at' => now(),
		    ],
		    [
			    'name' => 'Marketing Team',
			    'email' => 'marketing@egosolar.vn',
			    'password' => Hash::make('marketing123'),
			    'email_verified_at' => now(),
			    'created_at' => now(),
			    'updated_at' => now(),
		    ],
		    [
			    'name' => 'Sales Team',
			    'email' => 'sales@egosolar.vn',
			    'password' => Hash::make('sales123'),
			    'email_verified_at' => now(),
			    'created_at' => now(),
			    'updated_at' => now(),
		    ],
		    [
			    'name' => 'Ketoan Team',
			    'email' => 'ketoan@egosolar.vn',
			    'password' => Hash::make('ketoan123'),
			    'email_verified_at' => now(),
			    'created_at' => now(),
			    'updated_at' => now(),
		    ],
	    ]);
    }
}
