<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AirportRequest;
use App\Http\Resources\AirportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AirportController extends Controller
{
    public function __construct(
        protected \App\Interfaces\AirportRepositoryInterface $ARepositoryInterface
    ) {}


    public function index(Request $request): JsonResponse
    {
        try {
            $airports = $this->ARepositoryInterface->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200, 'Airports List', $airports);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(AirportRequest $request):JsonResponse
    {
        try{
            $airport = $this->ARepositoryInterface->store($request->validated());
            return Response::successResponse(201, 'Airport Created', new AirportResource($airport));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function show(int|string $uuid):JsonResponse
    {
        try{
            $airport = $this->ARepositoryInterface->findById($uuid);
            return Response::successResponse(200, '', new AirportResource($airport));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(AirportRequest $request,int|string $uuid):JsonResponse
    {
        try{
            $airport = $this->ARepositoryInterface->update($request->validated(), $uuid);
            return Response::successResponse(200, 'Airport Updated', new AirportResource($airport));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid){
        try{
            $this->ARepositoryInterface->delete($uuid);
            return Response::successResponse(200, 'Airport Deleted');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function dropDown(Request $request){
        try{
            $airport_list = $this->ARepositoryInterface->dropDown($request->get('query'));
            return Response::successResponse(200, '', $airport_list);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }
}
