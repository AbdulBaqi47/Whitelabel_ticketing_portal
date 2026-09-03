<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Response,DB};

class EmployeeController extends Controller
{
    public function __construct(protected \App\Interfaces\Organization\EmployeeRepositoryInterface $ERI)
    {}

    public  function index(Request $request):JsonResponse{
        try{
            $employees = $this->ERI->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200,'Employees List', $employees);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }
    public  function store(EmployeeRequest $request):JsonResponse
    {
        try{
            $employee = $this->ERI->store($request->validated());
            return Response::successResponse(201,'Employee Created', $employee);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function statusChange(string $uuid):JsonResponse{
        try{
            $employee = $this->ERI->statusChange($uuid);
            return Response::successResponse(200,'Status Changed!', $employee);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(string $uuid):JsonResponse{
        try{
            $employee = $this->ERI->delete($uuid);
            return Response::successResponse(200,'Employee Deleted!', $employee);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(EmployeeRequest $request,string $uuid):JsonResponse{
        try{
            $employee = $this->ERI->update($request->all(), $uuid);
            return Response::successResponse(200,'Employee Updated!', $employee);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
}
