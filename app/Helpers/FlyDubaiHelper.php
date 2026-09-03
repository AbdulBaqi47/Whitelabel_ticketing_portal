<?php

/**
 * leg_details_key_pair
 */
if (!function_exists('leg_details_key_pair')) {
    function leg_details_key_pair($legDetails)
    {
        $keyPair = [];

        if (!empty($legDetails['LegDetail']) && is_array($legDetails['LegDetail'])) {
            foreach ($legDetails['LegDetail'] as $legDetail) {
                // Use PFID + DepartureDate as unique composite key
                $key = $legDetail['PFID'] . '_' . date('YmdHi', strtotime($legDetail['DepartureDate']));
                $keyPair[$key] = $legDetail;
            }
        }

        return $keyPair;
    }
}

if (!function_exists('tax_details_key_pair')) {
    function tax_details_key_pair($taxDetails)
    {
        $keyPair = [];
        if (array_key_exists(0, $taxDetails) && count($taxDetails[0]) > 0) {
            foreach ($taxDetails as $taxDetail) {
                $keyPair[$taxDetail['TaxID']] = $taxDetail;
            }
        }
        return $keyPair;
    }
}

if (!function_exists('service_details_key_pair')) {
    function service_details_key_pair($service_details)
    {
        $keyPair = [];

        if (array_key_exists(0, $service_details) && count($service_details[0]) > 0) {
            foreach ($service_details as $service_detail) {
                $keyPair[$service_detail['ID']] = $service_detail;
            }
        }
        return $keyPair;
    }
}
if (!function_exists('flight_segments_key_pair')) {
    function flight_segments_key_pair($flight_segments)
    {
        $keyPair = [];
        if (array_key_exists(0, $flight_segments) && count($flight_segments[0]) > 0) {
            foreach ($flight_segments as $flight_segment) {
                $keyPair[$flight_segment['LFID']] = $flight_segment;
            }
        }
        return $keyPair;
    }
}

if(!function_exists('flight_combinability_pair')){

    function flight_combinability_pair(array $flight_result): array{

        $keyPair = [];

        if(array_key_exists('BS', $flight_result['Combinability']) && count($flight_result['Combinability']['BS']) > 0){

            foreach($flight_result['Combinability']['BS'] as $key => $value){
                $keyPair[$value['SolnRef'][0]][] = $value['SolnRef'][1];
            }
        }
        return $keyPair;
    }
}

if (!function_exists('convert_dateToFDFormat')) {
    /**
     * Convert date to FlyDubai format (dd/mm/yyyy)
     *
     * @param string $date
     * @return string
     */
    function convert_dateToFDFormat($date)
    {
        $formattedDate = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('d/m/Y');
        return $formattedDate;
    }
}

if (!function_exists('fb_passenger_type')) {
    function fb_passenger_type()
    {
        return [
            1 => 'ADLT',
            6 => 'CHLD',
            5 => 'INFT'
        ];
    }
}


if (!function_exists('groupFlights')) {

    function groupFlights($segmentDetails, $request)
    {
       
        $flights = $segmentDetails ?? [];
        $origin = $request['origin'];
        $destination = $request['destination'];
        $routeType = $request['route_type'];

        if ($routeType === 'RETURN') {
            $outbound = [];
            $inbound = [];

            foreach ($flights as $flight) {
                if ($flight['Origin'] === $origin && $flight['Destination'] === $destination) {
                    $outbound[] = $flight;
                } elseif ($flight['Origin'] === $destination && $flight['Destination'] === $origin) {
                    $inbound[] = $flight;
                }
            }

            return [$outbound, $inbound];
        }
        return [$flights];
    }
}

if (!function_exists('get_bagage_with_code')) {
    function get_bagage_with_code()
    {
        return [
            'BAGB' => '20',
            'BAGL' => '30',
            'BAGX' => '40',
            'JBAG' => '40',
            'BAGI' => '10'
        ];
    }
}

if (!function_exists('re_booking')) {
    function re_booking()
    {
        return [
            'Economy Class' => 'no',
            'Business Class' => 'yes',
            'LITE' => 'no',
            'VALUE' => 'penalties apply',
            'FLEX' => 'yes',
            'Business' => 'yes'
        ];
    }
}

if (!function_exists('cancellation')) {
    function cancellation()
    {
        return [
            'Economy Class' => 'no',
            'Business Class' => 'yes',
            'LITE' => 'no',
            'VALUE' => 'penalties apply',
            'FLEX' => 'penalties apply',
            'Business' => 'penalties apply'
        ];
    }
}

if (!function_exists('originDestinations')) {
    function originDestinations($data)
    {
        $sectors = [];
        foreach (arrayConversion($data) as $key => $value) {

            $leg_origin = "";
            $leg_designation = "";

            if($key == 0){
                $leg_origin = $value['request']['origin'];
                $leg_designation = $value['request']['destination'];
            }else{
                $leg_origin = $value['request']['destination'];
                $leg_designation = $value['request']['origin'];
            }
            $leg = $value['leg'];
            $sectors[] = [
                "odID" => $leg['LFID'],
                "origin" => $leg_origin,//$leg['Origin'],
                "destination" => $leg_designation,//$leg['Destination'],
                "flightNum" => $leg['FlightNum'],
                "depDate" => $leg['DepartureDate'],
                "isPromoApplied" => false,
                "fareBrand" => [fareBrand($value['fareType'], $value['tax_details'])],
                "segmentDetails" => segmentDetails($value['actual_segments'], $value['fareType']['FareInfos']),
            ];
        }
        return $sectors;
    }
}

