<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class SupplierController extends Controller
{
    public function __construct(
        protected \App\Interfaces\SupplierRepositoryInterface $SRepositoryInterface
    ) {}


    public function index(Request $request): JsonResponse
    {
        try {
            $suppliers = $this->SRepositoryInterface->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200, 'Suppliers List', $suppliers);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(SupplierRequest $request):JsonResponse
    {
        try{
            $data = $request->validated();
            $data['slug'] = \Illuminate\Support\Str::slug($request->name, '-');
            $supplier = $this->SRepositoryInterface->store($data);
            return Response::successResponse(201, 'Supplier Created', new SupplierResource($supplier));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function show(int|string $uuid):JsonResponse
    {
        try{
            $supplier = $this->SRepositoryInterface->findById($uuid);
            return Response::successResponse(200, '', new SupplierResource($supplier));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(SupplierRequest $request,int|string $uuid):JsonResponse
    {
        try{
            $supplier = $this->SRepositoryInterface->update($request->validated(), $uuid);
            return Response::successResponse(200, 'Supplier Updated', new SupplierResource($supplier));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid){
        try{
            $this->SRepositoryInterface->delete($uuid);
            return Response::successResponse(200, 'Supplier Deleted');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }

    public function dropDown(){
        try{
            return Response::successResponse(200, 'Supplier DropDown List', $this->SRepositoryInterface->dropDown());
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }
}
