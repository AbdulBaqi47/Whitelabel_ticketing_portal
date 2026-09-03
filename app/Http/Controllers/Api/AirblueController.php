<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;

class AirblueController extends Controller
{
    public function airblueAnci(Request $request){

        $validated = $request->validate([
            'booking_id' => 'required|string|size:11|exists:bookings,booking_id',
        ]);

        $booking = $this->bookingById($validated['booking_id']);

        if ($booking->status != 'confirmed') {
            return Response::errorResponse(400, 'Something went wrong, Booking is not in Confirmed State');
        }

        $extras = (new \App\Services\AirBlueService\AirService())->extras(pnr_meta: $booking->pnr_meta, booking: $booking);

        return Response::successResponse(200, 'Airblue Extras', $extras);

        return $extras;
    }

    public function bookingById($booking_id){
        return \App\Models\Booking::whereBookingId($booking_id)->first();
    }

    public function extraAddOrUpdate(Request $request){
        
        $request->validate([
            'booking_id' => 'required|string|size:11|exists:bookings,booking_id',
        ]);

        $booking = $this->bookingById($request->booking_id);
        $extras = (new \App\Services\AirBlueService\AirService())->extraAddOrUpdate($request->all(), $booking->pnr_meta);
        if(!$extras['status']){
            return Response::errorResponse(400, $extras['message']);
        }
        return Response::successResponse(200, $extras['message']);
    }
}
