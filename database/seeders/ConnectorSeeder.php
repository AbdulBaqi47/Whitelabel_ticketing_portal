<?php

namespace Database\Seeders;

use App\Models\Connector;
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
                'id' => 1,
                'uuid' => '01cf18f0-3033-4778-aac2-931bbb075c59',
                'name' => 'Sabre',
                'type' => 'SABRE',
                'environment' => 'UAT',
                'api_key' => '715063',
                'api_secret' => 'REPLACE_WITH_SABRE_SECRET',
                'connector_domain' => 'AA',
                'pcc' => 'JJ7L',
                'printer' => 'BCE5F4',
                'iata' => [
                    'emirates' => true,
                    'qatar_airways' => false,
                    'saudi_airline' => false,
                ],
                'client_ip' => null,
                'is_enable' => false,
                'supplier_id' => 1,
                'created_at' => '2026-08-13 19:13:35',
                'updated_at' => '2026-10-12 00:59:34',
            ],
            [
                'id' => 2,
                'uuid' => '69a22b57-8d36-48ab-af46-bc4d9266222b',
                'name' => 'PIA (HITIT)',
                'type' => 'PIA_HITIT',
                'environment' => 'UAT',
                'api_key' => 'A683TW70',
                'api_secret' => 'REPLACE_WITH_PIA_SECRET',
                'connector_domain' => null,
                'pcc' => null,
                'printer' => null,
                'iata' => null,
                'client_ip' => '182.191.83.168',
                'is_enable' => false,
                'supplier_id' => 1,
                'created_at' => '2026-08-13 19:24:58',
                'updated_at' => '2026-10-11 00:02:05',
            ],
            [
                'id' => 3,
                'uuid' => '14117a38-548f-44df-81b6-3941eefeed4a',
                'name' => 'FLYDUBAI_API',
                'type' => 'FLYDUBAI_API',
                'environment' => 'UAT',
                'api_key' => 'GEOGOT_FZ_P',
                'api_secret' => 'REPLACE_WITH_FLYDUBAI_SECRET',
                'connector_domain' => 'apigeogot',
                'pcc' => 'REPLACE_WITH_FLYDUBAI_PCC',
                'printer' => null,
                'iata' => null,
                'client_ip' => null,
                'is_enable' => false,
                'supplier_id' => 1,
                'created_at' => '2026-08-13 19:32:26',
                'updated_at' => '2026-10-10 23:53:45',
            ],
            [
                'id' => 4,
                'uuid' => 'a334040f-2a91-40fe-b372-c21f4139920e',
                'name' => 'AIRBLUE_API',
                'type' => 'AIRBLUE_API',
                'environment' => 'UAT',
                'api_key' => 'GeoGroupOTA',
                'api_secret' => 'REPLACE_WITH_AIRBLUE_SECRET',
                'connector_domain' => 'REPLACE_WITH_AIRBLUE_DOMAIN',
                'pcc' => null,
                'printer' => null,
                'iata' => null,
                'client_ip' => null,
                'is_enable' => false,
                'supplier_id' => 1,
                'created_at' => '2026-08-13 19:34:49',
                'updated_at' => '2026-10-10 23:53:48',
            ],
            [
                'id' => 5,
                'uuid' => '9097c19e-2c1d-4665-8632-7b3c5123b632',
                'name' => 'ONEAPI',
                'type' => 'ONEAPI',
                'environment' => 'UAT',
                'api_key' => 'AMANTRAG9',
                'api_secret' => 'REPLACE_WITH_ONEAPI_SECRET',
                'connector_domain' => null,
                'pcc' => null,
                'printer' => null,
                'iata' => null,
                'client_ip' => null,
                'is_enable' => false,
                'supplier_id' => 1,
                'created_at' => '2026-08-13 19:43:47',
                'updated_at' => '2026-10-11 00:00:56',
            ],
            [
                'id' => 6,
                'uuid' => '8b6157de-a634-4d8b-b82f-e89abf1b4721',
                'name' => 'Sabre NDC',
                'type' => 'SABRE_NDC',
                'environment' => 'UAT',
                'api_key' => '715063',
                'api_secret' => 'REPLACE_WITH_SABRE_NDC_SECRET',
                'connector_domain' => 'AA',
                'pcc' => 'JJ7L',
                'printer' => 'BCE5F4',
                'iata' => [
                    'emirates' => true,
                    'qatar_airways' => false,
                    'saudi_airline' => false,
                ],
                'client_ip' => null,
                'is_enable' => true,
                'supplier_id' => 1,
                'created_at' => '2026-10-09 23:08:20',
                'updated_at' => '2026-10-12 00:58:20',
            ],
            [
                'id' => 7,
                'uuid' => '1cc893f1-e159-41f1-80f7-35995179446f',
                'name' => 'Mock Flights (Demo)',
                'type' => 'MOCK',
                'environment' => 'UAT',
                'api_key' => 'mock_key',
                'api_secret' => 'mock_secret',
                'connector_domain' => null,
                'pcc' => null,
                'printer' => null,
                'iata' => null,
                'client_ip' => null,
                'is_enable' => true,
                'supplier_id' => 1,
                'created_at' => '2026-10-10 00:40:36',
                'updated_at' => '2026-10-11 22:38:51',
            ],
        ];

        foreach ($connectors as $connector) {
            Connector::updateOrCreate(
                ['id' => $connector['id']],
                $connector
            );
        }
    }
}