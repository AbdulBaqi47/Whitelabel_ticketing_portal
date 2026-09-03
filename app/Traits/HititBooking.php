<?php

namespace App\Traits;

trait HititBooking
{

    public function segmentData($data)
    {
        $bookOriginDestinationOptions = array();
        $bookOriginDestinationOptionList = array();
        $bookFlightSegmentList = array();

        foreach(arrayConversion($data) as $key => $sector){
            foreach ($sector['segment_data'] as $segKey => $segValue) {
                $fareInfoList = arrayConversion($sector['fare_info_list'])[$segKey];
                $bookFlightSegmentList = array(
                    'actionCode' => 'NN',
                    'bookingClass' => array(
                        'cabin' => $fareInfoList['cabin'],
                        'resBookDesigCode' => $fareInfoList['resBookDesigCode'],
                        'resBookDesigQuantity' => 9,
                    ),
                    'fareInfo' => array(
                        'cabinClassCode' => $fareInfoList['cabinClassCode'],
                        'fareBaggageAllowance' => (array) $fareInfoList['fareBaggageAllowance'],
                        'fareGroupName' => $fareInfoList['fareGroupName'],
                        'fareReferenceCode' => $fareInfoList['fareReferenceCode'],
                        'fareReferenceID' => $fareInfoList['fareReferenceID'],
                        'fareReferenceName' => $fareInfoList['fareReferenceName'],
                        'flightSegmentSequence' => $fareInfoList['flightSegmentSequence'],
                        'notValidAfter' => $fareInfoList['notValidAfter'],
                        'notValidBefore' => $fareInfoList['notValidBefore'],
                        'resBookDesigCode' => $fareInfoList['resBookDesigCode'],
                        'showBaseFare' => ''
                    ),
                    'addOnSegment' => '',
                    'sequenceNumber' => '',
                    'flightSegment' => (array) $segValue,
                    'involuntaryPermissionGiven' => 'false'
                );
                $bookOriginDestinationOptionList = array(
                    'bookFlightSegmentList' => $bookFlightSegmentList
                );
                $bookOriginDestinationOptions[] = $bookOriginDestinationOptionList;
            }
        }
        return $bookOriginDestinationOptions;
    }

    public function hititBooking($pnr, $pnr_meta, $ticket_time_limit, $availability_data, $request)
    {
        $availability_data = arrayConversion($availability_data);

        $booking = $this->createBookingRecord($pnr, $pnr_meta, $ticket_time_limit, $request, $availability_data);

        $this->storeBookedSegments($booking->id, $availability_data, $pnr);

        return $booking;
    }

