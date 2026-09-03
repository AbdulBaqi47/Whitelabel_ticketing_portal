<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectorRequest;
use App\Http\Resources\ConnectorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ConnectorController extends Controller
{

    public function __construct(
        protected \App\Interfaces\ConnectorRepositoryInterface $cRepositoryInterface
    ) {}

    public function update(ConnectorRequest $request):JsonResponse
    {
        try{
            $connector = $this->cRepositoryInterface->update($request->validated());
            return Response::successResponse(200, 'Connector Update', new ConnectorResource($connector));
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function show(string $type):JsonResponse
    {
        try{
            $connector = $this->cRepositoryInterface->findByType($type);
            if($connector){
                return Response::successResponse(200, '', new ConnectorResource($connector));
            }else{
                return Response::errorResponse(404, "$type does not exists");
            }
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function statusChange(Request $request,int|string $uuid){
        try{
            $this->cRepositoryInterface->statusChange($request->post('status'), $uuid);
            return Response::successResponse(200, 'Connector Status Changed.');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function accessToken(string $type){
        try{
            $access_token = \App\Models\AccessToken::where('type', $type)->first();
            return Response::successResponse(200, "$type Access token", $access_token);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function dropDown(){
        try{
            $connector_dropdown=$this->cRepositoryInterface->dropDown();
            return Response::successResponse(200, 'Connector Drop Down List', $connector_dropdown);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        } 
    }
    
}
