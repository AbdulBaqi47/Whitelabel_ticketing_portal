<?php

namespace App\Services\SabreService;
class Parsers
{
    use \App\Traits\AirlinePromotion;
    private $request;
    private $statistics;
    private $scheduleDescs;
    private $taxDescs;
    private $taxSummaryDescs;
    private $obFeeDescs;
    private $fareComponentDescs;
    private $brandFeatureDescs;
    private $baggageAllowanceDescs;
    private $legDescs;
    private $itineraryGroups;
    private $outboundFlightDetails;
    private $inboundFlightDetails;
    private $segmentStartDate;
    private $segmentEndDate;
    private $segmentStartTime;
    private $segmentEndTime;
    private $layoverBTWSegments;
    private $count = 1;
    private $flight_prefix = 'flight_search';
    private $sector = [];
    private $flight_numbers = [];
    private $commission = null;
    private $commission_type = null;
    private $code_share = false;

    public function parseBargainFinderResponse($response, $request)
    {
        if (!$response || !array_key_exists('groupedItineraryResponse', $response)) {
            return [];
        }

        $groupedItineraryResponse = $response['groupedItineraryResponse'];
        $this->request = $request;
        $this->statistics = $groupedItineraryResponse["statistics"]['itineraryCount'] ?? 0;

        $this->scheduleDescs = $this->keyValuePair($groupedItineraryResponse["scheduleDescs"] ?? []);
        $this->taxDescs = $this->keyValuePair($groupedItineraryResponse["taxDescs"] ?? []);
        $this->taxSummaryDescs = $this->keyValuePair($groupedItineraryResponse["taxSummaryDescs"] ?? []);
        $this->obFeeDescs = array_key_exists("obFeeDescs", $groupedItineraryResponse)
            ? $this->keyValuePair($groupedItineraryResponse["obFeeDescs"])
            : [];
            
        $this->fareComponentDescs = $this->keyValuePair($groupedItineraryResponse["fareComponentDescs"] ?? []);
        $this->brandFeatureDescs = $this->keyValuePair($groupedItineraryResponse["brandFeatureDescs"] ?? []);
        $this->baggageAllowanceDescs = $this->keyValuePair($groupedItineraryResponse["baggageAllowanceDescs"] ?? []);
        $this->legDescs = $this->keyValuePair($groupedItineraryResponse["legDescs"] ?? []);

        $parsedResponse = [];

        if (!isset($groupedItineraryResponse["itineraryGroups"]) || !is_array($groupedItineraryResponse["itineraryGroups"])) {
            return [];
        }

        foreach ($groupedItineraryResponse["itineraryGroups"] as $ItineraryGroup) {
            if (!isset($ItineraryGroup['itineraries']) || !is_array($ItineraryGroup['itineraries'])) {
                continue;
            }
            $this->commission = null;
            $this->commission_type = null;
            foreach ($ItineraryGroup['itineraries'] as $itinerary) {
                $this->code_share = false;
                $legs = [];
                $airline_code = "";
                $pricingInformation = $itinerary['pricingInformation'] ?? [];

                $source  = $pricingInformation[0]['pricingSubsource'] == 'NDC_CONNECTOR' ? 'SABRE_NDC' : 'SABRE';

                if ($request['route_type'] !== 'multicity' || count($request['departure_location'] ?? []) <= 2) {
                    $airline_code = $pricingInformation[0]['fare']['validatingCarrierCode'] ?? '';
                } else {
                    $airline_code = $pricingInformation[7]['fare']['validatingCarrierCode'] ?? '';
                }
                $flight_data['airline'] = \App\Models\Airline::where('iata_code', $airline_code)
                    ->select('name', 'thumbnail', 'iata_code')
                    ->first();


                if(is_null($flight_data['airline'])){
                    continue;
                }

                foreach ($itinerary['legs'] ?? [] as $leg_key => $leg_value) {
                    $resStartDate = $this->getLegStartDate($request, $leg_key);
                    $this->sector = [];
                    $legRef = $this->legDescs[$leg_value['ref']] ?? null;

                    if ($legRef) {
                        $legs[$leg_key] = [
                            'segments' => $this->getLegsSchedules($legRef['schedules'] ?? [], $resStartDate),
                            'journey_duration' => $legRef['elapsedTime'] ?? 0,
                            'sector' => $this->sector,
                            'flight_number' => $this->flight_numbers,
                        ];
                    }
                }

                $fares = [];
                foreach ($pricingInformation as $pricingInfo) {
                    if (array_key_exists('soldOut', $pricingInfo)) {
                        continue;
                    }

                    $pre_booking_id = random_id('FB');

                    $priceDetails = $this->getPriceDetails($pricingInfo, $pre_booking_id, $flight_data['airline']['iata_code'], $source);

                    $fares[] = $priceDetails[0];
                    set_data($pre_booking_id, $this->flight_prefix, 600, [
                        'fareInfoList'      => $priceDetails,
                        'segment_data'      => $legs,
                        'request'           => $this->request,
                        'airline'           => $flight_data['airline'],
                        'API'               => $source,
                        'commission'        => $this->commission,
                        'commission_type'   => $this->commission_type,
                        'ndc_data'          => $source == 'SABRE_NDC' ? ndc_booking_data_collector($pricingInfo) : null,
                        'total_price'       => $pricingInfo['fare']['totalFare']['totalPrice']
                    ]);
                }
                $flight_data['fare_option'] = $fares;
                $flight_data['legs'] = $legs;
                $flight_data['cheap_fare'] = getCheapestFareOption($fares);
                $flight_data['name'] = 'SABRE_API';
                $flight_data['provider'] = $source;
                $parsedResponse[] = $flight_data;
            }
        }

        return $parsedResponse;
    }

