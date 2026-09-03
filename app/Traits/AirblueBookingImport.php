<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

trait AirblueBookingImport
{

    protected $booking_detail = [];
    
    public function importAirblueBooking($request)
    {
        $booking_detail = $this->handleAirblueApi($request['pnr'], true);

        $booking = $this->createAirblueBooking($booking_detail, $booking_detail);
        $this->storeAirblueBookedSegments($booking->id, $booking_detail['booked_segments']);

        foreach ($booking_detail['passengers'] as $traveler) {
            $booking->passengers()->create($traveler);
        }
        return ['status'=> true, 'data'=> $booking];
    }

    private function createAirblueBooking(array $parsed_booking_data)
    {

        $status = $parsed_booking_data['status'];
        if(in_array($parsed_booking_data['status'],['ok', 'used']) && $parsed_booking_data['passengers'][0]['ticket_number'] != null){
            $status = 'issued';
        }else if($parsed_booking_data['status'] == 'ok' && $parsed_booking_data['passengers'][0]['ticket_number'] == null){
            $status = 'confirmed';
        }

        $response = $this->booking_detail['Body']['ReadResponse']['ReadResult']['AirReservation'];
        
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
        $booking->pnr_meta = ['pnr_info'=>$response['BookingReferenceID'], 'ItinTotalFare'=>$response['PriceInfo']['ItinTotalFare']];

        $booking->provider_name = 'AIRBLUE_API';
        $booking->booking_type = 'import';
        

        $booking->pnr_expiry = $parsed_booking_data['pnr_expiry'];
        
        $airline = \App\Models\Airline::where('iata_code', $departure_data['operatingAirlineCode'] ?? 'PA')->first();
        $booking->airline_id = $airline ? $airline->id : null;
        $booking->supplier_id = \App\Models\Connector::where('type', 'AIRBLUE_API')->first()->supplier_id;

        $booking->departure_airport = $parsed_booking_data['departure_airport'];
        $booking->departure_date_time = $parsed_booking_data['departure_date_time'];
        $booking->arrival_airport = $parsed_booking_data['arrival_airport'];
        $booking->arrival_date_time = $parsed_booking_data['arrival_date_time'];

        $booking->baggage = $parsed_booking_data['baggage'];
        $booking->is_refundable = $parsed_booking_data['is_refundable'];
        $user = \Illuminate\Support\Facades\Auth::user();
        $booking->applied_discount = 0;
        $booking->status = $status;
        $booking->booked_by = request()->filled('booked_by')
            ? request('booked_by')
            : $user->id;
        $booking->org_id = request()->filled('org_id')
            ? request('org_id')
            : (@$user->org_id ?? null);
        $booking->save();
        return $booking;
    }
    private function storeAirblueBookedSegments($booking_id, $flights)
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

    public function handleAirblueApi(string $pnr, $import_booking = false)
    {
        $booking_detail = (new \App\Services\AirBlueService\AirService())->readBooking($pnr);

        $body = $booking_detail['Body']['ReadResponse']['ReadResult'] ?? [];


        if($import_booking){
            $this->booking_detail  = $booking_detail;
        }
        if (empty($body)) {
            return Response::errorResponse(400, 'Invalid Airblue API response');
        }

        $airReservation = $body['AirReservation'] ?? [];
        $air_ticketing = air_ticketing_key_pair($airReservation['Ticketing']);
        $flights = [];
        $od_options = $airReservation['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'];
        $od_options = arrayConversion($od_options);

        if (isset($od_options)) {

            $booked_segments = [];
            foreach ($od_options as $key => $od_option) {
                $flightSegment = $od_option['FlightSegment'] ?? [];

                $departure = Carbon::parse($flightSegment['DepartureDateTime']);
                $arrival   = Carbon::parse($flightSegment['ArrivalDateTime']);

                $booked_segments[] = [
                    'parent_id' => null,
                    'flight_pnr' => $airReservation['BookingReferenceID'][0]['ID'],
                    'o_flight_number' => $flightSegment['FlightNumber'],
                    'o_airline' => $flightSegment['OperatingAirline']['Code'],
                    'm_flight_number' => $flightSegment['FlightNumber'],
                    'm_airline' => $flightSegment['MarketingAirline']['Code'],
                    'seg_origin' => $flightSegment['DepartureAirport']['LocationCode'],
                    'departure_terminal' => $flightSegment['DepartureAirport']['Terminal'],
                    'seg_destination' => $flightSegment['ArrivalAirport']['LocationCode'],
                    'arrival_terminal' => $flightSegment['ArrivalAirport']['Terminal'],
                    'seg_departure_datetime' => $flightSegment['DepartureDateTime'],
                    'seg_arrival_datetime' => $flightSegment['ArrivalDateTime'],
                    'cabin_fullname' => 'ECONOMY',
                    'flight_status_code' => $flightSegment['Status'],
                    'flight_type' => $key == 0 ? 'outbound' : 'inbound',
                    'flight_duration' => $arrival->diffInMinutes($departure),
                    'meal' => [["code" => "M", "description" => "Meal"]],
                    'operating_airline' => $flightSegment['OperatingAirline']['Code'],
                    'marketing_airline' => $flightSegment['MarketingAirline']['Code'],
                    'd_airport' => fetch_airport($flightSegment['DepartureAirport']['LocationCode']),
                    'a_airport' => fetch_airport($flightSegment['ArrivalAirport']['LocationCode']),
                    'childs' => []
                ];
            }
            $flights['booked_segments'] = $booked_segments;
        }
        $first_flight = $od_options[0]['FlightSegment'];
        $fare_break_down = fare_break_down($airReservation, count($booked_segments));
        $flights['airline'] = fetch_airline('PA');
        $flights['booking_type'] = 'import';
        $flights['type'] = 'flight';
        $flights['status'] = strtolower(reset($air_ticketing)['TicketingStatus']);
        $flights['base_fare'] = $fare_break_down['base_fare'];
        $flights['tax'] = $fare_break_down['tax'];
        $flights['gross_fare'] = $fare_break_down['gross_fare'];
        $flights['discount'] = 0;
        $flights['total_amount'] = $fare_break_down['gross_fare'];
        $flights['booking_pnr'] = $pnr;
        $flights['pnr_meta'] = '';
        $flights['provider_name'] = 'AIRBLUE_API';
        $flights['pnr_expiry'] = @\Carbon\Carbon::parse(reset($air_ticketing)['TicketTimeLimit'])->format('Y-m-d H:i:s') ?? null;
        $airline = \App\Models\Airline::where('iata_code', 'PA')->first();
        $flights['airline_id'] = $airline ? $airline->id : null;
        $flights['departure_airport'] = $first_flight['DepartureAirport']['LocationCode'];
        $flights['departure_date_time'] = $first_flight['DepartureDateTime'];
        $flights['arrival_airport'] = $first_flight['ArrivalAirport']['LocationCode'];
        $flights['arrival_date_time'] = $first_flight['ArrivalDateTime'];
        $flights['fare_break_down'] = $fare_break_down['fare_break_down'];
        $flights['baggage'] = $fare_break_down['baggage'];
        $flights['is_refundable'] = 1;
        $flights['is_multi_city'] = 0;
        $flights['supplier_id'] = @\App\Models\Connector::where('type', 'AIRBLUE_API')->first()->supplier_id ?? 1;
        $user = \Illuminate\Support\Facades\Auth::user();
        $flights['booked_by'] = $user->id;
        $flights['org_id'] = @$user->org_id ?? null;
        $flights['passengers'] = import_passengers($airReservation['TravelerInfo']['AirTraveler'], $air_ticketing);
        return $flights;
    }
}
