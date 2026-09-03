<?php

/**
 * Group flights by itinerary details and combine their pricing options
 * 
 * @param array $pricedItineraries Array of flights from API response
 * @return array Consolidated flights with merged pricing options
 */
if(!function_exists('groupFlightsByItinerary')){
    function groupFlightsByItinerary(array $pricedItineraries): array
    {
        $groupedFlights = [];
        
        foreach ($pricedItineraries as $itinerary) {

            $flightSegment = $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['FlightSegment'];
            
            $itineraryKey = sprintf('%s-%s-%s',
                $flightSegment['DepartureDateTime'],
                $flightSegment['ArrivalDateTime'],
                $flightSegment['FlightNumber']
            );
            
            if (!isset($groupedFlights[$itineraryKey])) {
                $groupedFlights[$itineraryKey] = [
                    'OriginDestinationRefNumber' => $itinerary['OriginDestinationRefNumber'],
                    'AirItinerary' => $itinerary['AirItinerary'],
                    'AirItineraryPricingInfo' => []
                ];
            }
            
            $groupedFlights[$itineraryKey]['AirItineraryPricingInfo'][] = $itinerary['AirItineraryPricingInfo'];
        }
        
        return array_values($groupedFlights);
    }
}

if(!function_exists('organizeFlightsByItineraryType')){
    function organizeFlightsByItineraryType($groupedFlights){

        $outbounds = [];
        $inbounds = [];
        foreach ($groupedFlights as $itinerary) {
            $refNumber = $itinerary['OriginDestinationRefNumber'];
            if($refNumber == '1'){
                $outbounds[] = $itinerary;
            }else{
                $inbounds[] = $itinerary;
            }
        }
        $organizedFlights = [];

        foreach($outbounds as $flight){

            $organizedFlights[] = count($inbounds) > 0 ? [$flight, ...$inbounds] : [$flight];
        }
        return $organizedFlights;
    }
}

if (!function_exists('sort_flight_options')) {
    /**
     * Sorts an array of flight options based on the 'FareType', handling cases
     * where FareInfo might be a single array or an array of arrays.
     *
     * @param array $flights The array of flight options to sort.
     * @return array The sorted array of flight options.
     */
    function sort_flight_options(array $flights): array
    {
        $sortOrder = ['EV', 'EF', 'EX'];

        usort($flights, function ($a, $b) use ($sortOrder) {
            $fareTypeA = null;
            $fareTypeB = null;

            $fareInfoA = \Illuminate\Support\Arr::get($a, 'PTC_FareBreakdowns.PTC_FareBreakdown.FareInfo');
            $fareInfoB = \Illuminate\Support\Arr::get($b, 'PTC_FareBreakdowns.PTC_FareBreakdown.FareInfo');

            if (is_array($fareInfoA) && !\Illuminate\Support\Arr::isAssoc($fareInfoA)) {
                $fareTypeA = \Illuminate\Support\Arr::get($fareInfoA, '0.FareInfo.FareType');
            } elseif (is_array($fareInfoA) && \Illuminate\Support\Arr::isAssoc($fareInfoA)) {
                $fareTypeA = \Illuminate\Support\Arr::get($fareInfoA, 'FareInfo.FareType');
            }

            if (is_array($fareInfoB) && !\Illuminate\Support\Arr::isAssoc($fareInfoB)) {
                $fareTypeB = \Illuminate\Support\Arr::get($fareInfoB, '0.FareInfo.FareType');
            } elseif (is_array($fareInfoB) && \Illuminate\Support\Arr::isAssoc($fareInfoB)) {
                $fareTypeB = \Illuminate\Support\Arr::get($fareInfoB, 'FareInfo.FareType');
            }

            $indexA = array_search($fareTypeA, $sortOrder);
            $indexB = array_search($fareTypeB, $sortOrder);

            $indexA = $indexA === false ? count($sortOrder) : $indexA;
            $indexB = $indexB === false ? count($sortOrder) : $indexB;

            return $indexA <=> $indexB;
        });

        return $flights;
    }
}