    private function getLegStartDate($request, $legKey)
    {
        $routeType = $request['route_type'] ?? '';

        return match ($routeType) {
            'ONEWAY' => $request['departure_date'] ?? '',
            'MULTICITY' => $this->getDateFromResponse($legKey),
            'RETURN' => ($legKey === 1 ? ($request['return_date'] ?? '') : ($request['departure_date'] ?? '')),
            default => '',
        };
    }

    public function getLegsSchedules($itinerarySchedules, $resStartDate)
    {
        $schedules = [];
        $sector = [];
        $flight_numbers = [];

        $mk_airline = '';
        foreach ($itinerarySchedules as $key => $itinerarySchedule) {
            
            $segment = $this->scheduleDescs[$itinerarySchedule['ref']] ?? null;

            if (!$segment) {
                continue;
            }
            if($key == 0){

                $mk_airline = $segment['carrier']['marketing'];
            }
            
            if ($mk_airline !== $segment['carrier']['operating']) {
                $this->code_share = true;
            }

            $explodedDepTime = $this->getUTCGMT($segment['departure']['time'] ?? '');
            $explodedArrTime = $this->getUTCGMT($segment['arrival']['time'] ?? '');

            $scheduleStartTime = $this->keyCheck('0', $explodedDepTime, '00:00');
            $scheduleEndTime = $this->keyCheck('0', $explodedArrTime, '00:00');

            $arrGMTOffset = $this->keyCheck('1', $explodedArrTime, 0);
            $deptGMTOffset = $this->keyCheck('1', $explodedDepTime, 0);

            $scheduleStartDate = $this->getSegmentStartDate($key, $resStartDate, $scheduleStartTime, $segment['elapsedTime'] ?? 0);
            $scheduleEndDate = $this->getSegmentEndDate($scheduleStartDate, $scheduleStartTime, $scheduleEndTime, $segment['elapsedTime'] ?? 0, $arrGMTOffset, $deptGMTOffset);

            $operating_flight_number = array_key_exists('operatingFlightNumber', $segment['carrier']) ? $segment['carrier']['operatingFlightNumber'] : $segment['carrier']['marketingFlightNumber'];
            
            $schedule = [
                'arrival_datetime' => "{$scheduleEndDate} {$scheduleEndTime}",
                'departure_datetime' => "{$scheduleStartDate} {$scheduleStartTime}",
                'DepartureDateTimeXML' => "{$scheduleStartDate}T{$scheduleStartTime}",
                'ArrivalDateTimeXML' => "{$scheduleEndDate}T{$scheduleEndTime}",
                'code_share' => ($segment['carrier']['marketing'] ?? '') !== ($segment['carrier']['operating'] ?? ''),
                'duration_minutes' => $segment['elapsedTime'] ?? 0,
                'flight_number' => ($segment['carrier']['operating'] ?? '') . '-' . $operating_flight_number,
                'marketing_code' => $segment['carrier']['marketing'] ?? '',
                'marketing_flight_number' => $segment['carrier']['marketingFlightNumber'] ?? '',
                'operating_code' => $segment['carrier']['operating'] ?? '',
                'operating_flight_number' => $operating_flight_number,
                'sector' => ($segment['departure']['country'] ?? '') === ($segment['arrival']['country'] ?? '') ? 'DOSMATIC' : 'INTERNATIONAL',
                'layover_waited_time' => $key !== 0 ? abs(\Carbon\Carbon::parse("{$scheduleStartDate} {$scheduleStartTime}")->diffInMinutes(\Carbon\Carbon::parse($schedules[$key-1]['arrival_datetime']))) : 0,
            ];

            $flight_numbers[] = $schedule['flight_number'];
            $schedule['destination'] = ['terminal' => @$segment['arrival']['terminal'] ?? '' ,...fetch_airport($segment['arrival']['airport'] ?? '')];
            $schedule['origin'] = ['terminal' => @$segment['departure']['terminal'] ?? '' ,...fetch_airport($segment['departure']['airport'] ?? '')];
            $schedule['marketing_airline'] = fetch_airline($segment['carrier']['marketing'] ?? '');
            $schedule['operating_airline'] = fetch_airline($segment['carrier']['operating'] ?? '');
            $schedules[] = $schedule;

            if ($key == 0) {
                $sector[] = $segment['departure']['airport'] ?? '';
                $lastSegment = $this->scheduleDescs[$itinerarySchedules[count($itinerarySchedules) - 1]['ref']] ?? null;
                $sector[] = $lastSegment['arrival']['airport'] ?? '';
            }
        }

        $this->flight_numbers = $flight_numbers;
        $this->sector = $sector;

        return $schedules;
    }

