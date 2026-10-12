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
        $connectors = [
            [
                'name' => 'Sabre',
                'type' => 'SABRE',
                'environment' => 'UAT',
                'api_key' => '715063',
                'api_secret' => 'SSWRES99',
                'connector_domain' => 'AA',
                'pcc' => 'JJ7L',
                'printer' => 'BCE5F4',
                'iata' => json_encode(['emirates' => false, 'qatar_airways' => false, 'saudi_airline' => false]),
                'is_enable' => false,
                'supplier_id' => 1,
            ],
            [
                'name' => 'PIA (HITIT)',
                'type' => 'PIA_HITIT',
                'environment' => 'UAT',
                'api_key' => 'A683TW70',
                'api_secret' => 'Geobtk@009',
                'client_ip' => '182.191.83.168',
                'is_enable' => false,
                'supplier_id' => 1,
            ],
            [
                'name' => 'FLYDUBAI_API',
                'type' => 'FLYDUBAI_API',
                'environment' => 'UAT',
                'api_key' => 'GEOGOT_FZ_P',
                'api_secret' => 'E450EF792EO777695C3EB5D64704AE1C',
                'connector_domain' => 'apigeogot',
                'pcc' => 'Pakistan@789',
                'is_enable' => false,
                'supplier_id' => 1,
            ],
            [
                'name' => 'AIRBLUE_API',
                'type' => 'AIRBLUE_API',
                'environment' => 'UAT',
                'api_key' => 'GeoGroupOTA',
                'api_secret' => 'GMjBCtwC7@B3T5F',
                'connector_domain' => '2045/F4AC0CC3C14D2193A082A486F9A723BF0A',
                'is_enable' => false,
                'supplier_id' => 1,
            ],
            [
                'name' => 'ONEAPI',
                'type' => 'ONEAPI',
                'environment' => 'UAT',
                'api_key' => 'AMANTRAG9',
                'api_secret' => 'P@ss1234',
                'is_enable' => false,
                'supplier_id' => 1,
            ],
            [
                'name' => 'Sabre NDC',
                'type' => 'SABRE_NDC',
                'environment' => 'UAT',
                'api_key' => '715063',
                'api_secret' => 'SSWRES99',
                'connector_domain' => 'AA',
                'pcc' => 'JJ7L',
                'printer' => 'BCE5F4',
                'iata' => json_encode(['emirates' => false, 'qatar_airways' => false, 'saudi_airline' => false]),
                'is_enable' => false,
                'supplier_id' => 1,
            ],
            [
                'name' => 'Mock Flights (Demo)',
                'type' => 'MOCK',
                'environment' => 'UAT',
                'api_key' => 'mock_key',
                'api_secret' => 'mock_secret',
                'is_enable' => false,
                'supplier_id' => 1,
            ],
        ];

        foreach ($connectors as $data) {
            \App\Models\Connector::updateOrCreate(
                ['type' => $data['type']],
                $data
            );
        }
    }
}