if(!function_exists('summarizeFlightPrices')){
    function summarizeFlightPrices($flightData) {
        $summary = [];
        $base_fare = 0.0;
        $tax = 0.0;
        $gross_amount = 0.0;
    
        foreach ($flightData as $flights) {
            $first_flight = $flights[0];
            $price = $first_flight['fare_option'][0]['price'];
    
            $base_fare += (float) str_replace([',', ' '], '', $price['base_fare']);
            $tax += (float) str_replace([',', ' '], '', $price['tax']);
            $gross_amount += (float) str_replace([',', ' '], '', $price['gross_amount']);
        }
    
        $summary['price'] = [
            'base_fare' => numberFormat($base_fare, 2),
            'tax' => numberFormat($tax, 2),
            'gross_amount' => numberFormat($gross_amount, 2),
            'currency' => 'PKR'
        ];
        
        return $summary;
    }
}

if (!function_exists('summarize_prices')) {
    function summarize_prices($fare_info_list) {
        
        $summary = [
            'fare_break_down' => [],
            'base_fare'     => 0,
            'tax'           => 0,
            'gross_amount'  => 0,
            'discount_psf'  => 0,
            'currency'      => 'PKR',
            'total_amount'  => 0
        ];

        foreach ($fare_info_list as $fare) {
            $fare_info = [];
            if(array_key_exists('fare_info_list', $fare)){
                $fare_info = $fare['fare_info_list']['price'];
            }else{
                $fare_info = $fare['price'];
            }
            
            if (isset($fare_info['fare_break_down'])) {
                foreach ($fare_info['fare_break_down'] as $passengerType => $values) {
                    if (!isset($summary['fare_break_down'][$passengerType])) {
                        $summary['fare_break_down'][$passengerType] = [
                           'quantity'      => $values['quantity'],
                            'base_fare'     => 0,
                            'tax'           => 0,
                            'gross_amount'  => 0,
                            'discount_psf'  => 0,
                            'total_amount'  => 0,
                            'currency'      => $values['currency'] ?? 'PKR'
                        ]; 
                    }

                    $summary['fare_break_down'][$passengerType]['base_fare'] += (float) str_replace(',', '', $values['base_fare']);
                    $summary['fare_break_down'][$passengerType]['tax'] += (float) str_replace(',', '', $values['tax']);
                    $summary['fare_break_down'][$passengerType]['gross_amount'] += (float) str_replace(',', '', $values['gross_amount']);
                    $summary['fare_break_down'][$passengerType]['discount_psf'] += (float) str_replace(',', '', $values['discount_psf'] ?? 0);
                    $summary['fare_break_down'][$passengerType]['total_amount'] += (float) str_replace(',', '', $values['total_amount'] ?? 0);
                }
            }

            $summary['base_fare'] += (float) str_replace(',', '', $fare_info['base_fare']);
            $summary['tax'] += (float) str_replace(',', '', $fare_info['tax']);
            $summary['gross_amount'] += (float) str_replace(',', '', $fare_info['gross_amount']);
            $summary['discount_psf'] += (float) str_replace(',', '', $fare_info['discount_psf'] ?? 0);
            $summary['total_amount'] += (float) str_replace(',', '', $fare_info['total_amount']);
            $summary['currency'] = $fare_info['currency'] ?? $summary['currency'];
        }

        foreach ($summary['fare_break_down'] as &$type) {
            $type['base_fare'] = number_format($type['base_fare'], 2);
            $type['tax'] = number_format($type['tax'], 2);
            $type['gross_amount'] = number_format($type['gross_amount'], 2);
            $type['discount_psf'] = number_format($type['discount_psf'], 2);
            $type['total_amount'] = number_format($type['total_amount'], 2);
        }
        
        $summary['base_fare'] = number_format($summary['base_fare'], 2);
        $summary['tax'] = number_format($summary['tax'], 2);
        $summary['gross_amount'] = number_format($summary['gross_amount'], 2);
        $summary['discount_psf'] = number_format($summary['discount_psf'], 2);
        $summary['total_amount'] = number_format($summary['total_amount'], 2);

        return $summary;
    }
}

