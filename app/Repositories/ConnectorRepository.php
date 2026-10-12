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
                $model = $this->model::create([
                    'name' => $validTypes[$type],
                    'type' => $type,
                    'environment' => 'UAT',
                    'is_enable' => false,
                    'supplier_id' => 1,
                    'api_key' => $type === 'MOCK' ? 'mock_key' : null,
                    'api_secret' => $type === 'MOCK' ? 'mock_secret' : null,
                ]);
            } else {
                return null;
            }
        }

        if ($model->type == 'SABRE_NDC') {
            $model->iata = is_string($model->iata) ? json_decode($model->iata) : $model->iata;
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
