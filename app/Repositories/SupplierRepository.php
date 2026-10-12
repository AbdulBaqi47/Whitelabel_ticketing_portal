<?php

namespace App\Repositories;

use App\Interfaces\SupplierRepositoryInterface;
use App\Models\Supplier;

class SupplierRepository implements SupplierRepositoryInterface
{
    public function __construct(protected Supplier $model)
    {}

    public function getAll(?string $term, $per_page){
        $per_page = $per_page ?: config('app.per_page');
        return $this->model::query()->orderBy('id', 'desc')->when($term, function ($query, $term){
            $query->whereAny(['name', 'description', 'slug'], 'like', "%{$term}%");
        })->paginate($per_page);
    }

    public function store(array $data){
        return $this->model::create($data);
    }

    public function findById(int|string $uuid){
        return $this->model::where(['uuid'=>$uuid])->firstOrFail();
    }

    public  function update(array $data, int|string $uuid){
        $supplier = $this->findById($uuid);
        $supplier->update($data);
        return $supplier;
    }

    public function delete(int|string $uuid){
        $supplier = $this->findById($uuid);
        $supplier->delete();
        return true;
    }

    public function dropDown(){
        return $this->model::query()->select('id','name')->where('status', true)->get();
    }
}