    private function createBookingRecord($pnr, $pnr_meta, $ticket_time_limit, $booking_request, $search_data)
    {
        $isIndexed = array_key_exists(0, $search_data);
        $segments = $isIndexed ? array_column($search_data, 'segments') : [$search_data['segments']];
        $prices   = $isIndexed ? array_column($search_data, 'fare_info') : [$search_data['fare_info']];
        $commission = $isIndexed ? $search_data[0]['commission'] : $search_data['commission'];
        $commission_type = $isIndexed ? $search_data[0]['commission_type'] : $search_data['commission_type'];
        $route_type = $isIndexed ? $search_data[0]['request']['route_type'] : $search_data['request']['route_type'];

        $first_sector = arrayConversion($segments)[0];
        $fareInfoList = $prices[0];

        $departure_data = $first_sector[0];
        $arrival_data = $this->getLastSegment($first_sector);
        $summarize_price = summarize_prices_fz($prices, $commission_type);

        $booking = new \App\Models\Booking();
        $booking->booking_id = random_id('BC');
        $booking->phone_number = $booking_request['contact_number'];

        $booking->base_fare = floatval(str_replace(',', '', $summarize_price['base_fare']));
        $booking->tax = floatval(str_replace(',', '', $summarize_price['tax']));
        $booking->discount = cleanAmount($summarize_price['discount_psf']);

        if($commission != null && $commission != ""){
            $booking->applied_discount = 1;
            $booking->applied_discount_percent = $commission;
            $booking->applied_discount_percent_type = $commission_type;
        }else{
            $booking->applied_discount = 0;
        }
        $booking->gross_fare = floatval(str_replace(',', '', $summarize_price['gross_amount']));
        $booking->total_amount = floatval(str_replace(',', '', $summarize_price['total_amount']));
        $booking->pia_fees = floatval(str_replace(',', '', $summarize_price['pia_fees']));
        $booking->fare_break_down = $summarize_price['fare_break_down'];

        $booking->booking_pnr = $pnr;
        $booking->pnr_meta = $pnr_meta;

        $ticketTime = \Carbon\Carbon::parse($ticket_time_limit);
        $now = \Carbon\Carbon::now();

        if ($ticketTime->lessThan($now) || $ticketTime->diffInMinutes($now, false) <= 10) {
            $booking->pnr_expiry = $ticketTime->copy()->addMinutes(10);
        } else {
            $booking->pnr_expiry = $ticketTime;
        }
        $booking->provider_name = 'PIA_HITIT';

        $airline = \App\Models\Airline::where('iata_code', 'PK')->first();
        $booking->airline_id = $airline ? $airline->id : null;

        $connector = \App\Models\Connector::where('type', 'PIA_HITIT')->first();
        $booking->supplier_id = optional($connector)->supplier_id;

        $booking->departure_airport = $departure_data['origin']['name'];
        $booking->departure_date_time = $departure_data['departure_datetime'];
        $booking->arrival_airport = $arrival_data['destination']['name'];
        $booking->arrival_date_time = $arrival_data['arrival_datetime'];

        $booking->fare_rules = '';
        $booking->baggage = baggage($prices);

        $booking->is_refundable = $fareInfoList['is_refundable'];
        $booking->booking_brand = $fareInfoList['rbd'];
        $booking->booking_class = $fareInfoList['booking_res_code'];
        $booking->is_multi_city = $route_type === 'MULTICITY';

        $user = \Illuminate\Support\Facades\Auth::user();
        $booking->booked_by = $user->id;

        $booking->org_id = request()->filled('org_id')
            ? request('org_id')
            : (@$user->org_id ?? null);

        $booking->save();

        return $booking;
    }


    private function getLastSegment($segment_data)
    {
        return end($segment_data);
    }
    
    private function storeBookedSegments($booking_id, $search_data, $pnr)
    {
        foreach (arrayConversion($search_data) as $sector) {
            $this->parent_id = null;

            foreach (arrayConversion($sector['segments']) as $key => $flight) {
                $booked_segment = \App\Models\BookedSegment::create([
                    'flight_pnr' => $pnr,
                    'o_flight_number' => $flight['operating_flight_number'],
                    'o_airline' => $flight['operating_code'],
                    'm_flight_number' => $flight['marketing_flight_number'],
                    'm_airline' => 'PK',
                    'seg_origin' => $flight['origin']['iata_code'],
                    'seg_destination' => $flight['destination']['iata_code'],
                    'seg_departure_datetime' => \Carbon\Carbon::parse($flight['departure_datetime'])->format('Y-m-d H:i:s'),
                    'seg_arrival_datetime' => \Carbon\Carbon::parse($flight['arrival_datetime'])->format('Y-m-d H:i:s'),
                    'departure_terminal' => $flight['origin']['terminal'],
                    'arrival_terminal' => $flight['destination']['terminal'],
                    'cabin_fullname' => 'ECONOMY',
                    'flight_duration' => (string)$flight['duration_minutes'],
                    'booking_id' => $booking_id,
                    'parent_id' => $this->parent_id,
                    'meal' => [['code' => 'M', 'description' => 'Meal']],
                ]);

                if ($key === 0) {
                    $this->parent_id = $booked_segment->id;
                }
            }
        }
    }
}
