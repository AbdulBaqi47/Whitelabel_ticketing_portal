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
        $model = $this->model::where(['type'=>$type])->first();
        if($model->type == 'SABRE_NDC'){
            $model->iata = json_decode($model->iata);
            return $model;
        }

        return $model;

    }

    public function update(array $data){
        return $this->model::updateOrCreate(['type' => $data['type']],[...$data, 'is_enable' => $data['is_enable'] ? 1 : 0]);
    }

    public function statusChange($status, int|string $type){
        $connector = $this->findByType($type);
        $connector->is_enable = $status;
        $connector->save();
        return true;
    }

    public function dropDown(){
        return $this->model::select('id', 'name')->where(['is_enable'=>1])->get();
    }
}
