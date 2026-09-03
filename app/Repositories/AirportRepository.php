<?php

namespace App\Repositories;

use App\Interfaces\AirportRepositoryInterface;

class AirportRepository implements AirportRepositoryInterface
{
    public function __construct(protected \App\Models\Airport $model)
    {}

    public function getAll(?string $term, $per_page){
        $per_page = $per_page ?: config('app.per_page');
        return $this->model::query()->orderBy('id', 'desc')->when($term, function ($query, $term){
            $query->whereAny(['name', 'iso_country', 'municipality', 'iata_code', 'country'] , 'like', "%{$term}%");
        })->paginate($per_page);
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
    public function dropDown(string $query){
        return $this->model
            ->where(function($subQuery) use ($query){
                if(strlen($query)>3){
                    $subQuery->whereAny(['municipality', 'iata_code', 'country'], 'LIKE', '%' . $query . '%');
                }else{
                    $subQuery->where('iata_code', 'LIKE', '%' . $query . '%');
                }
            })->orderBy('name', 'ASC')
            ->get(['name', 'municipality', 'iata_code', 'country']);
    }
}