if (!function_exists('fareBrand')) {
    function fareBrand($fareInfos, $tax_details)
    {
        $fareBrand = [
            'fareBrandID' => $fareInfos['FareTypeID'],
            'fareBrandName' => $fareInfos['FareTypeName'],
            'fareInfos' => []
        ];
        $fareInformations = [];

        foreach (arrayConversion($fareInfos['FareInfos']['FareInfo']) as $value) {

            $value = $value['Pax'][0];
            $fareInformations[] = [
                'paxFareInfos' => [
                    [
                        'fareID' => $value['FareID'],
                        'ID' => $value['ID'],
                        'FBC' => $value['FBCode'],
                        'fareClass' => $value['FCCode'],
                        'cabin' => $value['Cabin'],
                        'baseFare' => $value['BaseFareAmt'],
                        'ruleID' => $value['RuleId'],
                        'originalFare' => $value['BaseFareAmt'],
                        'totalFare' => $value['FareAmtInclTax'],
                        'PTC' => $value['PTCID'],
                        'seatAvailability' => $value['SeatsAvailable'],
                        'infantAvailability' => $value['InfantSeatsAvailable'],
                        'secureHash' => $value['hashcode'],
                        'fareCarrier' => $value['FareCarrier'],
                        'applicableTaxDetails' => applicableTaxDetails($value['ApplicableTaxDetails']['ApplicableTaxDetail'], $tax_details),
                    ],
                ],
            ];
        }

        $fareBrand['fareInfos'] = $fareInformations;
        return $fareBrand;
    }
}
if (!function_exists('segmentDetails')) {
    function segmentDetails($segmentDetails, $fareType)
    {
        $segments = [];
        foreach ($segmentDetails as $segmentDetail) {
            $segments[] = [
                "segmentID" => $segmentDetail['PFID'],
                "origin" => $segmentDetail['Origin'],
                "destination" => $segmentDetail['Destination'],
                "depDate" => $segmentDetail['DepartureDate'],
                "arrDate" => $segmentDetail['ArrivalDate'],
                "bookingCodes" => [bookingCodes($fareType)],
                "OAFlight" => false,
                "operCarrier" => $segmentDetail['OperatingCarrier'],
                "operFlightNum" => $segmentDetail['FlightNum'],
                "mrktCarrier" => $segmentDetail['MarketingCarrier'],
                "mrktFlightNum" => $segmentDetail['MarketingFlightNum'],
            ];
        }
        return $segments;
    }
}

if (!function_exists('bookingCodes')) {
    function bookingCodes($fareInfos)
    {

        $pax_ids = [];

        foreach (arrayConversion($fareInfos['FareInfo']) as $fareInfo) {
            $pax_ids[] = $fareInfo['Pax'][0]['ID'];
        }

        $fare = arrayConversion($fareInfos['FareInfo'])[0]['Pax'][0];
        return [
            'fareClass' => $fare['FCCode'],
            'cabin' => $fare['Cabin'],
            'paxID' => $pax_ids,
        ];
    }
}

if(!function_exists('applicableTaxDetails')){
    function applicableTaxDetails($taxes, $tax_details){

        $applicable_tax_details = [];

        foreach(arrayConversion($taxes) as $tax){
            $applicable_tax_details[] = [
                'Amt'=> $tax['Amt'],
                'TaxID'=> $tax['TaxID'],
                'TaxCode'=> $tax_details[$tax['TaxID']]['TaxCode']
            ];
        }
        return $applicable_tax_details;
    }
}

if(!function_exists('baggage')){
    function baggage(array $prices):array{

        $baggageInformation = [];
        foreach($prices as $price){
            array_push($baggageInformation,$price['baggageInformation']);
        }

        return $baggageInformation;
    }
}

if (!function_exists('summarize_prices_fz')) {
    function summarize_prices_fz($prices)
    {
        $summary = [
            'fare_break_down' => [],
            'base_fare'     => 0,
            'tax'           => 0,
            'gross_amount'  => 0,
            'discount_psf'  => 0,
            'currency'      => 'PKR',
            'total_amount'  => 0,
            'pia_fees'      => 0,
        ];

        foreach ($prices as $fare) {
            if(array_key_exists('price', $fare)){
                $fare_info = $fare['price'];
            } else {
                $fare_info = $fare;
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

            if (isset($fare_info['pia_fees'])) {
                $summary['pia_fees'] += (float) str_replace(',', '', $fare_info['pia_fees']);
            }
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
        $summary['pia_fees'] = number_format($summary['pia_fees'], 2);

        return $summary;
    }
}