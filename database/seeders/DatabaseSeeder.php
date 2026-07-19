<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

//        User::factory()->create([
//            'name' => 'Test User',
//            'email' => 'test@example.com',
//        ]);
	    $this->call([
//		    CrmRegionsSeeder::class,
//		    CrmSourcesSeeder::class,
//		    CrmLeadStatusesSeeder::class,
//		    CrmPaymentMethodsSeeder::class,

//		    CrmWarehousesSeeder::class,
//		    CrmProductCatalogSeeder::class,
//		    CrmCustomersSeeder::class,
//		    CrmLeadsSeeder::class,
//		    CrmOrdersSeeder::class,
//		    CrmInventorySeeder::class,
//		    UsersTableSeeder::class
		    RolePermissionSeeder::class
//		    CustomerTypeSeeder::class
	    ]);
    }
}