if (!function_exists('fare_break_down')) {
    function fare_break_down(array $airReservation, $segment_count)
    {
        if (!isset($airReservation['PriceInfo']['PTC_FareBreakdowns']['PTC_FareBreakdown'])) {
            return [];
        }

        $fareBreakDownData = [];
        $baggageInformation = [];
        $total_base_fare = 0;
        $total_tax = 0;
        $total_gross_amount = 0;

        foreach (arrayConversion($airReservation['PriceInfo']['PTC_FareBreakdowns']['PTC_FareBreakdown']) as $paxFareInfo) {

            $passengerTypeData = $paxFareInfo['PassengerTypeQuantity'];
            $pricingInfo       = $paxFareInfo['PassengerFare'];
            $quantity          = $passengerTypeData['Quantity'];


            $totalTax = 0;
            $surcharges = 0;
            if (array_key_exists('Taxes', $pricingInfo)) {
                $totalTax   = $pricingInfo['Taxes']['Amount'];
            }

            if (array_key_exists('Fees', $pricingInfo)) {
                $surcharges = @$pricingInfo['Fees']['Amount'];
            }

            $baseFare = $pricingInfo['BaseFare']['Amount'] + $surcharges;
            $grossFare  = $baseFare + $totalTax;

            $total_base_fare += $baseFare;
            $total_tax += $totalTax;
            $total_gross_amount += $grossFare;

            $fareBreakDown = [
                'quantity'      => $quantity,
                'base_fare'     => numberFormat($baseFare),
                'tax'           => numberFormat($totalTax),
                'gross_amount'  => numberFormat($grossFare),
                'discount_psf'  => '',
                'currency'      => 'PKR',
                'total_amount'  => numberFormat($grossFare)
            ];

            $bagageBreakDown = [];

            for ($i = 0; $i < $segment_count; $i++) {

                $fareInfoList = @arrayConversion($paxFareInfo['FareInfo'])[$i]['PassengerFare'];
                $bagageBreakDown[] = [
                    [
                        "weight" => (int) $fareInfoList['FareBaggageAllowance']['UnitOfMeasureQuantity'] ?? 0,
                        "unit" => "kg",
                        "provisionType" => "A",
                        "provision" => "Checked baggage allowance",
                    ],
                    [
                        "weight" => 7,
                        "unit" => "kg",
                        "provisionType" => "B",
                        "provision" => "Carry-on baggage allowance",
                    ]
                ];
            }

            $passengerCode = $passengerTypeData['Code'];
            $fareBreakDownData[paxType()[$passengerCode]] = $fareBreakDown;
            $baggageInformation[$passengerCode] = $bagageBreakDown;
        }

        return ['base_fare' => $total_base_fare, 'tax' => $total_tax, 'gross_fare' => $total_gross_amount,'fare_break_down' => $fareBreakDownData, 'baggage' => $baggageInformation];
    }
}

if(!function_exists('air_ticketing_key_pair')){
    function air_ticketing_key_pair($ticketing){
        $keyPair = [];
        foreach (arrayConversion($ticketing) as $ticket) {
            $keyPair[$ticket['TravelerRefNumber']] = $ticket;
        }
        return $keyPair;
    }
}

if(!function_exists('import_passengers')){

    function import_passengers($air_travelers, $air_ticketing){
        $passengers = [];
        foreach(arrayConversion($air_travelers) as $air_traveler){
            $passengers[] = [
                'title' => @$air_traveler['PersonName']['NameTitle'] ?? null,
                'passenger_type' =>$air_traveler['PassengerTypeCode'] == 'CHD' ? 'CNN' :  $air_traveler['PassengerTypeCode'],
                'first_name' => $air_traveler['PersonName']['GivenName'],
                'last_name' => $air_traveler['PersonName']['Surname'],
                'd_type' => $air_traveler['Document']['DocType'] == 2 ? 'P' : 'I',
                'd_number' => $air_traveler['Document']['DocID'],
                'd_expiry' => @$air_traveler['Document']['ExpireDate'] ?? null,
                'date_of_birth' => @$air_traveler['Document']['BirthDate'] ?? null,
                'ticket_number' => @$air_ticketing[$air_traveler['TravelerRefNumber']['RPH']]['TicketDocumentNbr'] ?? null,
            ];
        }

        return $passengers;
    }
}

if(!function_exists('terminals')){
    function terminals($pnr) {

        $booking = (new \App\Services\AirBlueService\AirService())->readBooking($pnr);

        $body = $booking['Body']['ReadResponse']['ReadResult'] ?? [];

        $airReservation = $body['AirReservation'] ?? [];

        $od_options = $airReservation['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'];
        $od_options = arrayConversion($od_options);
        
        $terminals = [];
        foreach($od_options as $od_option){
            $flightSegment = $od_option['FlightSegment'] ?? [];
            $terminals[] = [
                'departure_terminal' => $flightSegment['DepartureAirport']['Terminal'],
                'arrival_terminal' => $flightSegment['ArrivalAirport']['Terminal']
            ];
        }

        return $terminals;
    }
}

