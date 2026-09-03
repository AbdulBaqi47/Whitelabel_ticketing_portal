<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\JsonResponse;

class ChartOfAccountController extends Controller
{

    public function __construct(
        protected \App\Interfaces\ChartOfAccountRepositoryInterface $COARepositoryInterface
    ) {}


    public function index(Request $request): JsonResponse
    {
        try {
            $account_heads = $this->COARepositoryInterface->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200, 'Account Head List', $account_heads);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(AccountHeadRequest $request):JsonResponse
    {
        try{
            $data = $request->validated();
            $account_head = $this->COARepositoryInterface->store($data);
            return Response::successResponse(201, 'Account Head Created', $account_head);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(AccountHeadRequest $request,int|string $uuid):JsonResponse
    {
        try{
            $account_head = $this->COARepositoryInterface->update($request->validated(), $uuid);
            return Response::successResponse(200, 'Account Head Updated', $account_head);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid){
        try{
            $this->COARepositoryInterface->delete($uuid);
            return Response::successResponse(200, 'Account Head Deleted');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function dropDown(){
        try{
            $airline_dropdown=$this->COARepositoryInterface->dropDown();
            return Response::successResponse(200, 'Account Head Down List', $airline_dropdown);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
}
