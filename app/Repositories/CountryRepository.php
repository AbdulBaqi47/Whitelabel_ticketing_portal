<?php

namespace App\Repositories;

use App\Interfaces\CountryRepositoryInterface;
use App\Models\Country;
class CountryRepository implements CountryRepositoryInterface
{
    public function __construct(protected Country $model)
    {}

    public function getAll(?string $term, $per_page){
        $per_page = $per_page ?: config('app.per_page');
        return $this->model::query()->when($term, function ($query, $term){
            $query->whereAny(['name', 'nice_name', 'iso', 'iso3'], 'like', "%{$term}%");
        })->paginate($per_page);
    }

    public function store(array $data){
        return $this->model::create($data);
    }

    public function findById(int|string $uuid){
        return $this->model::where(['uuid'=>$uuid])->firstOrFail();
    }

    public function update(array $data, int|string $uuid){
        $connector = $this->findById($uuid);
        $connector->update($data);
        return $connector;
    }

    public function delete(int|string $uuid){
        $connector = $this->findById($uuid);
        $connector->delete();
        return true;
    }

    public function statusChange($status, int|string $uuid){
        $connector = $this->findById($uuid);
        $connector->is_enable = $status;
        $connector->save();
        return true;
    }

    public function dropDown()
    {
        return $this->model::select('id', 'name')->where(['status'=>1])->get();
    }
}
