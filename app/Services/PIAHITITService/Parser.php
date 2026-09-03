<?php

namespace App\Services\PIAHITITService;
class Parser
{
    use \App\Traits\AirlinePromotion;
    private $commission = null;
    private $commission_type = null;
    private $request;
    private $flight_prefix = 'flight_search';
    private $last_arrival;
    public function parseLFSResponse($data, $request)
    {
        $this->request = $request;
        $raw_response = $data['Availability']['availabilityResultList']['availabilityRouteList'];
        $flights = [];

        foreach(arrayConversion($raw_response) as $key => $value){
            $flights[$key] = $value;
        }

        return $this->responseParser($flights);
    }

    public function responseParser($flights)
    {
        $flightParsedData = [];
        
        foreach ($flights as $key => $flight) {

            $flight = $flight['availabilityByDateList'];

            $legs = [];
            foreach(arrayConversion($flight['originDestinationOptionList']) as $org_key => $orign_destination){
                
                $searchAvailability = [];

                $flight_segments = arrayConversion($orign_destination['fareComponentGroupList']['boundList']['availFlightSegmentList']);
                $fare_component_list = arrayConversion($orign_destination['fareComponentGroupList']['fareComponentList']);
                if (empty($fare_component_list)) {
                    continue;
                }
                $fares = [];
                $segmentData = [];
                
                $code_share = false;                
                $segments = [];
                $journey_duration = 0;
                $this->last_arrival = null;
                $sector = [];
                $flight_number = [];
               
                $totalSegments = count($flight_segments);

                if($totalSegments > 1 && chk_codeshare_not_operating($flight_segments)){
                    continue;
                }

                foreach ($flight_segments as $flightSegmentKey => $flightSegmentValue) {

                    $segments[$flightSegmentKey] = $this->manageSegment($flightSegmentValue['flightSegment']);
                    $code_share = $segments[$flightSegmentKey]['code_share'];
                
                    $this->last_arrival = \Carbon\Carbon::parse($flightSegmentValue['flightSegment']['arrivalDateTime']);

                    $journey_duration += $segments[$flightSegmentKey]['duration_minutes'];

                    array_push($segmentData, $flightSegmentValue['flightSegment']);

                    if (array_key_exists($flightSegmentKey + 1, $flight_segments)) {
                        $journey_duration += $this->calculateLayourTime($flightSegmentValue['flightSegment']['arrivalDateTime'], $flight_segments[$flightSegmentKey + 1]['flightSegment']['departureDateTime']);
                    }

                    if ($flightSegmentKey == 0) {
                        $firstDeparture = $flightSegmentValue['flightSegment']['departureAirport']['locationCode'];

                        array_push($sector, $firstDeparture);
                    }

                    if ($flightSegmentKey == $totalSegments - 1) {
                        $lastArrival = $flightSegmentValue['flightSegment']['arrivalAirport']['locationCode'];
                        array_push($sector, $lastArrival);
                    }

                    $o_flight_number = $segments[$flightSegmentKey]['operating_code'] .'-'. $segments[$flightSegmentKey]['operating_flight_number'];
                    array_push($flight_number, $o_flight_number);
                }
                $this->commission = null;
                $this->commission_type = null;
             
                foreach ($fare_component_list as $fareKey => $fareValue) {
    
                    $preBookingId = random_id('FB');
                    $fareInfoList = arrayConversion($fareValue['passengerFareInfoList'])[0]['fareInfoList'];
                    $fares[$fareKey] = $this->manageFare($fareValue, $preBookingId, $code_share);
    
                    $combineData = [
                        'fare_info_list' => $fareInfoList,
                        'fare_info' => $fares[$fareKey],
                        'segments' => $segments,
                        'segment_data' => $segmentData,
                        'request' => $this->request,
                        'API' => 'PIA_HITIT',
                        'airline' => 'PK',
                        'commission'=>$this->commission,
                        'commission_type'=>$this->commission_type,
                    ];
                    set_data($preBookingId, $this->flight_prefix, 660, $combineData);
                }

                $searchAvailability['fare_option'] = $fares;
                $searchAvailability['segments'] = $segments;
                $searchAvailability['sector'] = $sector;
                $searchAvailability['flight_number'] = $flight_number;
                $searchAvailability['journey_duration'] = $journey_duration;
                $legs[] = $searchAvailability;
            }
            $flightParsedData['legs'][$key] = $legs;
        }

        $flightParsedData['name'] = 'PIA';
        $flightParsedData['provider'] = 'PIA_HITIT';
        $flightParsedData['airline'] = \App\Models\Airline::where('iata_code', 'PK')->select('name', 'thumbnail', 'iata_code')->first();
        $new[] = $flightParsedData;
        return $new;
    }

