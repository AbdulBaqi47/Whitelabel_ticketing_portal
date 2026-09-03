<?php

namespace App\Repositories;

use App\Interfaces\AirlineMarginRepositoryInterface;

class AirlineMarginRepository implements AirlineMarginRepositoryInterface
{
    public function __construct(protected \App\Models\AirlineMargin $model) {}

    public function getAll(?string $term, $per_page)
    {

        $per_page = $per_page ?: config('app.per_page');
        return $this->model::query()->with('airline')->orderBy('id', 'desc')->when($term, function ($q, $term) {
            $q->whereAny(['sales_channel'], 'like', "%{$term}%")->orWhereHas('airline', function($query) use ($term){
                return $query->whereAny(['name', 'iata_code'], 'like', "%{$term}%");
            });
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
        $airline = $this->findById($uuid);

        $originalMargin = $airline->margin;
        $airline->update($data);

        if ($data['margin'] != $originalMargin) {

            $airline->margin_originations()->update(['margin' => $data['margin'], 'margin_type'=> $data['margin_type']]);
            $apply_all= $airline->margin_originations->where('parent_org_id', null)->first();
            if($apply_all->apply_all){
                $apply_all->apply_value = $data['margin'];
                $apply_all->save();
            }
        }


        return $airline;
    }

    public function delete(int|string $uuid)
    {
        $airline = $this->findById($uuid);
        $airline->delete();
        return true;
    }
}