    public function privateFare($ref)
    {
        $privateFareExists = array_key_exists('privateFare', $this->fareComponentDescs[$ref]);
        return $privateFareExists;
    }

    public function keyValuePair($dataArr)
    {
        $keyValues = [];
        foreach ($dataArr as $key => $keyValue) {
            $keyValues[$keyValue['id']] = $keyValue;
        }
        return $keyValues;
    }

    public function getSegmentStartDate($key, $resStartDate, $scheduleStartTime, $elapsedTime)
    {
        $startDate = '';
        if ($key === 0) {
            $startDate = $resStartDate;
            $this->segmentStartDate = $startDate;
            $this->segmentStartTime = $scheduleStartTime;
        } else {
            $parsedscheduleStartTime = $this->segmentEndTime > $scheduleStartTime ? \Carbon\Carbon::parse($resStartDate . ' ' . $scheduleStartTime)->addDay(1) : \Carbon\Carbon::parse($resStartDate . ' ' . $scheduleStartTime);
            $parsedsegmentEndTime = \Carbon\Carbon::parse($this->segmentEndDate . ' ' . $this->segmentEndTime);
            $layover = abs($parsedscheduleStartTime->diffInMinutes($parsedsegmentEndTime));
            $this->layoverBTWSegments = $layover;
            $startDateWithLayover = "";
            if ($this->segmentEndTime > $scheduleStartTime) {
                $dateValCheck = \Carbon\Carbon::parse($this->segmentEndDate . ' ' . $this->segmentEndTime)->addMinutes($layover)->isoFormat('YYYY-MM-DD');
                if ($dateValCheck > $this->segmentEndDate) {
                    $startDateWithLayover = $dateValCheck;
                } else {
                    $startDateWithLayover = \Carbon\Carbon::parse($this->segmentEndDate . ' ' . $this->segmentEndTime)->addMinutes($layover)->isoFormat('YYYY-MM-DD');
                }
            } else {
                $startDateWithLayover = \Carbon\Carbon::parse($this->segmentEndDate . ' ' . $this->segmentEndTime)->isoFormat('YYYY-MM-DD');
            }
            $startDate = \Carbon\Carbon::parse($startDateWithLayover)->addMinutes($elapsedTime)->isoFormat('YYYY-MM-DD');
            $this->segmentStartDate = $startDate;
            $this->segmentStartTime = $scheduleStartTime;
        }

        return $startDate;
    }
    public function getSegmentEndDate($scheduleStartDate, $scheduleStartTime, $scheduleEndTime, $elapsedTime, $arrGMTOffset, $deptGMTOffset)
    {
        $utcDiff = \Carbon\Carbon::parse($arrGMTOffset)->diffInMinutes(\Carbon\Carbon::parse($deptGMTOffset));
        if ($scheduleStartTime > $scheduleEndTime) {
            $dateValCheck = \Carbon\Carbon::parse($scheduleStartDate . $scheduleStartTime)->addMinutes($elapsedTime - $utcDiff)->isoFormat('YYYY-MM-DD');
            if ($dateValCheck > $scheduleStartDate) {
                $endDate = $dateValCheck;
            } else {
                $endDate = \Carbon\Carbon::parse($scheduleStartDate . $scheduleStartTime)->addMinutes($elapsedTime - $utcDiff)->addDay()->isoFormat('YYYY-MM-DD');
            }
        } else {
            $endDate = \Carbon\Carbon::parse($scheduleStartDate . ' ' . $scheduleStartTime)->addMinutes($elapsedTime - $utcDiff)->isoFormat('YYYY-MM-DD');
        }

        $this->segmentEndDate = $endDate;
        $this->segmentEndTime = $scheduleEndTime;
        return $endDate;
    }

