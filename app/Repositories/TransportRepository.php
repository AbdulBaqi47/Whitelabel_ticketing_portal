<?php

namespace App\Repositories;

use App\Interfaces\TransportRepositoryInterface;

class TransportRepository implements TransportRepositoryInterface
{
    public function __construct(protected \App\Models\TransportType $type_model,protected \App\Models\TransportRoute $route_model){}

    public function getAllTypes(){
        return $this->type_model::query()->paginate();
    }

    public function storeType(array $data){
        return $this->type_model::create($data);
    }

    public function findTypeById(int|string $uuid){
        return $this->type_model::where(['uuid'=>$uuid])->firstOrFail();
    }

    public function updateType(array $data, int|string $uuid){
        $transport_type = $this->findTypeById($uuid);
        $transport_type->update($data);
        return $transport_type;
    }

    public function deleteType(int|string $uuid){
        $transport_type = $this->findTypeById($uuid);
        $transport_type->delete();
        return true;
    }

    public function typeStatusChange(int|string $uuid){
        $transport_type = $this->findTypeById($uuid);
        $transport_type->status = !$transport_type->status;
        $transport_type->save();
    }

    public function typeDropDown(){
        return $this->type_model::select('id', 'name')->where('status', true)->get();
    }

    /* ======= Transport Route ======= */

    public function getAllRoutes(){
        return $this->route_model::query()->paginate();
    }

    public function routeStore(array $data){
        return $this->route_model::create($data);
    }

    public function findRouteById(int|string $uuid){
        return $this->route_model::where(['uuid'=>$uuid])->firstOrFail();
    }

    public function updateRoute(array $data, int|string $uuid){
        $transport_route = $this->findRouteById($uuid);
        $transport_route->update($data);
        return $transport_route;
    }

    public function routedelete(int|string $uuid){
        $transport_route = $this->findRouteById($uuid);
        $transport_route->delete();
        return true;
    }

    public function routeStatusChange(int|string $uuid){
        $transport_route = $this->findRouteById($uuid);
        $transport_route->status = !$transport_route->status;
        $transport_route->save();
    }

    public function routeDropDown(){
        return $this->route_model::select('id', 'name')->where('status', true)->get();
    }

}
