<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Run order matters — each seeder depends on data from the previous ones.
     */
    public function run(): void
    {
        $this->call([
            SystemSeeder::class,
            AdminSeeder::class,
            DemoUserSeeder::class,
            ClientSeeder::class,
            ClientContactSeeder::class,
            SalesSeeder::class,
            BranchSeeder::class,
            ClientLinkAddressSeeder::class,
            BusinessTypeSeeder::class,
            CrmSeeder::class,
            // HorizonJobTestSeeder::class,

            // Demo analytics dataset — must run last; it reuses the admin/sale/
            // trainer users created by the seeders above (see AnalyticsDemoSeeder).
            AnalyticsDemoSeeder::class,
        ]);
    }
}