    public function calculateLayourTime(?string $current_arrival, ?string $next_departure)
    {
        return \Carbon\Carbon::parse($current_arrival)->diffInMinutes(\Carbon\Carbon::parse($next_departure));
    }
    public function manageFare(array $fareComponentList, ?string $pre_booking_id, $code_share): array
    {
        $fareInformation = [
            'baggageInformation' => [],
            'bagage_info'        => '',
            'price'              => [],
            'rbd'                => '',
            'is_refundable'      => true,
            'booking_id'         => $pre_booking_id,
            'has_meal'           => true,
            'booking_res_code'   => ''
        ];

        $fareBreakDownData = [];
        $baggageInformation = [];
        $overallBaseFare = 0;
        $overallSurcharge = 0;
        $totalTaxValue = 0;
        $baggageTitleStr = '';

        $comission = $this->getCommision(request()->all(), 'PK', $code_share);
        $this->commission = @$comission[0]['margin'] ?? null;
        $this->commission_type = @$comission[0]['margin_type'] ?? null;
        $break_down_commision = abs(extra_commision()/count(arrayConversion($fareComponentList['passengerFareInfoList'])))??0;

        foreach (arrayConversion($fareComponentList['passengerFareInfoList']) as $paxFareInfo) {

            $passengerTypeData = $paxFareInfo['passengerTypeQuantity'];
            $pricingInfo = $paxFareInfo['pricingInfo'];
            $quantity = $passengerTypeData['quantity'];

            $baseFare = ($pricingInfo['equivBaseFare']['value'] * $quantity) + $break_down_commision;
            $tax = $pricingInfo['taxes']['totalAmount']['value'] * $quantity;
            $surcharge = $pricingInfo['surcharges']['totalAmount']['value'] * $quantity;
            $grossFare = $baseFare + $tax + $surcharge;

            // Totals for global summary
            $overallBaseFare += $baseFare;
            $totalTaxValue += $tax;
            $overallSurcharge += $surcharge;

            // Discount Calculation
            $discountedAmount = airline_discount($baseFare,$grossFare, $comission);
            $adjustedTotal = $grossFare - ($discountedAmount > 0 ? -$discountedAmount : abs($discountedAmount));

            $fareBreakDown = [
                'quantity'      => $quantity,
                'base_fare'     => numberFormat($baseFare),
                'tax'           => numberFormat($tax),
                'pia_fees'      => numberFormat($surcharge),
                'gross_amount'  => numberFormat($grossFare),
                'discount_psf'  => numberFormat($discountedAmount),
                'currency'      => 'PKR',
                'total_amount'  => numberFormat($adjustedTotal)
            ];

            // Baggage info
            $fareInfoList = arrayConversion($paxFareInfo['fareInfoList'])[0];
            $passengerCode = $passengerTypeData['passengerType']['code'];

            $pieces = $fareInfoList['fareBaggageAllowance']['maxAllowedPieces'] ?? 1;
            $weight = $fareInfoList['fareBaggageAllowance']['maxAllowedWeight']['weight']
                ?? ($fareInfoList['fareBaggageAllowance']['pieceDefinitions'] ?? 0);

            $bagageBreakDown = [
                [
                    "weight"        => $weight,
                    'pieces'        => $pieces,
                    "unit"          => "kg",
                    "provisionType" => "A",
                    "provision"     => "Checked baggage allowance",
                ],
                [
                    "weight"        => 7,
                    "unit"          => "kg",
                    "provisionType" => "B",
                    "provision"     => "Carry-on baggage allowance",
                ]
            ];
            $pieces = $pieces == 0 ? 1 : $pieces;
            $fareBreakDownData[paxType()[$passengerCode]] = $fareBreakDown;
            $baggageInformation[$passengerCode] = $bagageBreakDown;
            $unit = 'kg';
            $baggageTitleStr .= "($quantity $passengerCode: $pieces PC $weight$unit x $quantity) ";
        }

        // Final overall fare calculation
        $grossAmount = $overallBaseFare + $totalTaxValue + $overallSurcharge;
        $totalDiscount = airline_discount($overallBaseFare,$grossAmount, $comission);

        $finalTotal = $grossAmount - ($totalDiscount > 0 ? -$totalDiscount : abs($totalDiscount));

        $fareInformation['bagage_info'] = trim($baggageTitleStr);
        $fareInformation['baggageInformation'] = $baggageInformation;
        $fareInformation['price'] = [
            'fare_break_down' => $fareBreakDownData,
            'base_fare'       => numberFormat($overallBaseFare),
            'tax'             => numberFormat($totalTaxValue),
            'pia_fees'        => numberFormat($overallSurcharge),
            'gross_amount'    => numberFormat($grossAmount),
            'discount_psf'    => numberFormat($totalDiscount),
            'currency'        => 'PKR',
            'total_amount'    => numberFormat($finalTotal)
        ];
        $fareInformation['rbd'] = $fareInfoList['fareGroupName'] . ' | ' . $fareInfoList['resBookDesigCode'];
        $fareInformation['booking_res_code'] = $fareInfoList['resBookDesigCode'];

        return $fareInformation;
    }


