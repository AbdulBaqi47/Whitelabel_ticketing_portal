<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait FlyDubaiBooking
{
    public function flydubaiBooking($response, $booking_request, $search_data)
    {
        $booking = $this->createBookingRecord($response, $booking_request, $search_data);
        $this->storeBookedSegments($booking->id, $search_data, $response['Cabin']);
        return $booking;
    }

    private function createBookingRecord($response, $booking_request, $search_data)
    {
        $isIndexed = array_key_exists(0, $search_data);
        $segments = $isIndexed ? array_column($search_data, 'segments') : [$search_data['segments']];
        $prices   = $isIndexed ? array_column($search_data, 'price') : [$search_data['price']];
        $commission = $isIndexed ? $search_data[0]['commission'] : $search_data['commission'];
        $commission_type = $isIndexed ? $search_data[0]['commission_type'] : $search_data['commission_type'];

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
        $booking->fare_break_down = $summarize_price['fare_break_down'];

        $booking->booking_pnr = $response['ConfirmationNumber'];
        $booking->reservation_balance = $response['ReservationBalance'];
        $booking->pnr_expiry = \Carbon\Carbon::parse($response['ReservationFulfillmentRequiredByODT'])->format('Y-m-d H:i:s');
        $booking->provider_name = 'FLYDUBAI_API';

        $airline = \App\Models\Airline::where('iata_code', 'FZ')->first();
        $booking->airline_id = optional($airline)->id;

        $connector = \App\Models\Connector::where('type', 'FLYDUBAI_API')->first();
        $booking->supplier_id = optional($connector)->supplier_id;

        $booking->departure_airport = $departure_data['origin']['name'];
        $booking->departure_date_time = $departure_data['departure_datetime'];
        $booking->arrival_airport = $arrival_data['destination']['name'];
        $booking->arrival_date_time = $arrival_data['arrival_datetime'];

        $booking->fare_rules = '';
        $booking->baggage = baggage($prices);

        $booking->is_refundable = $fareInfoList['cancellation'] === 'yes' ? 1 : 0;
        $booking->booking_brand = $fareInfoList['rbd'];
        $booking->is_multi_city = false;

        $user = Auth::user();
        $booking->booked_by = $user->id;
        $booking->org_id = $user->org_id ?? null;

        $booking->save();

        return $booking;
    }

    private function getLastSegment($segment_data)
    {
        return end($segment_data);
    }

    private function storeBookedSegments($booking_id, $search_data, $cabin)
    {

        $meal = collect(arrayConversion($search_data))->pluck('price.has_meal')->toArray();

        foreach (arrayConversion($search_data) as $parent_key =>  $sector) {
            $this->parent_id = null;

            foreach (arrayConversion($sector['segments']) as $key => $flight) {
                $booked_segment = \App\Models\BookedSegment::create([
                    'o_flight_number' => $flight['operating_flight_number'],
                    'o_airline' => $flight['operating_code'],
                    'm_flight_number' => $flight['marketing_flight_number'],
                    'm_airline' => 'FZ',
                    'seg_origin' => $flight['origin']['iata_code'],
                    'seg_destination' => $flight['destination']['iata_code'],
                    'seg_departure_datetime' => \Carbon\Carbon::parse($flight['departure_datetime'])->format('Y-m-d H:i:s'),
                    'seg_arrival_datetime' => \Carbon\Carbon::parse($flight['arrival_datetime'])->format('Y-m-d H:i:s'),
                    'departure_terminal' => $flight['origin']['terminal'],
                    'arrival_terminal' => $flight['destination']['terminal'],
                    'cabin_fullname' => $cabin,
                    'flight_duration' => (string)$flight['duration_minutes'],
                    'booking_id' => $booking_id,
                    'parent_id' => $this->parent_id,
                    'meal' =>  $meal[$parent_key] ? [['code' => 'M', 'description' => 'Meal']] : null,
                ]);

                if ($key === 0) {
                    $this->parent_id = $booked_segment->id;
                }
            }
        }
    }
}