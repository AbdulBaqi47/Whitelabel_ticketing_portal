<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Supplier::create([
        'name'=>'BSP IATA FLIGHT BOOKING SUPPLIER',
        'slug'=>'bsp-iata-flight-booking-supplier',
        'description'=>'BSP IATA FLIGHT BOOKING SUPPLIER',
        'status'=>true,
    ]);
    }
}
