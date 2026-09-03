<?php

namespace App\Repositories;

use App\Interfaces\AirlineRepositoryInterface;

class AirlineRepository implements AirlineRepositoryInterface
{
    public function __construct(protected \App\Models\Airline $model) {}

    public function getAll(?string $term, $per_page)
    {
        $per_page = $per_page ?: config('app.per_page');
        return $this->model::query()->with('country')->orderBy('id', 'desc')->when($term, function ($query, $term){
            $query->whereAny(['name', 'iata_code'], 'like', "%{$term}%")
            ->orWhereHas('country', function ($query) use ($term) {
                $query->when($term, function ($subQuery) use ($term) {
                    return $subQuery->where('name', 'like', "%{$term}%");
                });
            });
        })->paginate($per_page);
    }

    public function store(array $data)
    {
        if (request()->hasFile('thumbnail')) {
            $data['thumbnail'] = UPLOAD_FILE(request()->file('thumbnail'), 'airlines');
        }

        $data['status'] = $data['status'] == 'false' ? false : true;
        
        return $this->model::create($data);
    }

    public function findById(int|string $uuid)
    {
        return $this->model::where(['uuid' => $uuid])->firstOrFail();
    }

    public function update(array $data, int|string $uuid)
    {
        $airline = $this->findById($uuid);
        if (request()->has('thumbnail') && request()->hasFile('thumbnail')) {
            $data['thumbnail'] = UPLOAD_FILE(request()->file('thumbnail'), 'airlines');
        }
        $data['status'] = $data['status'] == 'false' ? false : true;

        $airline->update($data);
        return $airline;
    }

    public function delete(int|string $uuid)
    {
        $airline = $this->findById($uuid);
        $airline->delete();
        return true;
    }

    public function dropDown()
    {
        return $this->model::select('id', 'name')->where(['status' => 1])->get();
    }

    public function labelDropDown(){
        return $this->model::whereStatus(1)
        ->select([
            'iata_code as value',
            \Illuminate\Support\Facades\DB::raw("CONCAT(iata_code, ' - ', name) as label")
        ])
        ->get();
    }
}
