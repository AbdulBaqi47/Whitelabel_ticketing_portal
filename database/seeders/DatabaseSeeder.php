<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            HeadOfficeSeeder::class,
            AirlineSeeder::class,
            AirportSeeder::class,
            CountriesSeeder::class,
            ConnectorSeeder::class,
            SupplierSeeder::class
        ]);
    }
}
