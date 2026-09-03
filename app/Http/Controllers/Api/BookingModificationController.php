<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModifyBookingPricingRequest;
use App\Http\Requests\ModifyBookingRequest;
use App\Models\Booking;
use Illuminate\Support\Facades\Response;

class BookingModificationController extends Controller
{
    public function manaulTicketIssuance(Request $request)
    {

        $reqData = $request->validate([
            'booking_id' => 'required|string|size:11|exists:bookings,booking_id',
            'pnr' => 'required|string|max:10|min:6',
            'tickets' => 'required|array|min:1',
            'tickets.*.passenger_id' => 'required|exists:passengers,id',
            'tickets.*.ticket_number' => 'required|string|max:50',

            'fare_break_down' => 'required|array',
            'fare_break_down.*.tax' => 'nullable|string',
            'fare_break_down.*.currency' => 'nullable|string|max:3',
            'fare_break_down.*.quantity' => 'nullable|integer',
            'fare_break_down.*.base_fare' => 'nullable|string',
            'fare_break_down.*.discount_psf' => 'nullable|string',
            'fare_break_down.*.gross_amount' => 'nullable|string',
            'fare_break_down.*.total_amount' => 'nullable|string',

            'total_amount' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0',
            'gross_fare' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
            'base_fare' => 'required|numeric|min:0',
        ]);

        try {

            \App\Models\Booking::where('booking_id', $reqData['booking_id'])->update([
                'status' => 'issued',
                'booking_pnr' => $reqData['pnr'],
                'base_fare' => $reqData['base_fare'],
                'tax' => $reqData['tax'],
                'gross_fare' => $reqData['gross_fare'],
                'discount' => $reqData['discount'],
                'total_amount' => $reqData['total_amount'],
                'fare_break_down' => $reqData['fare_break_down']
            ]);

            foreach ($reqData['tickets'] as $value) {
                $passenger = \App\Models\Passenger::find($value['passenger_id']);
                $passenger->ticket_number = $value['ticket_number'];
                $passenger->save();
            }


            return Response::successResponse(200, 'Updated Successfully!');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function fareModification(
        ModifyBookingPricingRequest $request,
        $booking_id
    ) {
        $booking = Booking::where('booking_id', $booking_id)->first();

        if (!$booking) {
            return Response::errorResponse(404, 'Booking Not Found!');
        }

        if (role_name() !== \App\Models\Role::HEADOFFICE) {
            return Response::errorResponse(400, "You don't have Permission to access this module");
        }

        $validated = $request->validated();

        $booking->base_fare     = $validated['base_fare'];
        $booking->tax           = $validated['tax'];
        $booking->gross_fare    = $validated['gross_fare'];
        $booking->discount      = $validated['discount'];
        $booking->total_amount  = $validated['total_amount'];
        $booking->fare_break_down = booking_fare_break_down($validated['fare_break_down']);
        $booking->save();

        unset($validated['remarks']);
        $booking_pricing                    = new \App\Models\BookingPricingModification();
        $booking_pricing->booking_id        = $booking->id;
        $booking_pricing->user_id           = Id();
        $booking_pricing->modified_pricing  = $validated;
        $booking_pricing->old_pricing       = old_pricing($validated);
        $booking_pricing->remarks           = request()->remarks;
        $booking_pricing->save();

        return Response::successResponse(200, 'Updated Successfully!');
    }

    public function fareHistory($booking_id)
    {
        $booking = Booking::where('booking_id', $booking_id)->first();

        if (!$booking) {
            return Response::errorResponse(404, 'Booking Not Found!');
        }

        if (role_name() !== \App\Models\Role::HEADOFFICE) {
            return Response::errorResponse(400, "You don't have Permission to access this module");
        }

        $booking_pricing_modifications = \App\Models\BookingPricingModification::select('modified_pricing', 'old_pricing', 'remarks', 'created_at')
            ->where('booking_id', $booking->id)->orderBy('id', 'DESC')->get();
        return Response::successResponse(200, 'Booking Fare Modification List!', $booking_pricing_modifications);
    }

    public function modifyBooking(ModifyBookingRequest $request, $booking_id)
    {
        $booking = \App\Models\Booking::query()->where('booking_id', $booking_id)->with(['refund_request:booking_id,refunded_amount','booked_segments.childs', 'booked_segments.operating_airline:name,thumbnail,iata_code','booked_segments.marketing_airline:name,thumbnail,iata_code','booked_segments.d_airport:name,iata_code,municipality,country','booked_segments.a_airport:name,iata_code,municipality' ,'passengers', 'airline:id,name,iata_code', 'organization:id,name'])->orgFilter()->firstOrFail();

        if (!$booking) {
            return Response::errorResponse(404, 'Booking Not Found!');
        }

        if (role_name() !== \App\Models\Role::HEADOFFICE) {
            return Response::errorResponse(400, "You don't have Permission to access this module");
        }

        $validated = $request->validated();

        $booking_modification = new \App\Models\BookingHistory();
        $booking_modification->booking_id = $booking->id;
        $booking_modification->user_id = Id();
        $booking_modification->modified_data = $validated;
        $booking_modification->old_data = $booking->toArray();
        $booking_modification->save();

        $passengers = \App\Models\Passenger::whereBookingId($booking->id)->get()->keyBy('id');        
        foreach ($validated['passengers'] as $passengerData) {
            $passenger = $passengers->get($passengerData['passenger_id']);
            if ($passenger) {
                $passenger->ticket_number = $passengerData['ticket_number'];
                $passenger->d_type = $passengerData['d_type'];
                $passenger->d_number = $passengerData['d_number'];
                $passenger->d_expiry = $passengerData['d_expiry'];
                $passenger->date_of_birth = $passengerData['date_of_birth'];
                $passenger->save();
            }
        }

        $cabin_fullname = "";

        foreach(booked_segment_modify($validated['booked_segments']) as $segment){


            $booking_segment = \App\Models\BookedSegment::find($segment['id']);
            if($booking_segment){
                $cabin_fullname = $booking_segment->cabin_fullname;
            }

            if(!$booking_segment){
                $booking_segment = new \App\Models\BookedSegment();
                $booking_segment->booking_id = $booking->id;
                $booking_segment->cabin_fullname = $cabin_fullname;
                $booking_segment->flight_status_code = 'HK';
                $booking_segment->flight_type = 'outbound';
                $booking_segment->departure_terminal = null;
                $booking_segment->arrival_terminal = null;
            }
            $booking_segment->seg_origin = $segment['seg_origin'];
            $booking_segment->seg_destination = $segment['seg_destination'];
            $booking_segment->flight_pnr = $segment['flight_pnr'];
            $booking_segment->m_airline = $segment['m_airline'];
            $booking_segment->m_flight_number = $segment['m_flight_number'];
            $booking_segment->o_airline = $segment['o_airline'];
            $booking_segment->o_flight_number = $segment['o_flight_number'];
            $booking_segment->meal = $segment['meal'] == 'yes' ? meal_helper() : null;
            $booking_segment->seg_departure_datetime = \Carbon\Carbon::parse($segment['seg_departure_datetime'])->format('Y-m-d H:i:s');
            $booking_segment->seg_arrival_datetime = \Carbon\Carbon::parse($segment['seg_arrival_datetime'])->format('Y-m-d H:i:s');
            $booking_segment->flight_duration = flight_duration($segment['seg_departure_datetime'], $segment['seg_arrival_datetime'], $segment['seg_origin'], $segment['seg_destination']);
            $booking_segment->save();


            if(array_key_exists('childs', $segment) && count($segment['childs']) > 0){
                foreach($segment['childs'] as $child_segment_data){

                    $child_segment = \App\Models\BookedSegment::find($child_segment_data['id']);

                    if(!$child_segment){
                        $child_segment = new \App\Models\BookedSegment();
                        $child_segment->booking_id = $booking->id;
                        $child_segment->parent_id = $booking_segment->id;
                        $child_segment->cabin_fullname = $cabin_fullname;
                        $child_segment->flight_status_code = 'HK';
                        $child_segment->flight_type = 'outbound';
                        $child_segment->departure_terminal = null;
                        $child_segment->arrival_terminal = null;
                    }

                    $child_segment->seg_origin = $child_segment_data['seg_origin'];
                    $child_segment->seg_destination = $child_segment_data['seg_destination'];
                    $child_segment->flight_pnr = $child_segment_data['flight_pnr'];
                    $child_segment->m_airline = $child_segment_data['m_airline'];
                    $child_segment->m_flight_number = $child_segment_data['m_flight_number'];
                    $child_segment->o_airline = $child_segment_data['o_airline'];
                    $child_segment->o_flight_number = $child_segment_data['o_flight_number'];
                    $child_segment->meal = $child_segment_data['meal'] == 'yes' ? meal_helper() : null;
                    $child_segment->seg_departure_datetime = \Carbon\Carbon::parse($child_segment_data['seg_departure_datetime'])->format('Y-m-d H:i:s');
                    $child_segment->seg_arrival_datetime = \Carbon\Carbon::parse($child_segment_data['seg_arrival_datetime'])->format('Y-m-d H:i:s');
                    $child_segment->flight_duration = flight_duration($child_segment_data['seg_departure_datetime'], $child_segment_data['seg_arrival_datetime'], $child_segment_data['seg_origin'], $child_segment_data['seg_destination']);
                    $child_segment->save();
                }
            }
        }

        $booking->baggage = $validated['baggage'] ?? null;
        $booking->status = $validated['status'];
        $booking->save();

        return Response::successResponse(200, 'Booking Modified Successfully!');
    }
}
