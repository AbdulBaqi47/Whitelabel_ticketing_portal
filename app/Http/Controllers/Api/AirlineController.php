<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AirlineRequest;
use App\Http\Resources\AirlineResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AirlineController extends Controller
{
    public function __construct(
        protected \App\Interfaces\AirlineRepositoryInterface $ARepositoryInterface
    ) {}


    public function index(Request $request): JsonResponse
    {
        try {
            $airlines = $this->ARepositoryInterface->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200, 'Airlines List', $airlines);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(AirlineRequest $request):JsonResponse
    {
        try{
            $data = $request->validated();
            $airline = $this->ARepositoryInterface->store($data);
            return Response::successResponse(201, 'Airline Created', new AirlineResource($airline));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function show(int|string $uuid):JsonResponse
    {
        try{
            $airline = $this->ARepositoryInterface->findById($uuid);
            return Response::successResponse(200, '', new AirlineResource($airline));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(AirlineRequest $request,int|string $uuid):JsonResponse
    {
        try{
            $airline = $this->ARepositoryInterface->update($request->validated(), $uuid);
            return Response::successResponse(200, 'Airline Updated', new AirlineResource($airline));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid){
        try{
            $this->ARepositoryInterface->delete($uuid);
            return Response::successResponse(200, 'Airline Deleted');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function dropDown(){
        try{
            $airline_dropdown=$this->ARepositoryInterface->dropDown();
            return Response::successResponse(200, 'Airline Drop Down List', $airline_dropdown);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function labelDropDown(){
         try{
            $airline_dropdown=$this->ARepositoryInterface->labelDropDown();
            return Response::successResponse(200, 'Airline Label Drop Down List', $airline_dropdown);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
}
