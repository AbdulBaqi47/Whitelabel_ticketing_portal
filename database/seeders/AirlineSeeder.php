<?php

namespace Database\Seeders;

use App\Models\Airline;
use Illuminate\Database\Seeder;

class AirlineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $airlines = config('airlines.airlines');
        foreach ($airlines as $airline) {
            $param['name'] = $airline['name'];
            $param['thumbnail'] = $airline['thumbnail'];
            $param['iata_desi'] = $airline['iata_desi'];
            $param['iata_code'] = $airline['iata_code'];
            $param['icao_code'] = $airline['icao_code'];
            $param['status'] = 1;
            $param['country_id'] = $airline['country_id'];
            $param['issuing_pcc'] = $airline['issuing_pcc'];
            $param['reserving_pcc'] = $airline['reserving_pcc'];
            $param['tour_code'] = $airline['tour_code'];
            $param['preferred_connector'] = $airline['preferred_connector'];
            if (!Airline::where('name', $airline['name'])->exists()) {
                Airline::create($param);
                dump($param['name'] . ' created');
            }else{
                dump($param['name'] . ' already exists in system');
            }
           
        }
    }
}
