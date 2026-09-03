<?php

namespace App\Repositories;

use App\Interfaces\AirportRepositoryInterface;
use App\Interfaces\CityRepositoryInterface;

class CityRepository implements CityRepositoryInterface
{
    public function __construct(protected \App\Models\City $model)
    {}

    public function getAll(){
        return $this->model::query()->paginate();
    }

    public function store(array $data){
        return $this->model::create($data);
    }

    public function findById(int|string $uuid){
        return $this->model::where(['uuid'=>$uuid])->firstOrFail();
    }

    public function update(array $data, int|string $uuid){
        $airport = $this->findById($uuid);
        $airport->update($data);
        return $airport;
    }

    public function delete(int|string $uuid){
        $airport = $this->findById($uuid);
        $airport->delete();
        return true;
    }

}
