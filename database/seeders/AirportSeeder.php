<?php

namespace Database\Seeders;

use App\Models\Airport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AirportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $airports = config('airports.airports');
        foreach ($airports as $airport) {
            $param['ident'] = $airport['ident'];
            $param['name'] = $airport['name'];
            $param['coordinates'] = $airport['coordinates'];
            $param['continent'] = $airport['continent'];
            $param['iso_country'] = $airport['iso_country'];
            $param['municipality'] = $airport['municipality'];
            $param['gps_code'] = $airport['gps_code'];
            $param['iata_code'] = $airport['iata_code'];
            $param['local_code'] = $airport['local_code'];
            $param['country'] = ucfirst(strtolower($airport['country']));
            if (!Airport::where('name', $airport['name'])->exists()) {
                Airport::create($param);
                dump($param['name'] . ' created');
            } else {
                dump($param['name'] . ' already exists in system');
            }
        }
    }
}
