<?php

namespace App\Repositories;

use App\Interfaces\AccountHeadRepositoryInterface;

class AccountHeadRepository implements AccountHeadRepositoryInterface
{
    public function __construct(protected \App\Models\AccountHead $model) {}

    public function getAll(?string $term, $per_page)
    {
        $per_page = $per_page ?: config('app.per_page');
        return $this->model::query()->orderBy('id', 'desc')->when($term, function ($query, $term){
            $query->whereAny(['name'], 'like', "%{$term}%");
        })->paginate($per_page);
    }

    public function store(array $data)
    {        
        return $this->model::create($data);
    }

    public function findById(int|string $uuid)
    {
        return $this->model::where(['uuid' => $uuid])->firstOrFail();
    }

    public function update(array $data, int|string $uuid)
    {
        $account_head = $this->findById($uuid);
        $account_head->update($data);
        return $account_head;
    }

    public function delete(int|string $uuid)
    {
        $account_head = $this->findById($uuid);
        $account_head->delete();
        return true;
    }

    public function dropDown()
    {
        return $this->model::select('id', 'name')->where(['status' => 1])->get();
    }
}
