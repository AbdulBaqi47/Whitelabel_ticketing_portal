<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\BranchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Response,DB};
use Illuminate\Validation\Rule;

class BranchController extends Controller
{

    public function __construct(protected \App\Interfaces\Organization\BranchRepositoryInterface $BRI)
    {}

    public  function index(Request $request):JsonResponse{

        $role_name = role_name();

        if(!in_array($role_name, \App\Models\Role::HEAD_ROLES)){
            return Response::errorResponse(400, 'Unauthorized Access');
        }

        try{
            $branches = $this->BRI->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200,'Branches List', $branches);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }
    public  function store(BranchRequest $request):JsonResponse
    {
        DB::beginTransaction();
        try{
            $branch = $this->BRI->store($request->validated());
            DB::commit();
            return Response::successResponse(201,'Branch Created', $branch);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function statusChange(string $uuid):JsonResponse{
        try{
            $branch = $this->BRI->statusChange($uuid);
            return Response::successResponse(200,'Status Changed!', $branch);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(string $uuid):JsonResponse{
        try{
            $branch = $this->BRI->delete($uuid);
            return Response::successResponse(200,'Branch Deleted!', $branch);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(BranchRequest $request,string $uuid):JsonResponse{

        DB::beginTransaction();
        try{
            $branch = $this->BRI->update($request->all(), $uuid);
            DB::commit();
            return Response::successResponse(200,'Branch Updated!', $branch);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function dropDown(){
        try{
            $connector_dropdown=$this->BRI->dropDown();
            return Response::successResponse(200, 'Branches Drop Down List', $connector_dropdown);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function dropDownByType(Request $request){

        $validatedData = $request->validate([
            'branchId' => ['required', 'integer', 'exists:organizations,id'],
            'type' => ['required', Rule::in(['agencies', 'employees'])],
        ]);

        try{
            $type_dropdown=$this->BRI->dropDownByType($validatedData);
            return Response::successResponse(200, 'Type Drop Down List', $type_dropdown);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function showAllBookings(string $uuid){
         try{
            $branch = $this->BRI->showAllBookings($uuid);
            return Response::successResponse(200,'Status Changed!', $branch);
        }catch(\Exception $e){
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }
}
