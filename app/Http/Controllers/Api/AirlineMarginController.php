<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AirlineMarginRequest;
use App\Http\Resources\AirlineMarginResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AirlineMarginController extends Controller
{
    public function __construct(
        protected \App\Interfaces\AirlineMarginRepositoryInterface $AMRepositoryInterface
    ) {}


    public function index(Request $request): JsonResponse
    {
        try {
            $airline_margins = $this->AMRepositoryInterface->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200, 'Airline Margins List', $airline_margins);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(AirlineMarginRequest $request):JsonResponse
    {
        try{
            $airline_margin = $this->AMRepositoryInterface->store($request->validated());
            return Response::successResponse(201, 'Airline Margin Created', new AirlineMarginResource($airline_margin));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function show(int|string $uuid):JsonResponse
    {
        try{
            $airline_margin = $this->AMRepositoryInterface->findById($uuid);
            return Response::successResponse(200, '', new AirlineMarginResource($airline_margin));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(AirlineMarginRequest $request,int|string $uuid):JsonResponse
    {
        try{

            $airport = $this->AMRepositoryInterface->update($request->validated(), $uuid);
            return Response::successResponse(200, 'Airline Margin Updated', new AirlineMarginResource($airport));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid){
        try{
            $this->AMRepositoryInterface->delete($uuid);
            return Response::successResponse(200, 'Airline Margin Deleted');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
}