if (!function_exists('travelers_references')) {
    function travelers_references($booking_detail, $booking)
    {
        $travelers = [];
        $travelerInfo = $booking_detail['Body']['ReadResponse']['ReadResult']['AirReservation']['TravelerInfo']['AirTraveler'];
        $extraSelection = $booking->extra_selection ?? [];

        foreach (arrayConversion($travelerInfo) as $traveler) {
            if ($traveler['PassengerTypeCode'] === 'INF') {
                continue;
            }

            $fullName = trim($traveler['PersonName']['GivenName'] . ' ' . $traveler['PersonName']['Surname']);
            $tempTraveler = [];

            $tempTraveler['traveler_ref_number'] = $traveler['TravelerRefNumber'];
            $tempTraveler['flight_segment_RPH'] = $traveler['FlightSegmentRPHs']['FlightSegmentRPH'];
            
            $baggage = [];
            foreach ((array) $traveler['FlightSegmentRPHs']['FlightSegmentRPH'] as $key => $value) {
                if (isset($booking['baggage'][$key][$traveler['PassengerTypeQuantity']['Code']][0]['weight'])) {
                    $baggage[$key] = $booking['baggage'][$key][$traveler['PassengerTypeQuantity']['Code']][0]['weight'] . 'kgs';
                }
            }
            $tempTraveler['baggage'] = $baggage;

            $extra = $extraSelection[$fullName] ?? [];

            $selectedSeats = [];
            if (!empty($extra['seat'])) {
                foreach ($extra['seat'] as $sector => $seat) {
                    $selectedSeats[$sector] = [
                        'price'             => array_key_exists('TPA_Extensions', $seat) ? numberFormat($seat['TPA_Extensions']['SeatCost']) : '0.00',
                        'seat_availability' => true,
                        'seat_code'         => $seat['RowNumber'] . $seat['SeatNumber'],
                        'seat_number'       => $seat['SeatNumber'],
                    ];
                }
            }
            $tempTraveler['selected_seat'] = $selectedSeats;

            $selectedBaggage = [];
            if (!empty($extra['baggage'])) {
                foreach ($extra['baggage'] as $sector => $bag) {
                    $selectedBaggage[$sector] = [
                        'Available'         => "true",
                        'ChargeAmount'      => $bag['ChargeAmount'],
                        'ChargeCurrency'    => "PKR",
                        'IsRefundable'      => "true",
                        'ItemCode'          => $bag['ItemCode'],
                        'ItemTitle'         => $bag['ItemTitle'],
                    ] ?? null;
                }
            }
            $tempTraveler['selected_baggage'] = $selectedBaggage;

            $selectedWheelChair = [];
            if (!empty($extra['wheel_chair'])) {
                foreach ($extra['wheel_chair'] as $sector => $wheel) {
                    $selectedWheelChair[$sector] = [
                        'ItemCode'          => $wheel['ItemCode'],
                        'ItemTitle'         => $wheel['ItemTitle'],
                        'Description'       => '',
                        'Available'         => "true",
                        'ChargeCurrency'    => "PKR",
                        'ChargeAmount'      => $wheel['ChargeAmount'],
                        'IsRefundable'      => "true"
                    ] ?? null;
                }
            }
            $tempTraveler['selected_wheel_chair'] = $selectedWheelChair;

            $travelers[$fullName] = $tempTraveler;
        }

        return $travelers;
    }
}

