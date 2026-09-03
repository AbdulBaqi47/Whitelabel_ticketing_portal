<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgencyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Response};

class AgencyController extends Controller
{
    public function __construct(protected \App\Interfaces\Organization\AgencyRepositoryInterface $ARI)
    {}

    public  function index(Request $request):JsonResponse{
        try{
            $agencies = $this->ARI->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200,'Agencies List', $agencies);
        }catch(\Exception $e){
             return Response::errorResponse(500, $e->getMessage());
        }
    }
    public  function store(AgencyRequest $request):JsonResponse
    {
        DB::beginTransaction();
        try{
            $agency = $this->ARI->store($request->validated());
            DB::commit();
            return Response::successResponse(201,'Agency Created', $agency);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function statusChange(string $uuid):JsonResponse{
        try{
            $agency = $this->ARI->statusChange($uuid);
            return Response::successResponse(200,'Status Changed!', $agency);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function financialProfile(string $uuid):JsonResponse{
        try{
            $agency = $this->ARI->financialProfile($uuid);
            return Response::successResponse(200,'Status Changed!', $agency);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(string $uuid):JsonResponse{
        try{
            $agency = $this->ARI->delete($uuid);
            return Response::successResponse(200,'Agency Deleted!', $agency);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(AgencyRequest $request,string $uuid):JsonResponse{
        try{
            $agency = $this->ARI->update($request->all(), $uuid);
            return Response::successResponse(200,'Agency Updated!', $agency);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function dropDown(){
        try{
            $drop_down = $this->ARI->dropDown();
            return Response::successResponse(200,'Agency Drop Down List!', $drop_down);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
}