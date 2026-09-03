<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccountsRequest;
use App\Http\Resources\BankAccountsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class BankAccountController extends Controller
{
    public function __construct(
        protected \App\Interfaces\BankAccounts\BankAccountsRepositoryInterface $bARepositoryInterface
    ) {}


    public function index(Request $request)
    {
        try {
            $bank_accounts = $this->bARepositoryInterface->getAll($request->get('q'), $request->get('perPage'));
            return Response::successResponse(200, 'Bank Accounts List', $bank_accounts);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function store(BankAccountsRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('bank_logo')) {
                $data['bank_logo'] = UPLOAD_FILE($request->file('bank_logo'), 'bank_account');
            }
            $bank_account = $this->bARepositoryInterface->store($data);
            return Response::successResponse(201, 'Bank Account Created', new BankAccountsResource($bank_account));
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function show(int|string $uuid): JsonResponse
    {
        try {
            $bank_account = $this->bARepositoryInterface->findById($uuid);
            return Response::successResponse(200, '', new BankAccountsResource($bank_account));
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function update(BankAccountsRequest $request, int|string $uuid): JsonResponse
    {
        try {
            $data = $request->validated();

            if(is_null(\App\Models\Bank::where('uuid', $uuid)->first()->org_id) && !in_array(role_name(), \App\Models\Role::HEAD_ROLES)){
                return Response::errorResponse(400, "You don't have permission to do this Action.");
            }
            
            if ($request->hasFile('bank_logo')) {
                $data['bank_logo'] = UPLOAD_FILE($request->file('bank_logo'), 'bank_account');
            }
            $bank_account = $this->bARepositoryInterface->update($data, $uuid);
            return Response::successResponse(200, 'Bank Account Updated', new BankAccountsResource($bank_account));
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function delete(int|string $uuid)
    {
        try {
            $deposit = $this->bARepositoryInterface->delete($uuid);

            if($deposit['status']){
                return Response::successResponse(200, 'Bank Account Deleted');
            }else{
                return Response::successResponse(400, $deposit['message']);
            }
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function bankList(){
        try{

            if(in_array(role_name(), \App\Models\Role::HEAD_ROLES)){
                return Response::errorResponse(400, "You don't have permission to Access this Module.");
            }

            $bank_list = $this->bARepositoryInterface->bankList();
            return Response::successResponse(200, 'Bank Account List',$bank_list);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function bankDropDown(){
        try{
            $bank_list = $this->bARepositoryInterface->bankDropDown();
            return Response::successResponse(200, 'Bank Account List',$bank_list);
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function showToAgencies($uuid){


        try{

            $bank_account = \App\Models\Bank::where('uuid',$uuid)->first();

            if(!is_null($bank_account->org_id) && $bank_account->org_id != org_id()){
                return Response::errorResponse(400, "You don't have permission to do this Action.");
            }

            $this->bARepositoryInterface->showToAgencies($bank_account->id);
            return Response::successResponse(200, 'Update SuccessFully!');

        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }
}
