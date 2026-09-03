<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Http\Resources\CityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class CityController extends Controller
{
    public function __construct(
        protected \App\Interfaces\CityRepositoryInterface $CRepositoryInterface
    ) {}


    public function index(): JsonResponse
    {
        try {
            $connectors = $this->CRepositoryInterface->getAll();
            return Response::successResponse(200, 'Cities List', CityResource::collection($connectors));
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(CityRequest $request):JsonResponse
    {
        try{
            $connector = $this->CRepositoryInterface->store($request->validated());
            return Response::successResponse(201, 'City Created', new CityResource($connector));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function show(int|string $uuid):JsonResponse
    {
        try{
            $connector = $this->CRepositoryInterface->findById($uuid);
            return Response::successResponse(200, '', new CityResource($connector));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(CityRequest $request,int|string $uuid):JsonResponse
    {
        try{
            $connector = $this->CRepositoryInterface->update($request->validated(), $uuid);
            return Response::successResponse(200, 'City Updated', new CityResource($connector));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid){
        try{
            $this->CRepositoryInterface->delete($uuid);
            return Response::successResponse(200, 'City Deleted');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
}
