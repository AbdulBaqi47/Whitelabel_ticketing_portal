<?php

namespace App\Traits;

trait AirblueBooking
{

    private $pnr = "";
    public function airblueBooking($response, $booking_request, $search_data)
    {
        $booking = $this->createBookingRecord($response, $booking_request, $search_data);

        $this->storeBookedSegments($booking->id, $search_data);

        return $booking;
    }

    private function createBookingRecord($response, $booking_request, $search_data)
    {
        $isIndexed = array_key_exists(0, $search_data);
        $first_sector = arrayConversion($search_data)[0];
        $response = $response['Body']['AirBookResponse']['AirBookResult']['AirReservation'];
        $route_type = $first_sector['request']['route_type'];
        $fareInfoList = $first_sector['fare_info_list'];

        $prices   = $isIndexed ? array_column($search_data, 'fare_info_list') : [$search_data['fare_info_list']];

        $commission = $isIndexed ? $search_data[0]['commission'] : $search_data['commission'];
        $commission_type = $isIndexed ? $search_data[0]['commission_type'] : $search_data['commission_type'];

        $departure_data = $first_sector['segment_data'][0];
        $arrival_data = $this->getLastSegment($first_sector['segment_data']);

        $booking = new \App\Models\Booking();
        $booking->booking_id = random_id('BC');
        $booking->phone_number = $booking_request['contact_number'];

        $summarize_price = summarize_prices(arrayConversion($search_data));
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

        $booking->fare_break_down = $summarize_price['fare_break_down'];
        $booking->booking_pnr = $response['BookingReferenceID'][0]['ID'];
        $this->pnr = $response['BookingReferenceID'][0]['ID'];
        $booking->pnr_meta = ['pnr_info'=>$response['BookingReferenceID'], 'ItinTotalFare'=>$response['PriceInfo']['ItinTotalFare'], 'AirItinerary' => $response['AirItinerary']];

        $booking->pnr_expiry = \Carbon\Carbon::parse(arrayConversion($response['Ticketing'])[0]['TicketTimeLimit'])->format('Y-m-d H:i:s');
        
        $booking->provider_name = 'AIRBLUE_API';

        $airline = \App\Models\Airline::where('iata_code', 'PA')->first();
        $booking->airline_id = $airline ? $airline->id : null;

        $booking->supplier_id = @\App\Models\Connector::where('type', 'AIRBLUE_API')->first()->supplier_id ?? 1;

        $booking->departure_airport = $departure_data['origin']['name'];
        $booking->departure_date_time = $departure_data['departure_datetime'];
        $booking->arrival_airport = $arrival_data['destination']['name'];
        $booking->arrival_date_time = $arrival_data['arrival_datetime'];
        $booking->fare_rules = '';

        $booking->baggage = baggage($prices);
        $booking->booking_class = $fareInfoList['booking_res_code'];
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

    private function storeBookedSegments($booking_id, $search_data)
    {
        $terminals = terminals($this->pnr);
        $i = 0;
        foreach (arrayConversion($search_data) as $sector) {
            $this->parent_id = null;
            foreach ($sector['segment_data'] as $key => $flight) {
                $booked_segment = \App\Models\BookedSegment::create([
                    'flight_pnr' => $this->pnr,
                    'o_flight_number' => $flight['operating_flight_number'],
                    'o_airline' => $flight['operating_code'],
                    'm_flight_number' => $flight['marketing_flight_number'],
                    'm_airline' => 'PA',
                    'seg_origin' => $flight['origin']['iata_code'],
                    'seg_destination' => $flight['destination']['iata_code'],
                    'seg_departure_datetime' => \Carbon\Carbon::parse($flight['departure_datetime'])->format('Y-m-d H:i:s'),
                    'seg_arrival_datetime' => \Carbon\Carbon::parse($flight['arrival_datetime'])->format('Y-m-d H:i:s'),
                    'departure_terminal' => $terminals[$i]['departure_terminal'],
                    'arrival_terminal' => $terminals[$i]['arrival_terminal'],
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
