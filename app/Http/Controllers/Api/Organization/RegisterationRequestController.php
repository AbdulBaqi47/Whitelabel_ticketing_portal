<?php

namespace App\Http\Controllers\Api\Organization;

use Illuminate\Support\Facades\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgencyRequest;
use Illuminate\Http\Request;

class RegisterationRequestController extends Controller
{
    
    public function registerRequest(Request $request){

        $reqData = $request->validate([
            'agency_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string',
            'phone_number' => 'required|string|regex:/^\+?[0-9]{11,13}$/',
        ]);
        
        \App\Models\RegisterationRequest::create($reqData);
        return Response::successResponse(201, 'Your request has been successfully received. We appreciate your trust in our services.');
    }

    public function store(AgencyRequest $request){

        $reqData = $request->validated();

        if(!isset(request()->register_id)){
            return Response::errorResponse(422, 'Register Must Be Required!');
        }
    
        $org = \App\Models\Organization::create([
            'name' => $reqData['name'],
            'address' =>  $reqData['address'],
            'city' => $reqData['city'],
            'parent_id' => $reqData['org_id']
        ]);

        $user = \App\Models\User::create([
            'name' => $reqData['name'],
            'org_id' => $org->id,
            'phone_number' => $reqData['phone_number'],
            'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
            'email' => strtolower($reqData['email'])
        ]);

        \App\Models\UserSetting::create([
            'org_id' => $org->id,
            'can_issue_ticket' => true,
            'void_charges' => 1000
        ]);

        \App\Models\Wallet::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'org_id' => $org->id,
            'per_credit_limit' => $reqData['per_credit_limit'],
            'available_per_credit_limit' => $reqData['per_credit_limit'],
        ]);

        $role = \App\Models\Role::where(['name' => 'agency'])->firstOrFail();

        $permissions = \App\Models\Permission::whereJsonContains('preference', 'agency')->where('by_default', true)->pluck('uuid')->toArray();

        $user->assignRole($role);

        $user->syncPermissions($permissions);

        $uuid = request()->register_id;

        \App\Models\RegisterationRequest::where('uuid', $uuid)->delete();

        \Illuminate\Support\Facades\Notification::send($user, new \App\Notifications\UserSetPasswordLink($user));

        return Response::successResponse(201,'Agency Created', $user);
    }

    public function list(Request $request){
        $per_page = $request->get('perPage') ?: config('app.per_page');
        $requests = \App\Models\RegisterationRequest::where('mark_read', false)->orderBy('id', 'DESC')->paginate($per_page);


       return Response::successResponse(200,'Registeration Request List', $requests);
    }

    public function reject(Request $request){
        
        $reqData = $request->validate(['register_id'=>'required|string|exists:registeration_requests,uuid']);

        \App\Models\RegisterationRequest::where('uuid', $reqData['register_id'])->delete();

        return Response::successResponse(200,'Registeration Request Successfully Rejected');
    }
}