    public function getPriceDetails(array $itinPricingInformation, string $pre_booking_id, $airline_iata, $source)
    {
        $weight = "";
        $baggageTitleStr = "";
        $passengerInfoList = $this->getPassengerInfoList($itinPricingInformation['fare']['passengerInfoList']);
        $comission = static::getCommision(request()->all(), $airline_iata, $this->code_share);
        $this->commission = @$comission[0]['margin'] ?? null;
        $this->commission_type = @$comission[0]['margin_type'] ?? null;

        foreach ($passengerInfoList as $passengerInfoKey => $passengerInfo) {
            if (!isset($passengerInfo['baggageInformation'])) {
                continue;
            }

            $is_ndc = $source == 'SABRE_NDC' ? true : false;
            $finalData['baggageInformation'][$passengerInfoKey] = removeDuplicateBaggageItems($passengerInfo['baggageInformation'], $is_ndc);

            $count = 0;

            foreach ($passengerInfo['baggageInformation'] as $value) {
                if ($value['provision'] !== 'Checked baggage allowance') {
                    continue;
                }

                $weight = in_array($value['airlineCode'], ['SV', 'KU', 'CZ', 'X1', 'HY', 'TG']) ? '23KG' : ($value['airlineCode'] == "X1" ? "23KG" : @$value['weight'] . 'KG');
                $count++;

                if ($count === 1) {
                    $travelerCount = match ($passengerInfoKey) {
                        'ADT' => $this->request['traveler_count']['adult_count'],
                        'CNN' => $this->request['traveler_count']['child_count'],
                        'CHD' => $this->request['traveler_count']['child_count'],
                        default => $this->request['traveler_count']['infant_count'],
                    };

                    // if (isset($value['pieceCount'])) {
                    //     $baggageTitleStr .= '(' . $travelerCount . ' ' . $passengerInfoKey . ': ' . $value['pieceCount'] . 'PC' . ' ' . $weight . ' x ' . $travelerCount . ')';
                    // } else {
                    //     $baggageTitleStr .= '(' . $travelerCount . ' ' . $passengerInfoKey . ': ' . 1 . 'PC' . ' ' . $weight . ')';
                    // }

                    if(isset($value['pieceCount']) && $value['pieceCount'] > 0){
                        $baggageTitleStr .= '(' . $travelerCount . ' ' . $passengerInfoKey . ': ' . $value['pieceCount'] . 'PC' . ' ' . $weight . ')';
                    } elseif(isset($value['weight']) && $value['weight'] > 0){
                        $pc = array_key_exists('pieceCount', $value) && $value['pieceCount'] > 0 ? $value['pieceCount'] : 1;
                        $baggageTitleStr .= '(' . $travelerCount . ' ' . $passengerInfoKey . ': ' . $pc . 'PC' . ' ' . $weight . ')';
                    }else{
                        $baggageTitleStr .= '(' . $travelerCount . ' ' . $passengerInfoKey . ': ' . 'NILL BAGGAGE' . ')';
                    }
                }
            }
        }
        $finalData['bagage_info'] = trim($baggageTitleStr);
        $brandedInfo = null;
        $brandedInfofare = [];
        $bookingCode = [];
        $cabinCode = "";
        
        foreach ($itinPricingInformation['fare']['passengerInfoList'] as $key => $paxInfoValue) {
            if ($key == 0) {
                foreach (arrayConversion($paxInfoValue['passengerInfo']['fareComponents']) as $fare_value) {
                    foreach (arrayConversion($fare_value['segments']) as $value) {
                        if(!array_key_exists('segment',$value)){
                            continue;
                        }
                        array_push($bookingCode, $value['segment']['bookingCode']);
                    }
                }
            }
            $fareComponents = $paxInfoValue['passengerInfo']['fareComponents'][0];
            $cabinCode = $fareComponents['segments'][0]['segment']['bookingCode'];

            $fareComponent = $this->fareComponentDescs[$fareComponents['ref']];
            if (array_key_exists('brand', $fareComponent)) {
                $brandedInfo = $fareComponent['brand'];
            }

            foreach(arrayConversion($paxInfoValue['passengerInfo']['fareComponents']) as $fare_component){
                $fare_component = $this->fareComponentDescs[$fare_component['ref']];
                if (array_key_exists('fareBasisCode', $fare_component)) {
                    if (!is_array($brandedInfofare)) {
                        $brandedInfofare = [];
                    }
                    $brandedInfofare[] = $fare_component['fareBasisCode'];
                }
            }
        }
        
        $finalData['price'] = array_merge($this->getPrices($itinPricingInformation['fare']['totalFare'], $comission, extra_commision()), ["fare_break_down" => $this->getFareBreakDown($itinPricingInformation['fare']['passengerInfoList'], $comission)]);
        $finalData['rbd'] = $brandedInfo == null ? 'RBD-' . $cabinCode : $brandedInfo['brandName'] . ' - ' . $cabinCode;
        $finalData['is_refundable'] = $passengerInfoList['ADT']['nonRefundable'] ? false : true;

        if ($airline_iata == 'SV') {
            if($source == 'SABRE_NDC'){
                $finalData['is_refundable'] = true;
            }else{
                $finalData['is_refundable'] = false;
            }
        }
        $finalData['has_meal'] = true;
        $finalData['booking_id'] = $pre_booking_id;
        $finalData['booking_res_code'] = $bookingCode;
        return [$finalData, ['brand' => $brandedInfo, 'basefarecode' => $brandedInfofare]];
    }

