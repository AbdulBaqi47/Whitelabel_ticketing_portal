<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentManagementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Response};

class AgentManagementController extends Controller
{
    public function __construct(protected \App\Interfaces\Organization\AgentManagementRepositoryInterface $ARI)
    {}

    public  function index(Request $request):JsonResponse{
        try{
            $agencies = $this->ARI->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200,'Agents List', $agencies);
        }catch(\Exception $e){
             return Response::errorResponse(500, $e->getMessage());
        }
    }
    public  function store(AgentManagementRequest $request):JsonResponse
    {
        DB::beginTransaction();
        try{
            $agency = $this->ARI->store($request->validated());
            DB::commit();
            return Response::successResponse(201,'New Agent Created', $agency);
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

    public function delete(string $uuid):JsonResponse{
        try{
            $agency = $this->ARI->delete($uuid);
            return Response::successResponse(200,'Agent Deleted!', $agency);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(AgentManagementRequest $request,string $uuid):JsonResponse{
        try{
            $agency = $this->ARI->update($request->all(), $uuid);
            return Response::successResponse(200,'Agent Updated!', $agency);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        } 
    }    
}
