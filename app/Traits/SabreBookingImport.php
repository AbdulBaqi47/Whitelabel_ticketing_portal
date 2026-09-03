<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait SabreBookingImport
{
    public function importBooking($request)
    {
        $booking_detail = (new \App\Services\SabreService\API())->getBookingDetails($request['pnr']);

        $parsed_booking_data = $this->handleSabre($booking_detail);

        $booking = $this->createBooking($parsed_booking_data, $booking_detail);
        $this->storeBookedSegments($booking->id, $parsed_booking_data['booked_segments']);

        foreach ($parsed_booking_data['passengers'] as $traveler) {
            $booking->passengers()->create($traveler);
        }

        return ['status' => true, 'data' => $booking];
    }
    private function createBooking(array $parsed_booking_data, $booking_detail)
    {
        $booking = new \App\Models\Booking();
        $booking->booking_id = random_id('BC');
        $booking->phone_number = Auth::user()->phone_number;
        $booking->base_fare = $parsed_booking_data['base_fare'];
        $booking->tax = $parsed_booking_data['tax'];
        $booking->discount = $parsed_booking_data['discount'];

        $booking->gross_fare = $parsed_booking_data['gross_fare'];
        $booking->total_amount = $parsed_booking_data['total_amount'];

        $booking->fare_break_down = $parsed_booking_data['fare_break_down'];
        $booking->booking_pnr = $parsed_booking_data['booking_pnr'];
        $booking->provider_name = 'SABRE';
        $booking->booking_type = 'import';

        $booking->pnr_expiry = $parsed_booking_data['pnr_expiry'];

        $booking->airline_id = $parsed_booking_data['airline_id'];

        $booking->supplier_id = \App\Models\Connector::where('type', 'SABRE')->first()->supplier_id;

        $booking->departure_airport = $parsed_booking_data['departure_airport'];
        $booking->departure_date_time = $parsed_booking_data['departure_date_time'];
        $booking->arrival_airport = $parsed_booking_data['arrival_airport'];
        $booking->arrival_date_time = $parsed_booking_data['arrival_date_time'];

        $booking->baggage = $parsed_booking_data['baggage'];
        $booking->is_refundable = $parsed_booking_data['is_refundable'];

        $user = \Illuminate\Support\Facades\Auth::user();

        $booking->applied_discount = 0;
        $booking->booked_by = request()->filled('booked_by')
            ? request('booked_by')
            : $user->id;

        $booking->org_id = request()->filled('org_id')
            ? request('org_id')
            : (@$user->org_id ?? null);

        $booking->status = $parsed_booking_data['status'];
        $booking->created_at = \Carbon\Carbon::parse($booking_detail['creationDetails']['creationDate'] . ' ' . $booking_detail['creationDetails']['creationTime'])->format('Y-m-d H:s:i');
        $booking->updated_at = \Carbon\Carbon::parse($booking_detail['creationDetails']['creationDate'] . ' ' . $booking_detail['creationDetails']['creationTime'])->format('Y-m-d H:s:i');
        $booking->save();

        return $booking;
    }
    private function storeBookedSegments($booking_id, $flights)
    {
        foreach ($flights as $sector) {
            $this->parent_id = null;

            $booked_segment = \App\Models\BookedSegment::create([
                'booking_id'             => $booking_id,
                'flight_pnr'             => $sector['flight_pnr'],
                'o_flight_number'        => $sector['o_flight_number'],
                'o_airline'              => $sector['o_airline'],
                'm_flight_number'        => $sector['m_flight_number'],
                'm_airline'              => $sector['m_airline'],
                'seg_origin'             => $sector['seg_origin'],
                'departure_terminal'     => $sector['departure_terminal'],
                'seg_destination'        => $sector['seg_destination'],
                'arrival_terminal'       => $sector['arrival_terminal'],
                'seg_departure_datetime' => $sector['seg_departure_datetime'],
                'seg_arrival_datetime'   => $sector['seg_arrival_datetime'],
                'cabin_fullname'         => $sector['cabin_fullname'],
                'flight_status_code'     => $sector['flight_status_code'],
                'flight_type'            => $sector['flight_type'],
                'flight_duration'        => $sector['flight_duration'],
                'meal'                   => $sector['meal'],
            ]);

            if (count($sector['childs']) > 0) {

                foreach ($sector['childs'] as $key => $segment) {
                    \App\Models\BookedSegment::create([
                        'booking_id'             => $booking_id,
                        'parent_id'              => $booked_segment->id,
                        'flight_pnr'             => $segment['flight_pnr'],
                        'o_flight_number'        => $segment['o_flight_number'],
                        'o_airline'              => $segment['o_airline'],
                        'm_flight_number'        => $segment['m_flight_number'],
                        'm_airline'              => $segment['m_airline'],
                        'seg_origin'             => $segment['seg_origin'],
                        'departure_terminal'     => $segment['departure_terminal'],
                        'seg_destination'        => $segment['seg_destination'],
                        'arrival_terminal'       => $segment['arrival_terminal'],
                        'seg_departure_datetime' => $segment['seg_departure_datetime'],
                        'seg_arrival_datetime'   => $segment['seg_arrival_datetime'],
                        'cabin_fullname'         => $segment['cabin_fullname'],
                        'flight_status_code'     => $segment['flight_status_code'],
                        'flight_type'            => $segment['flight_type'],
                        'flight_duration'        => $segment['flight_duration'],
                        'meal'                   => $segment['meal'],
                    ]);
                }
            }
        }
    }

    public function handleSabre($booking_details)
    {
        $od_options = flights_combination($booking_details['flights'], $booking_details['journeys']);

        $first_flight = $od_options[0]['segments'][0];
        $booked_segments = [];
        foreach ($od_options as $key => $od_option) {
            $parent_segment = [];
            $child_segments = [];
            foreach ($od_option['segments'] as $seg_key => $segment) {
                $parsed_data = [
                    'parent_id'              => null,
                    'flight_pnr'             => $segment['confirmationId'],
                    'o_flight_number'        => $segment['operatingFlightNumber'],
                    'o_airline'              => $segment['operatingAirlineCode'],
                    'm_flight_number'        => $segment['operatingFlightNumber'],
                    'm_airline'              => $segment['operatingAirlineCode'],
                    'seg_origin'             => $segment['fromAirportCode'],
                    'departure_terminal'     => @$segment['departureTerminalName'] ?? null,
                    'seg_destination'        => $segment['toAirportCode'],
                    'arrival_terminal'       => @$segment['arrivalTerminalName'] ?? null,
                    'seg_departure_datetime' => $segment['departureDate'] . 'T' . $segment['departureTime'],
                    'seg_arrival_datetime'   => $segment['arrivalDate'] . 'T' . $segment['arrivalTime'],
                    'cabin_fullname'         => $segment['cabinTypeName'],
                    'flight_status_code'     => $segment['flightStatusName'],
                    'flight_type'            => $key == 0 ? 'outbound' : 'inbound',
                    'flight_duration'        => $segment['durationInMinutes'],
                    'meal'                   => @$segment['meals'],
                    'operating_airline'      => $segment['operatingAirlineCode'],
                    'marketing_airline'      => $segment['operatingAirlineCode'],
                    'd_airport'              => fetch_airport($segment['fromAirportCode']),
                    'a_airport'              => fetch_airport($segment['toAirportCode']),
                ];
                if ($seg_key == 0) {
                    $parent_segment = $parsed_data;
                } else {
                    $child_segments[] = $parsed_data;
                }
            }
            $parent_segment['childs'] = $child_segments;
            $booked_segments[] = $parent_segment;
        }
        $flights['booked_segments'] = $booked_segments;
        $fare_break_down = import_pnr_price($booking_details['fares'], $booking_details['travelers']);
        $flights['airline'] = fetch_airline((string)$first_flight['operatingAirlineCode']);
        $flights['booking_type'] = 'import';
        $flights['type'] = 'flight';
        $flights['status'] = ($booking_details['isTicketed'] && $booking_details['flightTickets'][0]['ticketStatusName'] == 'Issued') ? 'issued' : (array_key_exists('flights', $booking_details) ? 'confirmed' : 'cancelled');
        $flights['base_fare'] = cleanAmount($fare_break_down['base_fare']);
        $flights['tax'] = cleanAmount($fare_break_down['tax']);
        $flights['gross_fare'] = cleanAmount($fare_break_down['gross_amount']);
        $flights['discount'] = 0;
        $flights['total_amount'] = cleanAmount($fare_break_down['total_amount']);
        $flights['booking_pnr'] = $booking_details['bookingId'];
        $flights['provider_name'] = 'SABRE';
        $flights['pnr_expiry'] = pnrExpiry($booking_details);
        $airline = \App\Models\Airline::where('iata_code', $first_flight['operatingAirlineCode'])->first();
        $flights['airline_id'] = $airline ? $airline->id : null;
        $flights['departure_airport'] = $first_flight['fromAirportCode'];
        $flights['departure_date_time'] = $first_flight['departureDate'] . 'T' . $first_flight['departureTime'];
        $flights['arrival_airport'] = $first_flight['toAirportCode'];
        $flights['arrival_date_time'] = $first_flight['arrivalDate'] . 'T' . $first_flight['arrivalTime'];
        $flights['fare_break_down'] = $fare_break_down['fare_break_down'];
        $flights['passengers'] = sabre_import_passengers($booking_details['travelers'], @$booking_details['flightTickets']);
        $flights['baggage'] = sabre_baggage($flights['passengers'], $booking_details['fareOffers']);
        $flights['is_refundable'] = 1;
        return $flights;
    }
}