    public function getPrices($price, $comission, $extra_commison): array
    {

        $base_fare = (array_key_exists('equivalentAmount', $price) ? $price['equivalentAmount'] : $price['baseFareAmount'] + $extra_commison);
        $tax = $price['totalTaxAmount'];

        $gross_amount = $base_fare + $tax;
        $discounted_amount = airline_discount($base_fare,$gross_amount,$comission);
        
        $total_amount = $gross_amount - ($discounted_amount > 0 ? -$discounted_amount : abs($discounted_amount));

        return [
            'base_fare' => numberFormat($base_fare),
            'tax' => numberFormat($tax),
            'gross_amount' => numberFormat($gross_amount),
            'currency' => @$price['equivalentCurrency'] ?? $price['currency'],
            'discount_psf' => numberFormat($discounted_amount),
            'total_amount' => numberFormat($total_amount),
        ];
    }

    public function getFareBreakDown($passengerFareInfoList, $comission)
    {
        $breakDownList = [];
        $passengerFareInfoList = arrayConversion($passengerFareInfoList);

        $break_down_commision = abs(extra_commision()/count($passengerFareInfoList))??0;
        foreach ($passengerFareInfoList as $key => $fareList) {
            $passengerType = $fareList['passengerInfo']['passengerType'];
            $passengerQuantity = $fareList['passengerInfo']['passengerNumber'] ?? 1;

            if (!isset($breakDownList[$passengerType])) {
                $breakDownList[$passengerType] = [
                    'quantity' => 0,
                    'prices' => $this->getPrices($fareList['passengerInfo']['passengerTotalFare'], $comission, $break_down_commision),
                ];
            }

            $breakDownList[$passengerType]['quantity'] += $passengerQuantity;
        }

        return $breakDownList;
    }

