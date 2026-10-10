<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ConnectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Connector::create([
            'name'=>'Sabre',
            'type'=>'SABRE',
            'api_key'=>'715063',
            'api_secret'=>'SSWRES99',
            'connector_domain'=>'AA',
            'pcc'=>'JJ7L',
            'printer'=>'BCE5F4',
            'is_enable'=>true,
            'supplier_id'=>1
        ]);

        \App\Models\Connector::create([
            'name'=>'Mock Flights (Demo)',
            'type'=>'MOCK',
            'api_key'=>'mock_key',
            'api_secret'=>'mock_secret',
            'is_enable'=>false,
            'supplier_id'=>1
        ]);
    }
}
