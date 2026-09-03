<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ManageDepositController extends Controller
{
    public function agencyList(Request $request)
    {
        $current_user = \Illuminate\Support\Facades\Auth::user();
        $role_name = role_name();
        $perPage = request()->get('per_page', config('app.per_page'));
        $tab = deposit_status()[$request->tab];
        if (in_array($role_name, \App\Models\Role::MANAGMENT_ROLES)) {
            return Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        try {

            $agency_deposits = \App\Models\Deposit::with('bank:id,bank_name', 'organization:id,name', 'approved_by:id,name', 'request_by:id,name')
                ->where(function ($q) use ($current_user, $role_name) {
                    if (in_array($role_name, ['branch', 'b-employee'])) {
                        $orgIds = \App\Models\Organization::whereParentId($current_user->org_id)->pluck('id')->toArray();
                        $q->whereIn('org_id', array_merge([$current_user->org_id], $orgIds));
                    }else{
                        $q->where('org_id', $current_user->org_id);
                    }
                })
                ->whereStatus($tab)
                ->orderBy('id', 'desc')->paginate($perPage);

            return Response::successResponse(200, 'Agency Deposits List.', $agency_deposits);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function orgList(Request $request)
    {
        if (!in_array(role_name(), \App\Models\Role::HEAD_ROLES)) {
            return Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        try {
            $perPage = request()->get('perPage', config('app.per_page'));
            $tab = deposit_status()[$request->tab];
            $agency_deposits = \App\Models\Deposit::with('bank:id,bank_name', 'organization:id,name', 'approved_by:id,name', 'request_by:id,name')
                ->whereStatus($tab)
                ->orderBy('id', 'desc')->paginate($perPage);

            return Response::successResponse(200, 'Deposits List.', $agency_deposits);
        } catch (\Exception $e) {
            return  Response::errorResponse(500, $e->getMessage());
        }
    }

    public function depositRequest(Request $request)
    {
        $role_name = role_name();
        if (in_array($role_name, \App\Models\Role::MANAGMENT_ROLES)) {
            return Response::errorResponse(400, "You Don't have permission to access this module.");
        }
        $reqData = $request->validate([
            'bank_id'         => 'required|exists:banks,id',
            'reciept'         => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'deposit_amount'  => 'required|numeric|min:0',
        ]);

        try {
            $current_user = \Illuminate\Support\Facades\Auth::user();

            if ($request->hasFile('reciept')) {
                $reqData['reciept'] = UPLOAD_FILE($request->file('reciept'), 'deposit_reciept');
            }
            $reqData['request_id'] = $current_user->id;
            $reqData['org_id'] = $current_user->org_id;
            $reqData['deposit_id'] = random_id("TP1");
            $deposit = \App\Models\Deposit::create($reqData);
            $organization = $current_user->organization;
            $description = 'New deposit request submitted by ' . $organization->name;
            store_notification($deposit, $description, 'DEPOSIT_REQUESTED');

            $notifiable_users = [];
            broadcast(new \App\Events\DepositRequested($deposit->id, ['9ce86cd9-ebf2-44cb-a559-fcdff75a58f2'], 'DEPOSIT_REQUESTED', $description))->toOthers();
            
        return Response::successResponse(201, 'Deposit Created Successfully.', $deposit);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function depositAccept($uuid)
    {
        if (!in_array(role_name(), \App\Models\Role::HEAD_ROLES)) {
            return Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        DB::beginTransaction();
        try {
            $deposit = \App\Models\Deposit::where('uuid', $uuid)->first();

            if (is_null($deposit)) {
                return Response::errorResponse(404, "Deposit Not Found");
            }

            $deposit->status = 'accepted';
            $deposit->approved_by = Id();
            $deposit->save();
            $description = 'Your deposit request for ' . $deposit->deposit_amount . ' ' . $deposit->currency . ' is been Approved!' ;
            store_notification($deposit, $description, 'DEPOSIT_APPROVED');
            $userId = \App\Models\User::find($deposit->request_id)->uuid;
            broadcast(new \App\Events\DepositRequested($deposit->id, [$userId], 'DEPOSIT_APPROVED', $description))->toOthers();
            deposit_approved($deposit);
            DB::commit();
            return Response::successResponse(200, 'Deposit Accepted Successfully.', $deposit);
        } catch (\Exception $e) {
            DB::rollBack();
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function depositReject(Request $request,$uuid)
    {

        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $role_name = role_name();
        
        if (!in_array($role_name, \App\Models\Role::HEAD_ROLES)) {
            return Response::errorResponse(400, "You Don't have permission to access this module.");
        }
        try {

            $deposit = \App\Models\Deposit::where('uuid', $uuid)->first();

            if (is_null($deposit)) {
                return Response::errorResponse(404, "Deposit Not Found");
            }
            $deposit->status = 'rejected';
            $deposit->rejection_reason = $request->rejection_reason;
            $deposit->approved_by = Id();
            $deposit->save();

            $description = 'Your deposit request for ' . $deposit->deposit_amount . ' '. $deposit->currency . ' is been Rejected!';
            store_notification($deposit, $description, 'DEPOSIT_REJECTED');

            $userId = \App\Models\User::find($deposit->request_id)->uuid;
            broadcast(new \App\Events\DepositRequested($deposit->id, [$userId], 'DEPOSIT_REJECTED', $description))->toOthers();

            return Response::successResponse(200, 'Deposit Rejected Successfully.', $deposit);

        } catch (\Exception $e) {
            return  Response::errorResponse(500, $e->getMessage());
        }
    }
}
