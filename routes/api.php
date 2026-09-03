<?php

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json(new UserResource($request->user()));
    });
});

Route::post('/broadcasting/auth', 'Illuminate\Broadcasting\BroadcastController@authenticate')->middleware('auth:sanctum');

Route::middleware('api', 'forcejsonresponse')->prefix('v1')->group(function () {
    /** Auth Api Routes */
    Route::prefix('auth')->controller(\App\Http\Controllers\Api\UserController::class)
        ->group(function () {
            Route::post('login', 'login');
            Route::post('re-send', 'resendOtp');
            Route::post('verify', 'verifyOtp');
            Route::post('forgot-password', 'forgotPassword');
            Route::post('reset-password', 'resetPassword');
            Route::post('check-set-password', 'checkSetPassword');
            Route::post('logout', 'logout')->middleware('auth:sanctum');
            Route::post('enable-2fa', 'enable2FA');
            Route::post('verify-2fa', 'verify2FA');
            Route::post('admin-change-password', 'changePassword')->middleware('auth:sanctum');
        });

    Route::get('user-profile', [\App\Http\Controllers\Api\UserController::class, 'userProfile'])->middleware('auth:sanctum');
    Route::patch('update-profile', [\App\Http\Controllers\Api\UserController::class, 'updateProfile'])->middleware('auth:sanctum');
    Route::patch('update-password', [\App\Http\Controllers\Api\UserController::class, 'updatePassword'])->middleware('auth:sanctum');
    Route::patch('otp-preference',  [\App\Http\Controllers\Api\UserController::class, 'otpPreference'])->middleware('auth:sanctum');

    Route::get('organization-preference', [\App\Http\Controllers\Api\UserController::class, 'organizationPreference'])->middleware('auth:sanctum');
    Route::patch('organization-preference-update', [\App\Http\Controllers\Api\UserController::class, 'organizationPreferenceUpdate'])->middleware('auth:sanctum');

    Route::prefix('setting')->middleware('auth:sanctum')->controller(\App\Http\Controllers\Api\SettingController::class)
        ->group(function () {
            Route::post('general-setting', 'generalSetting');
            Route::post('temporary-limit', 'temporaryLimit');
            Route::get('temporary-limit-history/{uuid}', 'temporaryLimitHistory');
            Route::get('finanical-profile', 'financialProfile');
            Route::patch('google-autheticator-disable', 'googleAuthenticatorDisable');
            Route::patch('ticket-issuance', 'ticketIssuance');
            Route::patch('employee-ticket-issuance', 'employeeTicketIssuance');
            Route::patch('branch-ticket-issuance', 'branchTicketIssuance');
            Route::get('ticket-issuance-access', 'ticketIssuanceAccess');
            Route::post('instant-stop-limit/{limitId}', 'instantStopLimit');
            Route::post('reset-2fa', 'reset2FA');
        });


    Route::prefix('/flight')->middleware('auth:sanctum')->group(function () {
        Route::post('/search', [\App\Http\Controllers\Api\FlightController::class, 'search']);
        Route::get('/pricing/{price_id}', [\App\Http\Controllers\Api\FlightController::class, 'flightPricing']);
        Route::post('/flydubai-anci', [\App\Http\Controllers\Api\FlyDubaiController::class, 'anciSelection']);
        Route::post('/flydubai-seat-offers', [\App\Http\Controllers\Api\FlyDubaiController::class, 'seatSelection']);
    });

    Route::post('/import-pnr', [\App\Http\Controllers\Api\ImportPNRController::class, 'importPnr'])->middleware('auth:sanctum');

    Route::get('permission-list/{uuid}', [\App\Http\Controllers\Api\UserController::class, 'permissionList'])->middleware('auth:sanctum');
    Route::post('update-permission/{uuid}', [\App\Http\Controllers\Api\UserController::class, 'updatePermission'])->middleware('auth:sanctum');

    /** Bank Acccounts Routes **/
    Route::prefix('settings/bank-account')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\BankAccountController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::get('/show/{uuid}', 'show');
            Route::get('/delete/{uuid}', 'delete');
            Route::get('list', 'bankList');
            Route::get('drop-down', 'bankDropDown');
            Route::post('show-to-agencies/{uuid}', 'showToAgencies');
        });
    /** Connectors Routes **/
    Route::prefix('settings/connector')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\ConnectorController::class)->group(function () {
            Route::post('/update', 'update');
            Route::patch('/status/{type}', 'statusChange');
            Route::get('/show/{type}', 'show');
            Route::get('/access-token/{type}', 'accessToken');
            Route::get('/drop-down', 'dropDown');
        });
    /** Supplier Routes **/
    Route::prefix('settings/supplier')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\SupplierController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::patch('/status/{uuid}', 'statusChange');
            Route::get('/show/{uuid}', 'show');
            Route::get('/delete/{uuid}', 'delete');
            Route::get('/drop-down', 'dropDown');
        });
    /**
     * Airlines Routes
     */
    Route::prefix('settings/airline')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\AirlineController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::patch('/status/{uuid}', 'statusChange');
            Route::get('/show/{uuid}', 'show');
            Route::get('/delete/{uuid}', 'delete');
            Route::get('/drop-down', 'dropDown');
            Route::get('/label-drop-down', 'labelDropDown');
        });
    /** Airports Routes **/
    Route::prefix('settings/airport')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\AirportController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::patch('/status/{uuid}', 'statusChange');
            Route::get('/show/{uuid}', 'show');
            Route::get('/delete/{uuid}', 'delete');
        });

    Route::get('/locations', [\App\Http\Controllers\Api\AirportController::class, 'dropDown'])->middleware('auth:sanctum');
    /** Countries Routes **/
    Route::prefix('settings/country')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\CountryController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::patch('/status/{uuid}', 'statusChange');
            Route::get('/show/{uuid}', 'show');
            Route::get('/delete/{uuid}', 'delete');
            Route::get('/drop-down', 'dropDown');
        });
    /** Cities Routes **/
    Route::prefix('settings/city')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\CityController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::patch('/status/{uuid}', 'statusChange');
            Route::get('/show/{uuid}', 'show');
            Route::get('/delete/{uuid}', 'delete');
        });
    /** News&Alerts Routes **/
    Route::prefix('settings/news')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\NewAndAlertController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::get('/show/{uuid}', 'show');
            Route::get('/delete/{uuid}', 'delete');
        });
    /** Airline Margins */
    Route::prefix('settings/airline-margin')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\AirlineMarginController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::get('/show/{uuid}', 'show');
            Route::get('/delete/{uuid}', 'delete');
        });
    /** branches **/
    Route::prefix('organization/branch')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\Organization\BranchController::class)->group(function () {
            Route::get('index', 'index');
            Route::post('store', 'store');
            Route::put('update/{uuid}', 'update');
            Route::get('delete/{uuid}', 'delete');
            Route::patch('status-change/{uuid}', 'statusChange');
            Route::get('drop-down', 'dropDown');
            Route::post('drop-down-by-type', 'dropDownByType');
            Route::patch('show-all-bookings/{uuid}', 'showAllBookings');
        });

    /** Agencies */
    Route::prefix('organization/agency')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\Organization\AgencyController::class)->group(function () {
            Route::get('index', 'index');
            Route::post('store', 'store');
            Route::put('update/{uuid}', 'update');
            Route::get('delete/{uuid}', 'delete');
            Route::patch('status-change/{uuid}', 'statusChange');
            Route::patch('financial-profile/{uuid}', 'financialProfile');
            Route::get('drop-down', 'dropDown');
        });

    /** Employees */
    Route::prefix('organization/employee')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\Organization\EmployeeController::class)->group(function () {
            Route::get('index', 'index');
            Route::post('store', 'store');
            Route::put('update/{uuid}', 'update');
            Route::get('delete/{uuid}', 'delete');
            Route::patch('status-change/{uuid}', 'statusChange');
        });

    Route::controller(\App\Http\Controllers\Api\Organization\RegisterationRequestController::class)->prefix('register-request')->group(function () {
        Route::post('/', 'registerRequest'); // POST register-request
        Route::get('/list', 'list')->middleware('auth:sanctum'); // GET register-request/list
        Route::post('/store', 'store')->middleware('auth:sanctum'); // POST register-request/store
        Route::post('/reject', 'reject')->middleware('auth:sanctum'); // POST register-request/reject
    });

    Route::prefix('booking')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\BookingController::class)->group(function () {
            Route::post('initiating', 'initiate');
            Route::get('confirm-availability', 'confirmAvailability');
            Route::post('booking-confirm', 'bookingConfirm');
            Route::post('get-booking', 'getBooking');
            Route::post('cancel-booking', 'cancelBooking');
            Route::post('issue-ticket', 'issueTicket');
            Route::get('getById/{id}', 'bookingGetById');
            Route::post('void-ticket', 'voidTickets');
            Route::post('flydubai-void', 'voidBookingFlyDubai');
            Route::post('search', 'bookingSearch');
            Route::post('email-booking', 'emailBooking');
            Route::get('ticket-otp-email', 'ticketOtpEmail')->middleware('auth:sanctum');
            Route::post('download-booking', 'downloadBooking');
            Route::get('bookings-list', 'bookingList');
            Route::post('refund-booking', 'refundBooking');
            Route::post('import-booking', 'importBooking');
            // update booking details (Flight, Fare, Passengers and Baggage) routes
            Route::patch('update-passengers/{booking_id}', 'updateBookingPassengers');
            Route::patch('update-fare/{booking_id}', 'updateBookingFareDetails');
            Route::patch('update-baggage/{booking_id}', 'updateBookingBaggage');
            Route::patch('update-flight/{booking_id}', 'updateBookingFlightDetails');

            Route::patch('manual-issuance', 'manaulTicketIssuance');
        });

    Route::prefix('booking')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\BookingModificationController::class)->group(function () {
            Route::patch('fare-modification/{booking_id}', 'fareModification');
            Route::get('fare-history/{booking_id}', 'fareHistory');
            Route::put('modify-booking/{booking_id}', 'modifyBooking');
        });



    Route::prefix('airblue')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\AirblueController::class)->group(function () {
            Route::get('/anci', 'airblueAnci');
            Route::post('/add-update-anci', 'extraAddOrUpdate');
        });

    Route::prefix('dashboard')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\DashboardController::class)->group(function () {
            Route::get('stats', 'stats');
            Route::get('sale-statistics', 'salesGraphStatistics');
            Route::get('travel-calender', 'travelCalender');
        });

    Route::prefix('financial-dashboard')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\FinancialDashboardController::class)->group(function () {
            Route::get('top-airline-stats', 'topAirlinesStats');
            Route::get('provider-performance-stats', 'providerPerformanceStats');
            Route::get('agency-login-stats', 'agencyLoginStats');
        });
    Route::get('dashboard-receivables', [\App\Http\Controllers\Api\FinancialDashboardController::class, 'receivables'])->middleware('auth:sanctum');

    Route::prefix('margin')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\MarginManagerController::class)->group(function () {
            Route::get('branch-margin', 'getByMarginId');
            Route::post('assign-margin-branch', 'assignToBranch');
            Route::post('assign-to-branch-by-airline', 'assignToBranchByAirline');
            Route::get('branch-margins', 'branchMargins');
            Route::get('agencies-margins/{uuid}', 'agenciesMargins');
            Route::post('agency-margin-assign/{uuid}', 'agencyMarginAssign');
            Route::get('agency-airline-margin-list', 'agencyAirlineMarginList');
        });

    Route::prefix('agent-management')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\Organization\AgentManagementController::class)->group(function () {
            Route::get('index', 'index');
            Route::post('store', 'store');
            Route::put('update/{uuid}', 'update');
            Route::get('delete/{uuid}', 'delete');
            Route::patch('status-change/{uuid}', 'statusChange');
        });

    Route::prefix('refund-request')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\RefundTktController::class)->group(function () {
            Route::get('generate/{booking_id}', 'generateRequest');
            Route::get('refund-request', 'organizationRefunds');
            Route::get('my-refund-request', 'agencyRefundRequests');
            Route::get('my-refund-request', 'agencyRefundRequests');
            Route::get('show-fare-rule/{booking_id}', 'showFareRule');
            Route::get('reject/{booking_id}', 'refundReject');
            Route::post('make-refund/{booking_id}', 'makeRefund');
        });

    Route::prefix('deposits')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\ManageDepositController::class)->group(function () {
            Route::get('agency-list', 'agencyList');
            Route::get('org-list', 'orgList');
            Route::post('request', 'depositRequest');
            Route::post('reject/{uuid}', 'depositReject');
            Route::post('accept/{uuid}', 'depositAccept');
            // branch's agency deposit routes
            // Route::get('branch/agency-list', 'branchAgencyDepositList');
            // Route::get('branch/agencies/drop-down', 'branchAgenciesDropDown');
        });

    Route::post('passport-info', [\App\Http\Controllers\Api\PassportController::class, 'uploadPassport'])->middleware('auth:sanctum');


    Route::prefix('account-head')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\AccountHeadController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::patch('/status/{uuid}', 'statusChange');
            Route::get('/delete/{uuid}', 'delete');
            Route::get('/drop-down', 'dropDown');
        });

    Route::prefix('chart-of-account')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\AccountHeadController::class)->group(function () {
            Route::get('/index', 'index');
            Route::post('/store', 'store');
            Route::put('/update/{uuid}', 'update');
            Route::patch('/status/{uuid}', 'statusChange');
            Route::get('/delete/{uuid}', 'delete');
            Route::get('/drop-down', 'dropDown');
        });

    Route::prefix('transactions')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\Transaction\TransactionController::class)->group(function () {
            Route::get('agency/{agencyId?}', 'agencyTransactionlist');
            Route::get('management-list', 'managementTransactionList');
            Route::post('make-payment/{bookingId}', 'makePayment');
            Route::post('reverse-payment/{bookingId}', 'reverseTransaction');
            Route::post('management-make-payment/{bookingId}', 'managementMakePayment');
            Route::get('booking-transaction/{bookingId}', 'bookingTransactionById');
            Route::get('financial-details/{bookingId}/{agencyId?}', 'financialDetails');
        });

    Route::prefix('agency-panel')->middleware('auth:sanctum')
        ->controller(\App\Http\Controllers\Api\Organization\AgencyManagementPanelController::class)->group(function () {
            Route::get('profile-overview/{uuid}', 'profileOverview');
            Route::get('deposits/{uuid}', 'depositsHistory');
            Route::get('login-logs/{uuid}', 'loginLogs');
            Route::get('employee-sub-agent/{uuid}', 'employeeSubAgent');
        });
});

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello World!']);
});