if (!function_exists('extra_selection')) {

    function extra_selection($pnr)
    {
        $booking_detail = (new \App\Services\AirBlueService\AirService())->readBooking($pnr);
        $travelerInfo = $booking_detail['Body']['ReadResponse']['ReadResult']['AirReservation']['TravelerInfo'];
        $air_traveler = $travelerInfo['AirTraveler'];
        $specialReqDetails = arrayConversion($travelerInfo['SpecialReqDetails']);

        // Get seat + ancillaries
        $seats = seats_key_pair($specialReqDetails[0]['SeatRequests']['SeatRequest']);
        $ancillaries = seprate_ancillaries_key_pair(array_key_exists(1, $specialReqDetails) ? $specialReqDetails[1]['SpecialServiceRequests']['SpecialServiceRequest'] : null);

        $extra_selection_list = [];
        $extra_total_amount = 0;
        $extra_amounts = [
            'Adult' => 0,
            'Child' => 0,
        ];

        foreach (arrayConversion($air_traveler) as $traveler) {

            if ($traveler['PassengerTypeQuantity']['Code'] == 'INF') {
                continue;
            }

            $travelerType = $traveler['PassengerTypeQuantity']['Code'] == 'ADT' ? 'Adult' : 'Child';
            $rph = $traveler['TravelerRefNumber']['RPH'];

            $seat = $seats[$rph] ?? [];
            $baggage = $ancillaries['baggage'][$rph] ?? [];
            $wheel_chair = $ancillaries['wheel_chair'][$rph] ?? [];

            $traveler_total = 0;

            if (!empty($seat)) {
                foreach($seat as $value){

                    $traveler_total += $value['TPA_Extensions']['SeatCost'] ?? 0;
                }
            }

            if (!empty($baggage)) {
                foreach ($baggage as $bag) {
                    $traveler_total += $bag['ChargeAmount'] ?? 0;
                }
            }

            if (!empty($wheel_chair)) {
                foreach ($wheel_chair as $wheel) {
                    $traveler_total += $wheel['ChargeAmount'] ?? 0;
                }
            }

            // Add to type total
            $extra_amounts[$travelerType] += $traveler_total;
            $extra_total_amount += $traveler_total;
            $extra_selection_list[$traveler['PersonName']['GivenName'] . ' ' . $traveler['PersonName']['Surname']] = [
                'seat'        => $seat,
                'baggage'     => $baggage,
                'wheel_chair' => $wheel_chair
            ];
        }

        $booking = \App\Models\Booking::where('booking_pnr', $pnr)->first();
        $fare_break_down = $booking->fare_break_down;

        foreach ($fare_break_down as $type => &$data) {
            $extra = $extra_amounts[$type] ?? 0;
            $data['extra_amount'] = number_format($extra, 2);
        }

        unset($data);

        $booking->extra_selection = $extra_selection_list;
        $booking->fare_break_down = $fare_break_down;
        $booking->is_seat_selected = true;
        $booking->extra_amount = $extra_total_amount;
        $booking->save();

        return true;
    }
}

if(!function_exists('seats_key_pair')){
    function seats_key_pair($seats){
        if(is_null($seats)){
            return null;
        }
        $key_pair = [];
        foreach(arrayConversion($seats) as $seat){
            $key_pair[$seat['TravelerRefNumberRPHList']][$seat['FlightRefNumberRPHList']] = $seat;
        }
        return $key_pair;
    }
}

if(!function_exists('seprate_ancillaries_key_pair')){
    function seprate_ancillaries_key_pair($ancillaries){
        if(is_null($ancillaries)){
            return null;
        }

        $baggageAncillaries = [];
        $wheelChairAncillaries = [];

        foreach (arrayConversion($ancillaries) as $ancillary) {
            if (str_starts_with($ancillary['ItemCode'], 'XB')) {
                $baggageAncillaries[] = $ancillary;
            } elseif (str_starts_with($ancillary['ItemCode'], 'WCH')) {
                $wheelChairAncillaries[] = $ancillary;
            }
        }

        $baggage_key_pair = [];
        $wheelchair_key_pair = [];

        if(count($baggageAncillaries) > 0){
            foreach($baggageAncillaries as $baggageAncillary){
                $baggage_key_pair[$baggageAncillary['TravelerRefNumberRPHList']][$baggageAncillary['FlightRefNumberRPHList']] = [
                    'ItemCode'      => $baggageAncillary['ItemCode'],
                    'ItemTitle'     => $baggageAncillary['ItemTitle'],
                    'ChargeAmount'  => $baggageAncillary['ChargeAmount']
                ];
            }
        }

        if(count($wheelChairAncillaries) > 0){
            foreach($wheelChairAncillaries as $wheelChairAncillary){
                $wheelchair_key_pair[$wheelChairAncillary['TravelerRefNumberRPHList']][$wheelChairAncillary['FlightRefNumberRPHList']] = [
                    'ItemCode'          => $wheelChairAncillary['ItemCode'],
                    'ItemTitle'         => $wheelChairAncillary['ItemTitle'],
                    'ChargeAmount'      => $wheelChairAncillary['ChargeAmount'],
                    'ChargeCurrency'    => $wheelChairAncillary['ChargeCurrency']
                ];
            }
        }

        return [
            'baggage' => $baggage_key_pair,
            'wheel_chair' => $wheelchair_key_pair,
        ];
    }
}