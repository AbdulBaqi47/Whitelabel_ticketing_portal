<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait FlyDubaiBookingImport
{
    public function importFlyDubaiBooking($request)
    {
        $flydubaiService = new \App\Services\FlyDubaiService\FlydubaiService();
        $booking_detail = $flydubaiService->retreivePnr($request['pnr']);

        if(isset($booking_detail['Exceptions']) && !empty($booking_detail['Exceptions'])){
            $exception = $booking_detail['Exceptions'][0];
            if($exception['ExceptionCode'] != 0){
                return ['status'=>false, 'message'=>$exception['ExceptionDescription']];
            }
        }

        $is_ticketed_pnr = $this->checkTicketingStatus($booking_detail);

        $flights = $this->extractFlightsFlyDubai($booking_detail);
        $travelers = $this->extractTravelersFlyDubai($booking_detail);
        $price = $this->extractPriceFlyDubai($booking_detail, $travelers);
        $baggage = $this->extractBaggageFlyDubai($booking_detail);

        $booking = $this->createBookingFlyDubai($flights, $price, $baggage, $is_ticketed_pnr, $booking_detail);
        $this->storeBookedSegmentsFlyDubai($booking->id, $flights);
        $this->travelersFlyDubai($booking, $travelers, $is_ticketed_pnr, $booking_detail);

        return ['status'=> true, 'data'=>$booking];
    }

    private function checkTicketingStatus($booking_detail)
    {
        if (isset($booking_detail['Airlines'][0]['LogicalFlight'][0]['PhysicalFlights'][0]['Customers'][0]['AirlinePersons'])) {
            $airlinePersons = arrayConversion($booking_detail['Airlines'][0]['LogicalFlight'][0]['PhysicalFlights'][0]['Customers'][0]['AirlinePersons']);
            foreach ($airlinePersons as $person) {
                if (!empty($person['TicketNumber'])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function extractFlightsFlyDubai($booking_detail)
    {
        $flights = [];
        
        if (isset($booking_detail['Airlines'][0]['LogicalFlight'])) {
            $logicalFlights = arrayConversion($booking_detail['Airlines'][0]['LogicalFlight']);
            foreach ($logicalFlights as $logicalFlight) {
                $segments = [];
                $physicalFlights = arrayConversion($logicalFlight['PhysicalFlights']);
                foreach ($physicalFlights as $flight) {
                    $segments[] = [
                        'confirmationId' => $booking_detail['ConfirmationNumber'] ?? null,
                        'flightNumber' => $flight['FlightNumber'] ?? null,
                        'operatingAirlineCode' => $flight['OperatingCarrier'] ?? 'FZ',
                        'fromAirportCode' => $flight['Origin'] ?? null,
                        'toAirportCode' => $flight['Destination'] ?? null,
                        'departureDate' => substr($flight['DepartureTime'], 0, 10),
                        'departureTime' => substr($flight['DepartureTime'], 11),
                        'arrivalDate' => substr($flight['Arrivaltime'], 0, 10),
                        'arrivalTime' => substr($flight['Arrivaltime'], 11),
                        'departureTerminalName' => $flight['OriginDefaultTerminal'] ?? null,
                        'arrivalTerminalName' => $flight['DestinationDefaultTerminal'] ?? null,
                        'cabinTypeName' => $logicalFlight['Cabin'] ?? 'Economy',
                        'cabinTypeCode' => $flight['Customers'][0]['AirlinePersons'][0]['OperatingRBD'] ?? 'B',
                        'flightStatusCode' => $flight['FlightStatus'] ?? null,
                        'durationInMinutes' => $flight['FlightDuration'] ?? null,
                        'meals' => null,
                        'aircraft' => $flight['AirCraftType'] ?? null,
                        'aircraftDescription' => $flight['AirCraftDescription'] ?? null,
                    ];
                }
                $flights[] = [
                    'sector' => [
                        $logicalFlight['Origin'] ?? '',
                        $logicalFlight['Destination'] ?? ''
                    ],
                    'segments' => $segments
                ];
            }
        }
        
        return $flights;
    }

    private function extractTravelersFlyDubai($booking_detail)
    {
        $travelers = [];
        
        if (isset($booking_detail['Airlines'][0]['LogicalFlight'][0]['PhysicalFlights'][0]['Customers'][0]['AirlinePersons'])) {
            $airlinePersons = arrayConversion($booking_detail['Airlines'][0]['LogicalFlight'][0]['PhysicalFlights'][0]['Customers'][0]['AirlinePersons']);
            foreach ($airlinePersons as $person) {
                $travelers[] = [
                    'first_name' => $person['FirstName'] ?? '',
                    'last_name' => $person['LastName'] ?? '',
                    'middle_name' => $person['MiddleName'] ?? '',
                    'type' => $this->mapPTCIDToType($person['PTCID'] ?? 1),
                    'title' => $person['Title'] ?? '',
                    'date_of_birth' => $person['DOB'] ?? '',
                    'd_type' => !empty($person['Passport']) ? 'P' : 'I',
                    'd_number' => $person['Passport'] ?? '',
                    'd_expiry' => $person['PassportExpiryDate'] ?? '',
                    'ticket_number' => $person['TicketNumber'] ?? '',
                    'age' => $person['Age'] ?? null,
                    'gender' => $person['Gender'] ?? '',
                    'nationality' => $person['Nationality'] ?? '',
                    'record_number' => $person['RecordNumber'] ?? 1,
                ];
            }
        }
        
        return $travelers;
    }

    private function extractPriceFlyDubai($booking_detail, $travelers)
    {
        $fareData = [];
        $currency = $booking_detail['ReservationCurrency'] ?? 'PKR';

        if (isset($booking_detail['Airlines'][0]['LogicalFlight'][0]['PhysicalFlights'][0]['Customers'][0]['AirlinePersons'])) {
            $airlinePersons = arrayConversion($booking_detail['Airlines'][0]['LogicalFlight'][0]['PhysicalFlights'][0]['Customers'][0]['AirlinePersons']);
            
            foreach ($airlinePersons as $person) {
                $base_fare = 0;
                $tax = 0;
                $passengerType = $this->mapPTCIDToType($person['PTCID'] ?? 1);
                
                if (isset($person['Charges'])) {
                    $charges = arrayConversion($person['Charges']);
                    foreach ($charges as $charge) {
                        if ($charge['CodeType'] == 'AIR') {
                            $base_fare += abs($charge['Amount']);
                        } elseif (in_array($charge['CodeType'], ['TAX', 'TFEE'])) {
                            $tax += abs($charge['Amount']);
                        }
                    }
                }
                
                $fareData[] = [
                    'price' => [
                        'base_fare' => $base_fare,
                        'tax' => $tax,
                        'gross_amount' => $base_fare + $tax,
                        'total_amount' => $base_fare + $tax,
                        'currency' => $currency,
                        'discount_psf' => 0,
                        'fare_break_down' => [
                            $passengerType => [
                                'quantity' => 1,
                                'base_fare' => $base_fare,
                                'tax' => $tax,
                                'gross_amount' => $base_fare + $tax,
                                'total_amount' => $base_fare + $tax,
                                'currency' => $currency,
                                'discount_psf' => 0
                            ]
                        ]
                    ]
                ];
            }
        }
        
        return summarize_prices_fz($fareData);
    }

    private function extractBaggageFlyDubai($booking_detail)
    {
        $baggage = [];
        
        $passengerTypes = ['ADT'];
        if (isset($booking_detail['Airlines'][0]['LogicalFlight'][0]['PhysicalFlights'][0]['Customers'][0]['AirlinePersons'])) {
            $passengerTypes = [];
            $airlinePersons = arrayConversion($booking_detail['Airlines'][0]['LogicalFlight'][0]['PhysicalFlights'][0]['Customers'][0]['AirlinePersons']);
            foreach ($airlinePersons as $person) {
                $type = $this->mapPTCIDToType($person['PTCID'] ?? 1);
                if (!in_array($type, $passengerTypes)) {
                    $passengerTypes[] = $type;
                }
            }
        }

        foreach ($passengerTypes as $type) {
            $baggage[$type] = [
                [
                    'unit' => 'kg',
                    'weight' => 20,
                    'provision' => 'Checked baggage allowance',
                    'provisionType' => 'A',
                ],
                [
                    'unit' => 'kg', 
                    'weight' => 7,
                    'provision' => 'Carry-on baggage allowance',
                    'pieceCount' => 1,
                    'provisionType' => 'B',
                ]
            ];
        }
        
        return $baggage;
    }

    private function createBookingFlyDubai($flights, $price, $baggage, $is_ticketed_pnr, $booking_detail)
    {
        $departure_data = $flights[0]['segments'][0];
        $arrival_data = $flights[0]['segments'][count($flights[0]['segments'])-1];

        $booking = new \App\Models\Booking();
        $booking->booking_id = random_id('BC');
        
        $phone_number = Auth::user()->phone_number;
        if(isset($booking_detail['ContactInfos'])) {
            $contactInfos = arrayConversion($booking_detail['ContactInfos']);
            foreach($contactInfos as $contact) {
                if($contact['ContactType'] == 2) {
                    $phone_number = $contact['ContactField'];
                    break;
                }
            }
        }
        $booking->phone_number = $phone_number;
        
        $booking->base_fare = cleanAmount($price['base_fare']);
        $booking->tax = cleanAmount($price['tax']);
        $booking->discount = cleanAmount($price['discount_psf']);
        $booking->gross_fare = cleanAmount($price['gross_amount']);
        $booking->total_amount = cleanAmount($price['total_amount']);
        $booking->fare_break_down = $price['fare_break_down'];
        $booking->booking_pnr = $booking_detail['ConfirmationNumber'] ?? null;
        $booking->provider_name = 'FLYDUBAI_API';
        $booking->booking_type = 'import';
        
        if(isset($booking_detail['ReservationFulfillmentRequiredByODT'])) {
            $booking->pnr_expiry = \Carbon\Carbon::parse($booking_detail['ReservationFulfillmentRequiredByODT'])->format('Y-m-d H:i:s');
        }
        
        $airline = \App\Models\Airline::where('iata_code', $departure_data['operatingAirlineCode'])->first();
        $booking->airline_id = $airline ? $airline->id : null;
        
        $connector = \App\Models\Connector::where('type', 'FLYDUBAI_API')->first();
        $booking->supplier_id = $connector ? $connector->supplier_id : null;
        
        $booking->departure_airport = \App\Models\Airport::where('iata_code', $departure_data['fromAirportCode'])->first()?->name;
        $booking->departure_date_time = $departure_data['departureDate'] . ' ' . $departure_data['departureTime'];
        $booking->arrival_airport = \App\Models\Airport::where('iata_code', $arrival_data['toAirportCode'])->first()?->name;
        $booking->arrival_date_time = $arrival_data['arrivalDate'] . ' ' . $arrival_data['arrivalTime'];
        
        $booking->fare_rules = null;
        $booking->baggage = $baggage;
        $booking->is_refundable = 0;
        $booking->applied_discount = 0;
        
        $user = \Illuminate\Support\Facades\Auth::user();
        $booking->booked_by = request()->filled('booked_by') ? request('booked_by') : $user->id;
        $booking->org_id = request()->filled('org_id') ? request('org_id') : (@$user->org_id ?? null);
        $booking->status = $is_ticketed_pnr ? 'issued' : 'confirmed';
        
        $booking->save();
        
        return $booking;
    }

    private function storeBookedSegmentsFlyDubai($booking_id, $flights)
    {
        foreach ($flights as $sector) {
            $this->parent_id = null;
            foreach ($sector['segments'] as $key => $segment) {
                $booked_segment = \App\Models\BookedSegment::create([
                    'flight_pnr' => $segment['confirmationId'],
                    'o_flight_number' => $segment['flightNumber'],
                    'o_airline' => $segment['operatingAirlineCode'],
                    'm_flight_number' => $segment['flightNumber'],
                    'm_airline' => $segment['operatingAirlineCode'],
                    'seg_origin' => $segment['fromAirportCode'],
                    'seg_destination' => $segment['toAirportCode'],
                    'seg_departure_datetime' => $segment['departureDate'] . ' ' . $segment['departureTime'],
                    'seg_arrival_datetime' => $segment['arrivalDate'] . ' ' . $segment['arrivalTime'],
                    'departure_terminal' => @$segment['departureTerminalName'] ?? null,
                    'arrival_terminal' => @$segment['arrivalTerminalName'] ?? null,
                    'cabin_fullname' => $segment['cabinTypeName'] . '-' . $segment['cabinTypeCode'],
                    'flight_status_code' => $segment['flightStatusCode'],
                    'flight_duration' => (string)$segment['durationInMinutes'],
                    'booking_id' => $booking_id,
                    'parent_id' => $this->parent_id,
                    'meal' => @$segment['meals'],
                    'aircraft_type' => $segment['aircraft'],
                ]);
                
                if($key == 0){
                    $this->parent_id = $booked_segment->id;
                }
            }
        }
    }

    private function travelersFlyDubai($booking, $travelers, $is_ticketed_pnr, $booking_detail)
    {
        foreach ($travelers as $key => $traveler) {
            $passenger_data = [
                'title' => $traveler['title'],
                'first_name' => $traveler['first_name'],
                'last_name' => $traveler['last_name'],
                'type' => $traveler['type'],
                'd_type' => $traveler['d_type'],
                'd_number' => $traveler['d_number'],
                'd_expiry' => $traveler['d_expiry'],
                'date_of_birth' => $traveler['date_of_birth'],
            ];
            
            if($is_ticketed_pnr && !empty($traveler['ticket_number'])){
                $passenger_data['ticket_number'] = $traveler['ticket_number'];
            }
            
            $booking->passengers()->create($passenger_data);
        }
    }

    private function mapPTCIDToType($ptcid)
    {
        $passengerTypes = fb_passenger_type();
        $type = $passengerTypes[$ptcid] ?? 'ADLT';

        $mapping = [
            'ADLT' => 'ADT',
            'CHLD' => 'CNN', 
            'INFT' => 'INF'
        ];
        
        return $mapping[$type] ?? 'ADT';
    }
}