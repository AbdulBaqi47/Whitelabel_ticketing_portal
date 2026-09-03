<?php

namespace App\Repositories;

use App\Interfaces\NewAndAlertRepositoryInterface;
use App\Models\News;

class NewAndAlertRepository implements NewAndAlertRepositoryInterface
{
    public function __construct(protected News $model)
    {}

    public function getAll(?string $term, $per_page){
        $per_page = $per_page ?: config('app.per_page');
        return $this->model::query()->orderBy('id', 'desc')->when($term, function ($query, $term){
            $query->whereAny(['title', 'description'], 'like', "%{$term}%");
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
}
