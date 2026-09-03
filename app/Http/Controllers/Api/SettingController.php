<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class SettingController extends Controller
{

    public function generalSetting(Request $request)
    {

        $reqData = $request->validate([
            'void_charges' => 'nullable|string',
            'extra_commision' => 'nullable|string',
            'can_issue_ticket' => 'sometimes|required|boolean',
            'uuid' => 'required|string',
        ]);

        try {

            $organization = \App\Models\Organization::where('uuid', $reqData['uuid'])->first();

            if (is_null($organization)) {
                Response::errorResponse(404, 'User Not Found!');
            }

            $org_setting = \App\Models\UserSetting::where('org_id', $organization->id)->first();

            if (is_null($org_setting)) {
                $org_setting = new \App\Models\UserSetting();
                $org_setting->org_id = $organization->id;
            }

            $org_setting->void_charges = $reqData['void_charges'];
            $org_setting->extra_commision = $reqData['extra_commision'];
            $org_setting->can_issue_ticket = $reqData['can_issue_ticket'];
            $org_setting->save();

            return Response::successResponse(200, 'Updated Successfully!');
        } catch (\Exception $e) {
            Response::errorResponse(500, $e->getMessage());
        }
    }

    public function temporaryLimit(Request $request)
    {

        $reqData = $request->validate([
            'temp_credit_limit'     => 'required|string|min:1',
            'temp_limit_due_date'   => 'required|date|after_or_equal:today',
            'uuid'                  => 'required|string'
        ]);

        try {
            $organization = \App\Models\Organization::where('uuid', $reqData['uuid'])->first();

            if (is_null($organization)) {
                Response::errorResponse(404, 'User Not Found!');
            }

            $wallet = \App\Models\Wallet::where('org_id', $organization->id)->first();

            if (is_null($wallet)) {
                $wallet = new \App\Models\Wallet();
                $wallet->org_id = $organization->id;
            }

            $wallet->temp_credit_limit  = (float) $reqData['temp_credit_limit'];
            $wallet->temp_limit_due_date = $reqData['temp_limit_due_date'];
            $wallet->save();

            \App\Models\TemporayCreditLimit::create([
                'wallet_id'         => $wallet->id,
                'limit_amount'      => (float) $reqData['temp_credit_limit'],
                'start_date'        => now(),
                'end_date'          => $reqData['temp_limit_due_date'],
                'created_by'        => Id(),
                'used_amount'       => 0,
            ]);

            return Response::successResponse(200, 'Updated Successfully!');
        } catch (\Exception $e) {
            Response::errorResponse(500, $e->getMessage());
        }
    }

    public function financialProfile()
    {
        try {

            $orgId = org_id();
            $balance = balance($orgId);
            $permanent_limit  = \App\Models\Wallet::where('org_id', $orgId)->first()?->per_credit_limit;
            $valid_temp_limit = valid_temp_limit($orgId);
            $totalWalletAmount = $balance + $valid_temp_limit + $permanent_limit;

            $data = [
                'balance'                       => round($totalWalletAmount, 2),
                'financial_profile'             => \App\Models\Organization::find($orgId)->financial_profile,
                'not_invoiced_balance'          => not_invoiced_booking_total($orgId),
                'payable_amount'                => round(payable_amount($orgId), 2),
                'permanent_credit'              => $permanent_limit,
                'temporary_limit'               => [
                    'available_temp_balance'    => $valid_temp_limit,
                    'active_temp_list'          => active_temp_limits($orgId),
                ],
            ];

            return Response::successResponse(200, 'Current Account Status', $data);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function reset2FA()
    {

        $auth_user = \Illuminate\Support\Facades\Auth::user();

        $user = \App\Models\User::find($auth_user->id);

        $user->otp_pref = 'email';
        $user->google_auth_verified =  false;
        $user->save();

        return Response::successResponse(200, 'Reset Successfully!');
    }

    public function googleAuthenticatorDisable(Request $request)
    {

        $reqData = $request->validate([
            'uuid' => ['required', 'string', 'exists:users,uuid'],
        ]);

        $role_name = role_name();
        if (!in_array($role_name, \App\Models\Role::MANAGMENT_ROLES)) {
            return Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        $user = \App\Models\User::where('uuid', $reqData['uuid'])->first();


        if ($user) {

            $user->google_auth_verified = false;
            $user->otp_pref = 'email';
            $user->save();

            return Response::successResponse(200, "Updated Successfully!");
        }

        return Response::errorResponse(404, "User Not Found!");
    }

    public function ticketIssuance(Request $request)
    {
        $reqData = $request->validate([
            'id' => ['required', 'exists:user_settings,id'],
        ]);

        $role_name = role_name();
        if (!in_array($role_name, \App\Models\Role::MANAGMENT_ROLES)) {
            return Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        $user_setting = \App\Models\UserSetting::whereId($reqData['id'])->first();

        if ($user_setting) {
            $user_setting->can_issue_ticket = !$user_setting->can_issue_ticket;
            $user_setting->save();
            return Response::successResponse(200, "Updated Successfully!");
        }

        return Response::errorResponse(404, "User Not Found!");
    }

    public function employeeTicketIssuance(Request $request)
    {

        $reqData = $request->validate([
            'uuid' => ['required', 'exists:users,uuid'],
        ]);

        if (!in_array(role_name(), [\App\Models\Role::HEADOFFICE, 'branch'])) {
            return Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        $user = \App\Models\User::where('uuid', $reqData['uuid'])->first();
        $user->can_issue_ticket = !$user->can_issue_ticket;
        $user->save();
        return Response::successResponse(200, "Updated Successfully!");
    }

    public function branchTicketIssuance(Request $request)
    {
        $request->validate([
            'uuid' => ['required', 'exists:organizations,uuid'],
        ]);

        if (!in_array(role_name(), [\App\Models\Role::HEADOFFICE])) {
            return Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        $org = \App\Models\Organization::where('uuid', $request->uuid)->first();
        $user_setting = \App\Models\UserSetting::where('org_id', $org->id)->first();

        if (!$user_setting) {
            $user_setting = new \App\Models\UserSetting();
        }

        $user_setting->org_id = $org->id;
        $user_setting->can_flight_search = 1;
        $user_setting->can_issue_ticket = is_null($user_setting->can_issue_ticket) ? 1 : !$user_setting->can_issue_ticket;
        $user_setting->save();
        return Response::successResponse(200, "Updated Successfully!");
    }

    public function ticketIssuanceAccess()
    {
        try {

            $can_issue_ticket = false;
            $financial_profile = false;
            $auth_user = \Illuminate\Support\Facades\Auth::user();

            if (role_name() === \App\Models\Role::HEADOFFICE) {
                $can_issue_ticket = true;
                $financial_profile = true;
            } else if (role_name() === 'h-employee') {
                $can_issue_ticket = $auth_user->can_issue_ticket;
                $financial_profile = true;
            } else if (in_array(role_name(), ['branch'])) {
                $can_issue_ticket = \App\Models\UserSetting::where('org_id', org_id())->first()->can_issue_ticket;
                $financial_profile = true;
            } else if (in_array(role_name(), ["b-employee"])) {
                $setting = \App\Models\UserSetting::where('org_id', org_id())->first();
                $can_issue_ticket = $auth_user->can_issue_ticket && $setting->can_issue_ticket;
                $financial_profile = true;
            } else {
                $financial_profile = \App\Models\Organization::where('id', org_id())->value('financial_profile');
            }

            return ['can_issue_ticket' => $can_issue_ticket, 'financial_profile' => $financial_profile];
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function temporaryLimitHistory($uuid)
    {
        try {
            $wallet = \App\Models\Wallet::select('id')
                ->with('temporay_credit_limits.created_by:id,name')->whereOrgId(orgById($uuid))->first();
            return Response::successResponse(200, 'Temporary Limit List', $wallet);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function instantStopLimit($limitId)
    {

        if (!in_array(role_name(), \App\Models\Role::MANAGMENT_ROLES)) {
            return Response::errorResponse(403, "You don't have permission to access this module!");
        }

        try {

            $tem_limit = \App\Models\TemporayCreditLimit::find($limitId);
            $tem_limit->instant_stop_limit = !$tem_limit->instant_stop_limit;
            $tem_limit->save();

            return Response::successResponse(200, "Operation Successfully Performed!");
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }
}
