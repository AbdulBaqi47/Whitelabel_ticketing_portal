<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NewAndAlertRequest;
use App\Http\Resources\NewAndAlertResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class NewAndAlertController extends Controller
{
    public function __construct(
        protected \App\Interfaces\NewAndAlertRepositoryInterface $NARepositoryInterface
    ) {}


    public function index(Request $request): JsonResponse
    {
        try {
            $connectors = $this->NARepositoryInterface->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200, 'News List', $connectors);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(NewAndAlertRequest $request):JsonResponse
    {
        try{
            $news = $this->NARepositoryInterface->store($request->validated());
            return Response::successResponse(201, 'News Created', new NewAndAlertResource($news));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function show(int|string $uuid):JsonResponse
    {
        try{
            $news = $this->NARepositoryInterface->findById($uuid);
            return Response::successResponse(200, '', new NewAndAlertResource($news));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(NewAndAlertRequest $request,int|string $uuid):JsonResponse
    {
        try{
            $news = $this->NARepositoryInterface->update($request->validated(), $uuid);
            return Response::successResponse(200, 'News Updated', new NewAndAlertResource($news));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid){
        try{
            $this->NARepositoryInterface->delete($uuid);
            return Response::successResponse(200, 'News Deleted');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
}
