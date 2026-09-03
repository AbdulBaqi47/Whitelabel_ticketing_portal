<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountHeadRequest;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountHeadController extends Controller
{
    public function __construct(
        protected \App\Interfaces\AccountHeadRepositoryInterface $AHRepositoryInterface
    ) {}


    public function index(Request $request): JsonResponse
    {
        try {
            $account_heads = $this->AHRepositoryInterface->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200, 'Account Head List', $account_heads);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(AccountHeadRequest $request):JsonResponse
    {
        try{
            $data = $request->validated();
            $account_head = $this->AHRepositoryInterface->store($data);
            return Response::successResponse(201, 'Account Head Created', $account_head);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(AccountHeadRequest $request,int|string $uuid):JsonResponse
    {
        try{
            $account_head = $this->AHRepositoryInterface->update($request->validated(), $uuid);
            return Response::successResponse(200, 'Account Head Updated', $account_head);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid){
        try{
            $this->AHRepositoryInterface->delete($uuid);
            return Response::successResponse(200, 'Account Head Deleted');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function dropDown(){
        try{
            $airline_dropdown=$this->AHRepositoryInterface->dropDown();
            return Response::successResponse(200, 'Account Head Down List', $airline_dropdown);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
}