    function manageSegment($flightSegment): array
    {
        $segment['arrival_datetime'] = $flightSegment['arrivalDateTime'];
        $segment['departure_datetime'] = \Carbon\Carbon::parse($flightSegment['departureDateTime'])->format('Y-m-d H:i:s');
        
        // $segment['duration_minutes'] = $this->convertRawDurationToMinutes($flightSegment['journeyDuration']);
        $segment['duration_minutes'] = flight_duration($flightSegment['departureDateTime'], $flightSegment['arrivalDateTime'], $flightSegment['departureAirport']['locationCode'], $flightSegment['arrivalAirport']['locationCode']);
        $segment['flight_number'] = 'PK-' . $flightSegment['flightNumber'];
        $segment['marketing_code'] = 'PK';
        $segment['marketing_flight_number'] = $flightSegment['flightNumber'];
        $segment['operating_code'] = array_key_exists('operatingAirline', $flightSegment) ? $flightSegment['operatingAirline']['code'] : 'PK';
        $segment['operating_flight_number'] = array_key_exists('operatingAirline', $flightSegment) ? $flightSegment['operatingAirline']['flightNumber'] : $flightSegment['flightNumber'];
        $segment['sector'] = $flightSegment['sector'];
        $segment['layover_waited_time'] = is_null($this->last_arrival) || empty($this->last_arrival)  ? 0 : $this->last_arrival->diffInMinutes(\Carbon\Carbon::parse($flightSegment['departureDateTime']));
        $segment['origin'] = ['terminal' => @$flightSegment['departureAirport']['terminal'] ?? '' ,...fetch_airport($flightSegment['departureAirport']['locationCode'])];
        $segment['destination'] = [ 'terminal' => @$flightSegment['arrivalAirport']['terminal']  ?? '' , ...fetch_airport($flightSegment['arrivalAirport']['locationCode'])];
        $airline = \App\Models\Airline::where('iata_code', 'PK')
            ->select(['iata_code', 'thumbnail', 'name'])->first()->toArray();
        $segment['marketing_airline'] = $airline;
        $segment['operating_airline'] = array_key_exists('operatingAirline', $flightSegment) ? \App\Models\Airline::where('iata_code', $flightSegment['operatingAirline']['code'])
            ->select(['iata_code', 'thumbnail', 'name'])->first()->toArray() : $airline;

        $segment['code_share'] = $segment['operating_code'] != 'PK';

        return $segment;
    }

    public function getDuration($journeyDuration, $layoverDateTimes, $flightSegmentKey = 0)
    {
        $minutes = 0;
        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $journeyDuration[$flightSegmentKey], $matches)) {
            if (@$matches[1]) {
                $minutes += $matches[1] * 60;
            }
            if (@$matches[2]) {
                $minutes += $matches[2];
            }
        }

        if (count($layoverDateTimes) > 0) {
            for ($i = 0; $i < count($layoverDateTimes); $i++) {
                $arrivalTime = \Carbon\Carbon::parse($layoverDateTimes[$i][0]);
                $departureTime = \Carbon\Carbon::parse($layoverDateTimes[$i][1]);
                $minutes += $arrivalTime->diffInMinutes($departureTime);
            }
        }
        return floor($minutes / 60) . "hr " . $minutes % 60 . "mins";
    }

    // function convertRawDurationToMinutes($journeyDuration)
    // {
    //     $hours = 0;
    //     $minutes = 0;
    //     if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $journeyDuration, $matches)) {
    //         $hours = $matches[1];
    //         $minutes = !empty($matches[2]) ? $matches[2] : "00";
    //     }
    //     return ($hours * 60) + $minutes;
    // }
}
