<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CountryRequest;
use App\Http\Resources\CountryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class CountryController extends Controller
{
    public function __construct(
        protected \App\Interfaces\CountryRepositoryInterface $CRepositoryInterface
    ) {}


    public function index(Request $request): JsonResponse
    {
        try {
            $countries = $this->CRepositoryInterface->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200, 'Countries List',$countries);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(CountryRequest $request):JsonResponse
    {
        try{
            $country = $this->CRepositoryInterface->store($request->validated());
            return Response::successResponse(201, 'Country Created', new CountryResource($country));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function show(int|string $uuid):JsonResponse
    {
        try{
            $country = $this->CRepositoryInterface->findById($uuid);
            return Response::successResponse(200, '', new CountryResource($country));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(CountryRequest $request,int|string $uuid):JsonResponse
    {
        try{
            $country = $this->CRepositoryInterface->update($request->validated(), $uuid);
            return Response::successResponse(200, 'Country Updated', new CountryResource($country));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid){
        try{
            $this->CRepositoryInterface->delete($uuid);
            return Response::successResponse(200, 'Country Deleted');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function dropDown(){
        try{
            $country_list = $this->CRepositoryInterface->dropDown();
            return Response::successResponse(200, 'Country List', $country_list);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
}
