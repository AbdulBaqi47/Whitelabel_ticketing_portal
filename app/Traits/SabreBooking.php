<?php

namespace App\Traits;

trait SabreBooking {

    private $ndcBookingId = null;

    public function segmentData($data, $request)
    {
        $itinSegments = [];
        $number_in_party = getPassengerCount($request['passengers']);
        $i = 0;
        foreach ($data['segment_data'] as $value) {
            foreach ($value['segments'] as $sub_value) {
                $itinSegments[] = [
                    "DepartureDateTime" => $sub_value["DepartureDateTimeXML"],
                    "ArrivalDateTime" => $sub_value["ArrivalDateTimeXML"],
                    "FlightNumber" => (string) $sub_value['marketing_flight_number'],
                    "NumberInParty" => (string) $number_in_party,
                    "ResBookDesigCode" => $data['fareInfoList'][0]['booking_res_code'][$i],
                    "Status" => 'NN',
                    "DestinationLocation" => [
                        "LocationCode" => $sub_value['destination']['iata_code']
                    ],
                    "MarketingAirline" => [
                        "Code" => $sub_value['marketing_code'],
                        "FlightNumber" => (string) $sub_value['marketing_flight_number'],
                    ],
                    "OriginLocation" => [
                        "LocationCode" => $sub_value['origin']['iata_code']
                    ]
                ];
                $i++;
            }
        }
        return $itinSegments;
    }
    public function revalidateSegmentData($data)
    {
        $originDestinationInfo = [];
    
        $i = 0;
        foreach ($data['segment_data'] as $key => $segmentGroup) {
            $segments = $segmentGroup['segments'];
    
            $firstSegment = $segments[0];
            $lastSegment = end($segments);
    
            $tpaExtensions = [];
    
            if (!empty($data['fareInfoList'][1]['brand']['code'])) {
                $tpaExtensions['BrandFilters'] = [
                    "Brand" => [
                        [
                            "Code" => $data['fareInfoList'][1]['brand']['code'],
                            "PreferLevel" => "Preferred"
                        ]
                    ]
                ];
            }

            if (!empty($data['fareInfoList'][1]['basefarecode'])) {

                $code = $data['fareInfoList'][1]['basefarecode'][$key];

                $tpaExtensions['FareBasis'][] =
                    [
                        "Code" => $code,
                        "PreferLevel" => "Preferred"
                    ];

            }

            if(($data['airline']['iata_code'] == 'QR' && $data['fareInfoList'][1]['brand']['code'] == 'ECLASSIC') || ($data['airline']['iata_code'] == 'KU' && empty($data['fareInfoList'][1]['brand']['code']))){
                unset($tpaExtensions['FareBasis']);
            }    

            if($data['airline']['iata_code'] == 'HY'){
                unset($tpaExtensions['FareBasis']);
            }
    

            $flightExtensions = [];

            foreach ($segments as $segment) {

                $flightExtensions[] = [
                    "Airline" => [
                        "Marketing" => $segment['marketing_code']
                    ],
                    "ArrivalDateTime" => $segment['ArrivalDateTimeXML'],
                    "ClassOfService" => $data['fareInfoList'][0]['booking_res_code'][$i] ?? '',
                    "DepartureDateTime" => $segment['DepartureDateTimeXML'],
                    "DestinationLocation" => [
                        "LocationCode" => $segment['destination']['iata_code']
                    ],
                    "Number" => (int) $segment['marketing_flight_number'],
                    "OriginLocation" => [
                        "LocationCode" => $segment['origin']['iata_code']
                    ],
                    "Type" => "A"
                ];
                $i++;
            }

            $tpaExtensions['Flight'] = $flightExtensions;

            $originDestination = [
                "RPH" => (string) $key,
                "DepartureDateTime" => $firstSegment['DepartureDateTimeXML'],
                "DestinationLocation" => [
                    "LocationCode" => $lastSegment['destination']['iata_code'],
                    "LocationType" => "A",
                ],
                "OriginLocation" => [
                    "LocationCode" => $firstSegment['origin']['iata_code'],
                    "LocationType" => "A",
                ],
                "TPA_Extensions" => $tpaExtensions
            ];
    
            $originDestinationInfo[] = $originDestination;
        }
        
        return $originDestinationInfo;
    }
    
    public function sabreBooking($pnr, $request, $availability_data, $booking_detail, $ndcBookingId = null)
    {
        $this->ndcBookingId = $ndcBookingId;
        $booking = $this->createBookingRecord($pnr, $request, $availability_data, $booking_detail);

        $this->storeBookedSegments($booking->id, $booking_detail['flights'], $availability_data);

        return $booking;
    }

