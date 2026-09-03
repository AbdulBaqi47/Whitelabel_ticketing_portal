<?php

function sum_amount_from_array(array $items, string $key = 'Amount'): float
{
    $sum = 0;

    foreach ($items as $item) {
        if (isset($item[$key])) {
            $sum += (float) $item[$key];
        }
    }

    return $sum;
}

if(!function_exists('jinnah_passenger')){
    function jinnah_passenger(){

        return [
            'ADT' => 'ADT',
            'CNN' => 'CHD',
            'INF' => 'INF'
        ];
    }
}
if(!function_exists('manage_price')){
    function manage_price($pricedItinerary){

        $pricedItinerary = $pricedItinerary['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary']['AirItineraryPricingInfo'];

        $fareBreakDownData = [];
        $totalTaxValue = 0;
        $totalFeeValue = 0;
        $sumarize_per_pax_bundled_fee = 0;

        foreach (arrayConversion($pricedItinerary['PTC_FareBreakdowns']['PTC_FareBreakdown']) as $paxFareInfo) {

            $passengerTypeData = $paxFareInfo['PassengerTypeQuantity'];
            $pricingInfo       = $paxFareInfo['PassengerFare'];
            $quantity          = $passengerTypeData['Quantity'];

            $baseFare = (float) $pricingInfo['BaseFare']['Amount'] * $quantity;

            $taxes = $pricingInfo['Taxes']['Tax'] ?? [];
            $totalTax = sum_amount_from_array($taxes) * $quantity;

            $fees = $pricingInfo['Fees']['Fee'] ?? [];
            $surcharges = sum_amount_from_array($fees) * $quantity;

            $grossFare = $baseFare + $totalTax + $surcharges;

            $totalFeeValue += $surcharges;
            $totalTaxValue += $totalTax;

            $fareBreakDown = [
                'quantity'    => $quantity,
                'base_fare'    => numberFormat($baseFare + $surcharges),
                'tax'    => numberFormat($totalTax),
                'discount'    => 0,
                'gross_fare' => numberFormat($grossFare),
                'discount' => 0,
                'fees' => 0,
                'currency' => 'PKR'
            ];
            $passengerCode = $passengerTypeData['Code'];
            $fareBreakDownData[paxType()[$passengerCode]] = $fareBreakDown;
        }

        // Pricing Overview
        $pricingOverview = $pricedItinerary['ItinTotalFare'];
        $commission      = 0;

        $summarize = ($pricingOverview['BaseFare']['Amount'] + $sumarize_per_pax_bundled_fee) + $totalFeeValue;

        $fareInformation = [
            'fare_break_down' => $fareBreakDownData,
            'base_fare'       => numberFormat($summarize),
            'tax'             => numberFormat($totalTaxValue),
            'discount'        => numberFormat($commission),
            'gross_amount'    => numberFormat($summarize + $totalTaxValue),
            'discount'        => 0,
            'fees'            => 0,
            'currency'        => 'PKR',
        ];
        return $fareInformation;
    }
}

if (!function_exists('normalize_jinnah_sectors')) {
    function normalize_jinnah_sectors(array $response): array
    {
        $legs = array_values($response['ondWiseFlightCombinations']);

        $onward_date_key = key($legs[0]['dateWiseFlightCombinations']);
        $onward_flights = $legs[0]['dateWiseFlightCombinations'][$onward_date_key]['flightOptions'] ?? [];

        $return_flights = [];
        if (array_key_exists(1, $legs)) {
            $return_date_key = key($legs[1]['dateWiseFlightCombinations']);
            $return_flights = $legs[1]['dateWiseFlightCombinations'][$return_date_key]['flightOptions'] ?? [];
        }

        $results = [];

        if (count($return_flights) > 0) {
            foreach ($onward_flights as $onward) {
                foreach ($return_flights as $return) {
                    $flight_segments = [];
                    array_push($flight_segments, $onward['flightSegments']);
                    array_push($flight_segments, $return['flightSegments']);
                    $results[] = $flight_segments;
                }
            }
        } else {
            foreach ($onward_flights as $key => $onward) {
                // if($key != 0){
                //     continue;
                // }
                $results[] = $onward['flightSegments'];

            }
        }

        return $results;
    }
}


