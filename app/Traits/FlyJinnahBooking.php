<?php

namespace App\Traits;

trait FlyJinnahBooking
{

    private $pnr = "";
    public function create_booking($response, $booking_request, $confirmation)
    {
        $booking = $this->create_booking_record($response, $booking_request, $confirmation);

        $sectors = $confirmation[0]['legs'];
        $this->store_booked_segments($booking->id, $sectors);

        return $booking;
    }

    private function create_booking_record($response, $request, $confirmation)
    {
        $first_sector = $confirmation[0];
        $ongoing_flight = $confirmation[0]['legs'][0];
        $response = $response['Body']['OTA_AirBookRS']['AirReservation'];
        $route_type = $first_sector['request']['route_type'];
        $fareInfoList = $first_sector['price'];


        // $commission = $isIndexed ? $confirmation[0]['commission'] : $confirmation['commission'];
        // $commission_type = $isIndexed ? $confirmation[0]['commission_type'] : $confirmation['commission_type'];

        $departure_data = $ongoing_flight['segments'][0];

        $arrival_data = $this->getLastSegment($ongoing_flight['segments']);

        $booking = new \App\Models\Booking();
        $booking->booking_id = random_id('BC');
        $booking->phone_number = $request['contact_number'];

        $prices   = array_column($confirmation, 'price');
        $summarize_price = summarize_prices($prices);
        $booking->base_fare = floatval(str_replace(',', '', $summarize_price['base_fare']));
        $booking->tax = floatval(str_replace(',', '', $summarize_price['tax']));
        $booking->discount = cleanAmount($summarize_price['discount_psf']);

        // if($commission != null && $commission != ""){
        //     $booking->applied_discount = 1;
        //     $booking->applied_discount_percent = $commission;
        //     $booking->applied_discount_percent_type = $commission_type;
        // }else{
        //     $booking->applied_discount = 0;
        // }
        $booking->applied_discount = 0;
        $booking->gross_fare = floatval(str_replace(',', '', $summarize_price['gross_amount']));
        $booking->total_amount = floatval(str_replace(',', '', $summarize_price['total_amount']));

        $booking->fare_break_down = $summarize_price['fare_break_down'];
        $booking->booking_pnr = $response['BookingReferenceID']['ID'];
        $this->pnr = $response['BookingReferenceID']['ID'];
        $booking->extra_selection = ""; /// Remaining this for Overall Selection

        $booking->pnr_expiry = \Carbon\Carbon::parse(arrayConversion($response['Ticketing'])[0]['TicketTimeLimit'])->format('Y-m-d H:i:s');
        
        $booking->provider_name = 'ONEAPI';

        $airline = \App\Models\Airline::where('iata_code', $departure_data['marketing_code'])->first();
        $booking->airline_id = $airline ? $airline->id : null;

        $booking->supplier_id = @\App\Models\Connector::where('type', 'ONEAPI')->first()->supplier_id ?? 1;

        $booking->departure_airport = $departure_data['origin']['name'];
        $booking->departure_date_time = $departure_data['departure_datetime'];
        $booking->arrival_airport = $arrival_data['destination']['name'];
        $booking->arrival_date_time = $arrival_data['arrival_datetime'];
        $booking->fare_rules = '';

        $booking->is_refundable = $fareInfoList['is_refundable'];
        $booking->booking_brand = $fareInfoList['rbd'];
        $booking->is_multi_city = $route_type === 'MULTICITY';

        $user = \Illuminate\Support\Facades\Auth::user();
        $booking->booked_by = $user->id;
        $booking->org_id = @$user->org_id ?? null;

        $booking->save();

        return $booking;
    }

    private function getLastSegment($segment_data)
    {
        if (count($segment_data) === 0) {
            return null;
        }
        return $segment_data[count($segment_data) - 1];
    }

    private function store_booked_segments($booking_id, $sectors)
    {
        $i = 0;
        foreach ($sectors as $sector) {
            $this->parent_id = null;
            foreach ($sector['segments'] as $key => $flight) {
                $booked_segment = \App\Models\BookedSegment::create([
                    'flight_pnr' => $this->pnr,
                    'o_flight_number' => $flight['flight_number'],
                    'o_airline' => $flight['operating_airline']['iata_code'],
                    'm_flight_number' => $flight['marketing_flight_number'],
                    'm_airline' => $flight['marketing_code'],
                    'seg_origin' => $flight['origin']['iata_code'],
                    'seg_destination' => $flight['destination']['iata_code'],
                    'seg_departure_datetime' => \Carbon\Carbon::parse($flight['departure_datetime'])->format('Y-m-d H:i:s'),
                    'seg_arrival_datetime' => \Carbon\Carbon::parse($flight['arrival_datetime'])->format('Y-m-d H:i:s'),
                    'departure_terminal' => $flight['origin']['terminal'],
                    'arrival_terminal' => $flight['destination']['terminal'],
                    'cabin_fullname' => 'ECONOMY',
                    'flight_duration' =>(string)$flight['duration_minutes'],
                    'booking_id' => $booking_id,
                    'parent_id' => $this->parent_id,
                    'meal' => [["code"=> "M", "description"=> "Meal"]],
                ]);
                if ($key == 0) {
                    $this->parent_id = $booked_segment->id;
                }
                $i++;
            }
        }
    }
}