    public function getPassengerInfoList($ItinpassengersInfo)
    {
        $passengerInfoList = [];
        foreach ($ItinpassengersInfo as $ItinpassengerInfo) {

            $data['passengerType']      = $ItinpassengerInfo['passengerInfo']['passengerType'];
            $data['passengerNumber']    = $ItinpassengerInfo['passengerInfo']['passengerNumber'];
            $data['nonRefundable']      = array_key_exists('nonRefundable', $ItinpassengerInfo['passengerInfo']) ? $ItinpassengerInfo['passengerInfo']['nonRefundable'] : false;
            $data['fareComponents']     = $ItinpassengerInfo['passengerInfo']['fareComponents'];
            $data['taxes']              = array_key_exists('taxes', $ItinpassengerInfo['passengerInfo']) ? $this->getTaxs($ItinpassengerInfo['passengerInfo']['taxes']) : 'N/A';
            $data['taxSummaries']       = array_key_exists('taxSummaries', $ItinpassengerInfo['passengerInfo']) ? $this->getTaxSummaries($ItinpassengerInfo['passengerInfo']['taxSummaries']) : 'N/A';
            $data['currencyConversion'] = array_key_exists('currencyConversion', $ItinpassengerInfo['passengerInfo']) ? $ItinpassengerInfo['passengerInfo']['currencyConversion'] : 'N/A';
            $data['fareMessages']       = array_key_exists('fareMessages', $ItinpassengerInfo['passengerInfo']) ? $ItinpassengerInfo['passengerInfo']['fareMessages'] : 'N/A';
            $data['passengerTotalFare'] = $ItinpassengerInfo['passengerInfo']['passengerTotalFare'];
            $data['baggageInformation'] = array_key_exists('baggageInformation',$ItinpassengerInfo['passengerInfo']) ? $this->getBaggageInformation($ItinpassengerInfo['passengerInfo']['baggageInformation']) : [];
            $passengerInfoList[$ItinpassengerInfo['passengerInfo']['passengerType']] = $data;
        }
        return $passengerInfoList;
    }

    public function getTaxs($itniTaxes)
    {
        $taxes = [];
        foreach ($itniTaxes as $itinTax) {
            $tax = [];
            $tax['id'] = $this->taxDescs[$itinTax['ref']]['id'];
            $tax['code'] = $this->taxDescs[$itinTax['ref']]['code'];
            $tax['amount'] = $this->taxDescs[$itinTax['ref']]['amount'];
            $tax['currency'] = $this->taxDescs[$itinTax['ref']]['currency'];
            $tax['description'] = $this->taxDescs[$itinTax['ref']]['description'];
            $tax['publishedAmount'] = $this->taxDescs[$itinTax['ref']]['publishedAmount'];
            $tax['publishedCurrency'] = $this->taxDescs[$itinTax['ref']]['publishedCurrency'];
            $tax['station'] = $this->taxDescs[$itinTax['ref']]['station'];
            $tax['country'] = array_key_exists('country', $this->taxDescs[$itinTax['ref']]) ? $this->taxDescs[$itinTax['ref']]['country'] : '';
            $taxes[] = $tax;
        }

        return $taxes;
    }
    public function getTaxSummaries($itinTaxSummaries)
    {
        $taxSummaries = [];
        foreach ($itinTaxSummaries as $itinTaxSummary) {
            $tax = [];
            $tax = $this->taxSummaryDescs[$itinTaxSummary['ref']];
            $taxSummaries[] = $tax;
        }
        return $taxSummaries;
    }
    public function getBaggageInformation($itinBaggageInformation)
    {
        $infos = [];
        $itinBaggages = $itinBaggageInformation;
        foreach ($itinBaggages as $key => $itinBaggage) {
            if (array_key_exists('allowance', $itinBaggage)) {
                $info = [];
                $segment = ['provisionType' => $itinBaggage['provisionType'], 'provision' => $this->getProvision($itinBaggage['provisionType']), 'airlineCode' => $itinBaggageInformation[0]['airlineCode']];
                $info = $this->baggageAllowanceDescs[$itinBaggage['allowance']['ref']];
                $info = array_merge($info, $segment);
                $infos[] = $info;
            }
        }
        return $infos;
    }


    public function getProvision($provisionType)
    {
        $provision = '';
        switch ($provisionType) {
            case 'A':
                $provision = 'Checked baggage allowance';
                break;
            case 'B':
                $provision = 'Carry-on baggage allowance';
                break;
        }
        return $provision;
    }

    public function keyCheck($key, $arr, $ifFalse = 'N/A')
    {
        return array_key_exists($key, $arr) ? $arr[$key] : $ifFalse;
    }
    public function getUTCGMT($time)
    {
        $explodedIni = str_contains($time, "+") ? explode('+', $time) : explode('-', $time);
        if (count($explodedIni) < 2) {
            $exploded = [];
            $zExploded = explode('Z', $time);
            $exploded[] = $zExploded[0];
            $exploded[] = '00:00:00';
            return $exploded;
        } else {
            return $explodedIni;
        }
    }
    public function getDateFromResponse($key)
    {
        return $this->request['legs'][$key]['departure_date'];
    }
}
