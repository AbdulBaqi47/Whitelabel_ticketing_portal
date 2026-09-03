<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class RefundTktController extends Controller
{

    public function __construct(
        protected \App\Interfaces\RefundTktRepositoryInterface $RRepositoryInterface
    ) {}

    public function generateRequest($booking_id){

        $current_user = \Illuminate\Support\Facades\Auth::user();
        $role_name = role_name();
        if(in_array($role_name, \App\Models\Role::MANAGMENT_ROLES)){
            Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        try{    

            $booking = \App\Models\Booking::where(['booking_id'=> $booking_id, 'org_id' => $current_user->org_id])->first();
        
            if(is_null($booking)){
                Response::errorResponse(404, 'Booking Not Found!');    
            }

           $booking_refund_request = \App\Models\RefundRequest::where('booking_id', $booking->id)->first();

            if(!is_null($booking_refund_request )){
                Response::errorResponse(404, 'Already Requested for Refund Thanks!');   
            }

            $this->RRepositoryInterface->generateRequest($booking);
            $booking->refund_status = 'processing';
            $booking->save();
            return Response::successResponse(200, 'Refund Request Generated Successfully!');
    
        }catch(\Exception $e){
            Response::errorResponse(500, $e->getMessage());
        }
    }

    public function agencyRefundRequests(){

        try{

            $current_user = \Illuminate\Support\Facades\Auth::user();

            $refund_requests = \App\Models\RefundRequest::with('booking:id,booking_id,booking_pnr,created_at,booked_by,supplier_id,airline_id,base_fare,tax,total_amount', 'booking.airline:id,name' ,'booking.supplier:id,name','booking.booked_by_user:id,name', 'organization:id,name')->where('org_id', $current_user->org_id)->
            orderBy('id', 'desc')->paginate();

            return Response::successResponse(200, 'Refund Request List', $refund_requests);
        }catch(\Exception $e){
            Response::errorResponse(500, $e->getMessage());
        }
    }

    public function organizationRefunds(){
        try{
            $current_user = \Illuminate\Support\Facades\Auth::user();
            $role_name = role_name();
            $refund_requests = \App\Models\RefundRequest::with('booking:id,booking_id,booking_pnr,created_at,booked_by,supplier_id,airline_id,base_fare,tax,total_amount', 'booking.airline:id,name' ,'booking.supplier:id,name','booking.booked_by_user:id,name','requested_by:id,name', 'organization:id,name')->where(function($q) use ($role_name, $current_user){
                if(in_array($role_name, ['branch', 'b-employee'])){
                    $orgIds = \App\Models\Organization::whereParentId($current_user->org_id)->pluck('id')->toArray();
                    $q->whereIn('org_id', array_merge([$current_user->org_id], $orgIds));
                }
            })->orderBy('id', 'desc')->paginate();

            return Response::successResponse(200, 'Refund Request List',$refund_requests);

        }catch(\Exception $e){
            Response::errorResponse(500, $e->getMessage());
        }
    }

    public function showFareRule($booking_id){

        $role_name = role_name();
        if(!in_array($role_name, \App\Models\Role::MANAGMENT_ROLES)){
            Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        try{
            $booking = \App\Models\Booking::where('booking_id', $booking_id)->first();

            $fare_rules = is_null($booking?->fare_rules) ? false : $booking?->fare_rules;

            return Response::successResponse(200, 'Fare Rule List',$fare_rules);
        }catch(\Exception $e){
            Response::errorResponse(500, $e->getMessage());
        }
    }

    public function makeRefund(Request $request, $booking_id){

        $role_name = role_name();

        if(!in_array($role_name, \App\Models\Role::MANAGMENT_ROLES)){
            Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        $booking = \App\Models\Booking::where('booking_id', $booking_id)->first();
        if(is_null($booking)){
            Response::errorResponse(500, 'Booking Not Found!');
        }
        $maxAmount = $booking->total_amount;

        $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:1', "max:$maxAmount"],
        ]);
        return Response::successResponse(200, 'Booking Refunded Successfully');
    }

    public function refundReject($booking_id){

        $role_name = role_name();
        if(!in_array($role_name, \App\Models\Role::MANAGMENT_ROLES)){
            Response::errorResponse(400, "You Don't have permission to access this module.");
        }

        try{

            $booking = \App\Models\Booking::where('booking_id', $booking_id)->first();

            if(is_null($booking)){
                Response::errorResponse(404, "Booking Not Found!");
            }

            $booking->refund_status = 'rejected';
            $booking->refund_request()->status = 'rejected';
            $booking->save();

            return Response::successResponse(200, "Successfull Rejected Refund Request!");
        }catch(\Exception $e){
            Response::errorResponse(500, $e->getMessage());
        }
    }
    
}