    private function createBookingRecord($pnr, $request, $availability_data, $booking_detail)
    {
        $route_type = $availability_data['request']['route_type'];
        $fareInfoList = $availability_data['fareInfoList'][0];

        $departure_data = $availability_data['segment_data'][0]['segments'][0];
        $arrival_data = $this->getLastSegment($availability_data['segment_data'][0]);

        $r_departure_data = $this->getFirstSegment(@$availability_data['segment_data'][1]);
        $r_arrival_data = $this->getLastSegment(@$availability_data['segment_data'][1]);

        $booking = new \App\Models\Booking();
        $booking->booking_id = random_id('BC');
        $booking->ndc_order_id  = $this->ndcBookingId;
        $booking->phone_number = $request['contact_number'];
        $booking->base_fare = cleanAmount($fareInfoList['price']['base_fare']);
        $booking->tax = cleanAmount($fareInfoList['price']['tax']);
        $booking->discount = cleanAmount($fareInfoList['price']['discount_psf']);

        if($availability_data['commission'] != null && $availability_data['commission'] != ""){
            $booking->applied_discount = 1;
            $booking->applied_discount_percent = $availability_data['commission'];
            $booking->applied_discount_percent_type = $availability_data['commission_type'];
        }else{
            $booking->applied_discount = 0;
        }
        
        $booking->gross_fare = cleanAmount($fareInfoList['price']['gross_amount']);
        $booking->total_amount = cleanAmount($fareInfoList['price']['total_amount']);
        
        $booking->fare_break_down = $fareInfoList['price']['fare_break_down'];
        $booking->booking_pnr = $pnr;
        $booking->provider_name = $this->ndcBookingId == null ? 'SABRE' : 'SABRE_NDC';
        
        $booking->pnr_expiry = !is_null($this->ndcBookingId) ? sabre_ndc_expiry() : null;
        
        $airline = \App\Models\Airline::where('iata_code', $availability_data['airline']['iata_code'])->first();
        $booking->airline_id = $airline ? $airline->id : null;

        $booking->supplier_id = \App\Models\Connector::where('type', 'SABRE')->first()->supplier_id;

        $booking->departure_airport = $departure_data['origin']['name'];
        $booking->departure_date_time = $departure_data['departure_datetime'];
        $booking->arrival_airport = $arrival_data['destination']['name'];
        $booking->arrival_date_time = $arrival_data['arrival_datetime'];
        $booking->fare_rules = @$booking_detail['fareRules'] ?? null;

        if ($route_type === 'RETURN') {
            $booking->r_departure_airport = $r_departure_data['origin']['name'];
            $booking->r_departure_date_time = $r_departure_data['departure_datetime'];
            $booking->r_arrival_airport = $r_arrival_data['destination']['name'];
            $booking->r_arrival_date_time = $r_arrival_data['arrival_datetime'];
        }

        $booking->baggage = $fareInfoList['baggageInformation'];
        $booking->booking_class = implode(',',$fareInfoList['booking_res_code']);
        $booking->is_refundable = $fareInfoList['is_refundable'];
        $booking->booking_brand = $fareInfoList['rbd'];
        $booking->is_multi_city = $route_type === 'MULTICITY';

        $user = \Illuminate\Support\Facades\Auth::user();
        $booking->booked_by = $user->id;
        $booking->org_id = @$user->org_id ?? null;
        $booking->save();
        
        return $booking;
    }

    private function getFirstSegment($segment_data)
    {
        return $segment_data['segments'][0] ?? null;
    }

    private function getLastSegment($segment_data)
    {
        if (!isset($segment_data['segments']) || count($segment_data['segments']) === 0) {
            return null;
        }
        return $segment_data['segments'][count($segment_data['segments']) - 1];
    }

    private function storeBookedSegments($booking_id, $flights, $availability_data)
    {
        $flights = flightReIndex($flights);
        foreach ($availability_data['segment_data'] as $sector) {
            $parent_id = null;
            foreach ($sector['segments'] as $key => $value_sector) {
                
                $flight = [];

                if(array_key_exists($value_sector['operating_flight_number'], $flights)){
                    $flight = $flights[$value_sector['operating_flight_number']];
                }else{
                    $flight = $flights[$value_sector['marketing_flight_number']];
                }
                
                $booked_segment = \App\Models\BookedSegment::create([
                    'o_flight_number' => $flight['operatingFlightNumber'],
                    'o_airline' => $flight['operatingAirlineCode'],
                    'm_flight_number'=>$value_sector['marketing_flight_number'],
                    'm_airline'=> $value_sector['marketing_code'],
                    'seg_origin' => $flight['fromAirportCode'],
                    'seg_destination' => $flight['toAirportCode'],
                    'seg_departure_datetime' => $flight['departureDate'] . ' ' . $flight['departureTime'],
                    'seg_arrival_datetime' => $flight['arrivalDate'] . ' ' . $flight['arrivalTime'],
                    'departure_terminal' => sabre_terminal($flight['departureTerminalName'] ?? null , $this->ndcBookingId) ,
                    'arrival_terminal' => sabre_terminal($flight['arrivalTerminalName'] ?? null, $this->ndcBookingId),
                    'cabin_fullname' => $flight['cabinTypeName'] . '-' . $flight['cabinTypeCode'],
                    'flight_status_code' => $flight['flightStatusCode'],
                    'flight_duration' => $flight['durationInMinutes'],
                    'booking_id' => $booking_id,
                    'parent_id' => $parent_id,
                    'meal' => @$flight['meals'],
                    'flight_pnr'=> @$flight['confirmationId'] ?? null,
                ]);
                if($key == 0){
                    $parent_id = $booked_segment->id;
                }  
            }
        }
    }
}