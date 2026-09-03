<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = config('countries.countries');
        foreach ($countries as $country) {
            $param['name'] = $country['name'];
            $param['nice_name'] = $country['nice_name'];
            $param['iso'] = $country['iso'];
            $param['iso3'] = $country['iso3'];
            $param['status'] = 1;
            if (!Country::where('name', $country['name'])->exists()) {
                Country::create($param);
                dump($param['name'] . ' created');
            }else{
                dump($param['name'] . ' already exists in system');
            }
           
        }
    }
}
