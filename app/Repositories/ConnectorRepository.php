<?php

namespace App\Repositories;

use App\Interfaces\ConnectorRepositoryInterface;
use App\Models\Connector;

class ConnectorRepository implements ConnectorRepositoryInterface
{

    public function __construct(protected Connector $model)
    {}

    public function findByType(string $type)
    {
        $model = $this->model::where(['type' => $type])->first();
        if (!$model) {
            $validTypes = [
                'PIA_HITIT' => 'PIA (HITIT)',
                'SABRE' => 'Sabre',
                'AIRBLUE_API' => 'AIRBLUE_API',
                'FLYDUBAI_API' => 'FLYDUBAI_API',
                'ONEAPI' => 'ONEAPI',
                'SABRE_NDC' => 'Sabre NDC',
                'MOCK' => 'Mock Flights (Demo)',
            ];

            if (isset($validTypes[$type])) {
                $defaults = [
                    'name' => $validTypes[$type],
                    'type' => $type,
                    'environment' => 'UAT',
                    'is_enable' => false,
                    'supplier_id' => 1,
                    'api_key' => $type === 'MOCK' ? 'mock_key' : '',
                    'api_secret' => $type === 'MOCK' ? 'mock_secret' : '',
                ];

                if ($type === 'SABRE_NDC') {
                    $sabre = $this->model::where('type', 'SABRE')->first();
                    $defaults['api_key'] = $sabre?->api_key ?: '715063';
                    $defaults['api_secret'] = $sabre?->api_secret ?: 'SSWRES99';
                    $defaults['connector_domain'] = $sabre?->connector_domain ?: 'AA';
                    $defaults['pcc'] = $sabre?->pcc ?: 'JJ7L';
                    $defaults['printer'] = $sabre?->printer ?: 'BCE5F4';
                    $defaults['iata'] = json_encode(['emirates' => false, 'qatar_airways' => false, 'saudi_airline' => false]);
                }

                $model = $this->model::create($defaults);
            } else {
                return null;
            }
        }

        if ($model->type == 'SABRE_NDC') {
            if (empty($model->iata)) {
                $model->iata = (object)['emirates' => false, 'qatar_airways' => false, 'saudi_airline' => false];
            } else {
                $model->iata = is_string($model->iata) ? json_decode($model->iata) : $model->iata;
            }
            return $model;
        }

        return $model;
    }

    public function update(array $data){
        if ($data['type'] === 'MOCK') {
            $data['api_key'] = $data['api_key'] ?? 'mock_key';
            $data['api_secret'] = $data['api_secret'] ?? 'mock_secret';
        }
        if (isset($data['iata']) && is_array($data['iata'])) {
            $data['iata'] = json_encode($data['iata']);
        }
        return $this->model::updateOrCreate(['type' => $data['type']],[...$data, 'is_enable' => $data['is_enable'] ? 1 : 0]);
    }

    public function statusChange($status, int|string $type){
        $connector = $this->findByType($type);
        $connector->is_enable = $status;
        $connector->save();
        return true;
    }

    public function dropDown(){
        return $this->model::select('id', 'name', 'uuid', 'type')->where(['is_enable'=>1])->get();
    }
}