if(!function_exists('checked_baggage')){
    function checked_baggage($brand){

        $checked = 0;

        if($brand == "Value"){
            $checked = 30;
        } else if($brand == "Ultimate"){
            $checked = 40;
        }

        return $checked;
    }
}

if (!function_exists('combineSegmentsByRoute')) {
    /**
     *
     * @param  array  $segments  Array of flight segments (from API)
     * @param  array  $request   Original search request (must include 'route_type', 'origin', 'destination')
     * @return array
     */
    function combineSegmentsByRoute(array $segments, array $request): array
    {
        $combined = [];
        
        if (isset($request['route_type']) && strtoupper($request['route_type']) === 'RETURN') {
            $outbound = [];
            $return = [];
            $currentGroup = [];

            foreach ($segments as $seg) {
                $flight = $seg['FlightSegment'];
                $arrival = $flight['ArrivalAirport']['LocationCode'];

                $currentGroup[] = $flight;

                if ($arrival === $request['destination']) {
                    $outbound = $currentGroup;
                    $currentGroup = [];
                }

                elseif ($arrival === $request['origin']) {
                    $return = $currentGroup;
                    $currentGroup = [];
                }
            }

            if (!empty($outbound)) $combined[] = $outbound;
            if (!empty($return)) $combined[] = $return;

        } else {
            $segment_list = [];
            foreach(arrayConversion($segments) as $segment){
                $segment_list[] = $segment['FlightSegment'];
            }
            $combined[] = $segment_list;
        }

        return $combined;
    }
}


if(!function_exists('add_basic_service')){
    function add_basic_service($bundledService): array{
        $basicService = [
            "bunldedServiceId" => "0",
            "bundledServiceName" => "Basic",
            "perPaxBundledFee" => "0.00",
            "bookingClasses" => "",
            "description" => "No checked baggage, seat selection, or meal included.",
            "includedServies" => []
        ];
        
        array_unshift($bundledService, $basicService);
        
        return $bundledService;
    }
}

if(!function_exists('service_required')){
    function service_required($service_name){
        return $service_name !== 'Basic';
    }
}

if(!function_exists('bundle_service_manipulation')){

    function bundle_service_manipulation($confirmation_data){

        $bundle_services = [];
        
        foreach($confirmation_data[0]['legs'] as $key => $leg){
            $bundle_service = $confirmation_data[$key]['bundledService'];
            foreach($leg['segments'] as $s_key => $segments){
                $bundle_services[] = $bundle_service;
            }
        }
        return $bundle_services;
    }
}

if(!function_exists('extra_included_item_collection')){
    function extra_included_item_collection($booking_res, $extras, $request){
        // dd($booking_res, $extras, $request);
        $ancillaries_key_pair = ancillaries_key_pair($booking_res);
    }
}

if(!function_exists('ancillaries_key_pair')){
    function ancillaries_key_pair($booking){

        $ancillaries = [
            'seats'     => [],
            'baggage'   => [],
            'meals'     => [],
        ];


        $specialReqAncillaries = $booking['Body']['OTA_AirBookRS']['AirReservation']['TravelerInfo']['SpecialReqDetails'];


        if(array_key_exists('SeatRequests', $specialReqAncillaries)){

            foreach(arrayConversion($specialReqAncillaries['SeatRequests']['SeatRequest']) as $key => $seat){

                $ancillaries['seats'][$seat['TravelerRefNumberRPHList']][] = $seat;
            }
        }

        if(array_key_exists('MealRequests', $specialReqAncillaries)){

            foreach(arrayConversion($specialReqAncillaries['MealRequests']['MealRequest']) as $key => $meal){
                $ancillaries['baggage'][$meal['TravelerRefNumberRPHList']][] = $meal;

            }
        }

        if(array_key_exists('BaggageRequests', $specialReqAncillaries)){

            foreach(arrayConversion($specialReqAncillaries['BaggageRequests']['BaggageRequest']) as $key => $baggage){
                $ancillaries['meals'][$baggage['TravelerRefNumberRPHList']][] = $baggage;

            }
        }

        dd($ancillaries);
    }
}