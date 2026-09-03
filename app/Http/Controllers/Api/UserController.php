<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\Auth\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

class UserController extends Controller
{

    public function __construct(
        protected UserRepositoryInterface $userRepositoryInterface
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    "errors" => [
                        'password' =>  ['Password is incorrect']
                    ]
                ], 404);
            }

            $user = \App\Models\User::whereEmail($request->email)->first();
            if (!$user->status) {
                return Response::errorResponse(400, 'Your account is inactive. Please contact the administrator to activate it.');
            }

            if (!is_null($user?->organization?->status) && $user?->organization?->status != true) {
                return Response::errorResponse(400, "Your organization's account is currently inactive. Please reach out to your administrator to restore access.");
            }

            $message = "Use your Google Authenticator code to verify and complete the login process. Thank you!";

            if ($user->otp_pref != 'google_authenticator') {
                $message = "We have sent an OTP to your registered email. Please verify it to complete your login. Thank you!";

                $otpCode = 123456; //mt_rand(100000, 999999);
                $user->login_otp = $otpCode;
                $user->save();
                $user->notify(new \App\Notifications\LoginOtpNotification($otpCode));
            }

            return Response::successResponse(200, $message, [$user->uuid, $user->otp_pref]);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string',
                'otp' => 'required|numeric',
            ]);
            $user = \App\Models\User::where('uuid', $request->email)->first();

            if (!$user) {
                return Response::errorResponse(404, 'User not found.');
            }

            $is_login_success = true;
            if ($user->otp_pref == 'google_authenticator' && $user->google_auth_verified) {
                
                $google2fa = new Google2FA();

                $valid = $google2fa->verifyKey(
                    $user->google2fa_secret,
                    $request->otp
                );

                if (!$valid) {
                    $is_login_success = false;
                    return Response::errorResponse(400, 'Invalid Google Authenticator OTP.');
                }
            } else {
                
                if ($user->login_otp != $request->otp) {
                    $is_login_success = false;
                    return Response::errorResponse(400, 'Invalid OTP.');
                }
            }

            if (in_array($user->roles->first()->name, \App\Models\Role::AGENCY_ROLES)) {

                $ip = request()->ip();
                $location = [];

                if ($ip !== '127.0.0.1' && $ip !== '::1') {
                    $location = \Illuminate\Support\Facades\Http::timeout(3)
                        ->get("http://ip-api.com/json/{$ip}")
                        ->json();
                }

                \App\Jobs\LogUserLogin::dispatch($user, request()->ip(), request()->userAgent(), $is_login_success, get_device_info(), $location);
            }

            Auth::login($user);
            $user->tokens()->delete();
            $connectors = \App\Models\Connector::where('is_enable', true)->get(['uuid']);
            
            $data = [
                'user' => new UserResource($user),
                'connectors'    => $connectors,
                'preferences'   => preferences($user),
                'token'         => $user->createToken('auth-token')->plainTextToken,
                'token_type'    => 'Bearer'
            ];

            if (in_array(role_name(), \App\Models\Role::AGENCY_ROLES)) {

                $orgId = $user->org_id;
                $balance = balance($orgId);
                $permanent_limit  = \App\Models\Wallet::where('org_id', $orgId)->first()?->per_credit_limit;
                $valid_temp_limit = valid_temp_limit($orgId);
                $totalWalletAmount = $balance + $valid_temp_limit + $permanent_limit;

                $data['balance'] = [
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
            }

            $user->update(['login_otp' => null, 'last_login' => \Carbon\Carbon::now()]);

            return Response::successResponse(200, 'Successfully logged in.', $data);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function logout()
    {
        try {
            /** @var User&HasApiTokens $user */
            $user = Auth::user();
            if ($user) {
                $user->tokens()->delete();
                return Response::successResponse(200, 'Logout successfully...');
            }
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function permissionList(Request $request, string|int $uuid)
    {
        try {
            $permission_list = [
                'permission_list' => \App\Models\Permission::whereJsonContains('preference', $request->get('type'))->get(),
                'selected_permissions' => \App\Models\User::where('uuid', $uuid)->first()->permissions->pluck('uuid'),
            ];
            return Response::successResponse(200, 'Branche Permissions List', $permission_list);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function updatePermission(Request $request, string|int $uuid)
    {
        try {
            $user = \App\Models\User::where('uuid', $uuid)->first();
            $user->syncPermissions($request->all());
            return Response::successResponse(200, 'Branch Permissions Updated!');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function resendOtp(Request $request)
    {

        $request->validate([
            'email' => 'required|string',
        ]);

        try {
            $user = \App\Models\User::where('uuid', $request->email)->first();
            $otpCode = 123456; ///mt_rand(100000, 999999);
            $user->login_otp = $otpCode;
            $user->save();
            $user->notify(new \App\Notifications\LoginOtpNotification($otpCode));
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function forgotPassword(Request $request)
    {

        $request->validate([
            'email' => 'required|string',
        ]);

        try {
            $user = \App\Models\User::where('email', $request->email)->first();
            if (empty($user)) {
                return Response::errorResponse(404, 'Account Not Found');
            }
            $user->notify(new \App\Notifications\SendResetPasswordNotification($user));
            return Response::successResponse(200, 'A password reset email has been sent to your registered email address. Please check your inbox and follow the instructions to reset your password.');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'entity' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&]/',
            ],
        ]);

        try {
            $user = \App\Models\User::where('uuid', $request->entity)->first();
            if (empty($user)) {
                return Response::errorResponse(404, 'Something Went Wrong, Please check your action and try again. Thanks');
            }
            $user->password = \Illuminate\Support\Facades\Hash::make($request->new_password);
            $user->save();
            return Response::successResponse(200, 'Password Updated Successfully!');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function changePassword(Request $request)
    {

        try {
            $reqData = $request->validate([
                'uuid' => 'required|string',
                'new_password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/[a-z]/',
                    'regex:/[A-Z]/',
                    'regex:/[0-9]/',
                    'regex:/[@$!%*?&]/',
                ],
            ]);


            $user = \App\Models\User::where('uuid', $reqData)->first();
            if (is_null($user)) {
                return Response::errorResponse(404, 'User Not Found!');
            }

            $user->password = \Illuminate\Support\Facades\Hash::make($request->new_password);
            $user->save();
            return Response::successResponse(200, 'Password Successfully Updated!');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function userProfile()
    {

        try {

            $user = \App\Models\User::select('id', 'name', 'email', 'phone_number', 'profile_image', 'google_auth_verified', 'otp_pref')->with(['roles' => function ($q) {
                $q->select('name')->first();
            }])->findOrFail(Id());


            return Response::successResponse(200, 'User Profile!', $user);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function organizationPreference(){

        try{

            if(!(is_agency() || is_branch())){
                return Response::errorResponse(400, "You don't have permission to access this module!");
            }
            
            $org = \App\Models\Organization::select('name', 'logo', 'theme_color')->whereId(org_id())->first();

            return Response::successResponse(200, 'Organization Preferences', $org);

        }catch (\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function organizationPreferenceUpdate(Request $request){

        try{

            $reqData = $request->validate([
                'name' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:png,jpg,jpeg'
            ]);

            $organization = \App\Models\Organization::whereId(org_id())->first();

            $image_path   = $organization->logo;
            if (request()->has('logo') && request()->hasFile('logo')) {
                if(!is_null($organization->logo)){
                    $path = Str::after($organization->logo, '/storage/');
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
                $image_path = UPLOAD_FILE(request()->file('logo'), 'logos');
            }

            $organization->name = $reqData['name'];
            $organization->logo = $image_path;
            $organization->save();

            return Response::successResponse(200, 'Organization Preference Update!', $organization);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function updateProfile(Request $request)
    {

        $phoneNumberRule = [
            'required',
            'regex:/^\+?\d{1,4}?[\d\s\-\(\)]{7,15}$/',
            function ($attribute, $value, $fail) {

                $user = \App\Models\User::findOrFail(Id());

                if (
                    $user->phone_number != $value &&
                    \App\Models\User::where('phone_number', $value)
                    ->where('id', '!=', $user->id)
                    ->exists()
                ) {
                    $fail('The phone number has already been taken.');
                }
            }
        ];

        $reqData = $request->validate([
            'phone_number' => $phoneNumberRule,
            'name' => "required|string|max:255",
            'profile_image' => 'nullable|image|mimes:png,jpg,jpeg'
        ]);


        if (request()->has('profile_image') && request()->hasFile('profile_image')) {
            $reqData['profile_image'] = UPLOAD_FILE(request()->file('profile_image'), 'profile_images');
        }

        \App\Models\User::where('id', Id())->update($reqData);

        if ((is_agency() || is_branch()) && \App\Models\Organization::whereId(\Illuminate\Support\Facades\Auth::user()->org_id)->exists()) {
            \App\Models\Organization::whereId(\Illuminate\Support\Facades\Auth::user()->org_id)->update(['name' => $reqData['name']]);
        }

        $user = \App\Models\User::find(Id());

        $user->tokens()->delete();

        $connectors = \App\Models\Connector::where('is_enable', true)->get(['uuid']);
        $data = [
            'user' => new UserResource($user),
            'connectors' => $connectors,
            'token' => $user->createToken('auth-token')->plainTextToken,
            'token_type' => 'Bearer'
        ];

        return Response::successResponse(200, 'User Profile Updated!', $data);
    }

    public function updatePassword(Request $request)
    {

        $reqData = $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|max:16|min:8'
        ]);

        $user = \App\Models\User::findOrFail(Id());
        if (\Illuminate\Support\Facades\Hash::check($reqData['old_password'], $user->password)) {

            $user->password = \Illuminate\Support\Facades\Hash::make($reqData['new_password']);
            $user->save();

            return Response::successResponse(200, 'Successfully Updated Password!');
        } else {
            return Response::errorResponse(404, 'Provided Old Password is incorrect');
        }
    }

    public function verify2FA(Request $request)
    {
        
        $request->validate([
            'entity' => 'required|string',
            'otp' => 'required|numeric|digits:6',
        ]);

        $user = \App\Models\User::where('uuid', $request->entity)->first();

        if(empty($user)){
            return Response::errorResponse(400, 'Something Went Wrong!');
        }

        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(
            $user->google2fa_secret,
            $request->otp
        );
        
        if (!$valid) {
            return Response::errorResponse(400, 'Invalid Google Authenticator OTP.');
        }
        $user->google_auth_verified = true;
        $user->save();
        return Response::successResponse(200, 'Setup Successfully!');

    }

    public function enable2FA(Request $request)
    {
        $request->validate([
            'entity' => 'required|string',
        ]);

        $user = \App\Models\User::where('uuid', $request->entity)->first();

        if(empty($user)){
            return Response::errorResponse(400, 'Something Went Wrong!');
        }

        $google2fa = app('pragmarx.google2fa');
        $secretKey = $user->google2fa_secret;
        
        if(empty($user->google2fa_secret)){
            $secretKey = $google2fa->generateSecretKey();
        }

        $user->google2fa_secret = $secretKey;
        $user->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            env('APP_NAME'),
            $user->email,
            $secretKey
        );
        
        return Response::successResponse(200, 'QR Code!', $qrCodeUrl);
    }

    public function otpPreference(Request $request)
    {

        $user = \App\Models\User::findOrFail(Id());

        if (empty($user)) {
            return Response::errorResponse(400, 'Something Went Wrong!');
        }

        $reqData = $request->validate([
            'preference' => 'required|string|in:email,google_authenticator',
            'otp' => [
                'nullable',
                'digits:6',
                function ($attribute, $value, $fail) use ($request, $user) {
                    if (
                        $request->preference === 'google_authenticator' &&
                        !$user->google_auth_verified &&
                        empty($value)
                    ) {
                        $fail('The OTP field is required when using Google Authenticator and it is not yet verified.');
                    }
                },
            ],
        ]);

        if (!$user->google_auth_verified && $reqData['preference'] == 'google_authenticator') {
            $google2fa = new Google2FA();

            $valid = $google2fa->verifyKey(
                $user->google2fa_secret,
                $request->otp
            );

            if (!$valid) {
                return Response::errorResponse(400, 'Invalid Google Authenticator OTP.');
            }
            $user->google_auth_verified = true;
        }

        $user->otp_pref = $reqData['preference'];
        $user->save();

        return Response::successResponse(200, 'Successfully Updated!');
    }
}
