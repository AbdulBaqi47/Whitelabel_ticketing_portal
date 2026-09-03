<?php

namespace App\Services\FlyDubaiService;

class Parser
{
    use \App\Traits\AirlinePromotion;
    private $legDetails = [];
    private $taxDetails = [];
    private $flightSegments = [];
    private $ServiceDetails = [];
    private $flight_prefix = 'flight_search';
    private $commission = null;
    private $commission_type = null;
    private $request = [];
    public function searchFlightParser($flight_result, $request)
    {
        $this->request = $request;
        $this->legDetails = leg_details_key_pair($flight_result['LegDetails']);
        $this->flightSegments = flight_segments_key_pair(arrayConversion($flight_result['FlightSegments']['FlightSegment']));
        if(array_key_exists('TaxDetails', $flight_result)){
            $this->taxDetails =  tax_details_key_pair(arrayConversion($flight_result['TaxDetails']['TaxDetail']));
        }
        $this->ServiceDetails = service_details_key_pair(arrayConversion($flight_result['ServiceDetails']['ServiceDetail']));

        $parsedFlights = [];
        foreach (groupFlights(arrayConversion($flight_result['SegmentDetails']['SegmentDetail']), $request) as $legs) {

            $leg_data = [];

            foreach ($legs as $leg) {

                if(!array_key_exists($leg['LFID'],$this->flightSegments)){
                    continue;
                }
                $sector = $this->flightSegments[$leg['LFID']];
                $segments = [];
                $actual_segments = [];

                $code_share = false;
                foreach ($sector['FlightLegDetails']['FlightLegDetail'] as $key => $value) {
                    $pfidKey = $value['PFID'] . '_' . date('YmdHi', strtotime($value['DepartureDate']));
                    $segment = $this->legDetails[$pfidKey] ?? [];
                    $segments[$key] = $this->manageSegment($segment, $key, $sector['FlightLegDetails']['FlightLegDetail']);

                    $code_share = $segments[$key]['code_share'];

                    $actual_segments[] = $segment;
                }

                $fares = [];

                $this->commission = null;
                $this->commission_type = null;

                foreach ($sector['FareTypes']['FareType'] as $fareType) {

                    $preBookingId = random_id('FB');

                    $fare = $this->manageFare($fareType, $this->taxDetails, $preBookingId, $code_share);

                    $combineData = [
                        'leg' => $leg,
                        'actual_segments' => $actual_segments,
                        'segments' => $segments,
                        'request' => $request,
                        'fareType' => $fareType,
                        'price' => $fare,
                        'tax_details' => $this->taxDetails,
                        'API' => 'FLYDUBAI_API',
                        'commission'=>$this->commission,
                        'commission_type'=>$this->commission_type
                    ];

                    set_data($preBookingId, $this->flight_prefix, 660, $combineData);

                    $fares[] = $fare;
                }

                $sectorData['journey_duration'] = $leg['FlightTime'];

                $sectorData['flight_number'] = explode("/", $leg['OperatingFlightNum']);
                $sectorData['sector'] = [$leg['Origin'], $leg['Destination']];

                $sectorData['fare_option'] = $fares;
                $sectorData['segments'] = $segments;
                $leg_data[] = $sectorData;

            }
            $parsedFlights[] = $leg_data;
        }
        $searchAvailability['airline'] =  fetch_airline('FZ');
        $searchAvailability['legs'] = $parsedFlights;
        $searchAvailability['name'] = 'FLYDUBAI';
        $searchAvailability['provider'] = 'FLYDUBAI_API';
        $flightParsedData[] = $searchAvailability;

        return $flightParsedData;
    }

    function manageSegment($flightSegment, $key, $flight_details): array
    {

        $segment['layover_waited_time'] = 0;

        if($key > 0){
            $value = $flight_details[$key-1];
            $pfidKey = $value['PFID'] . '_' . date('YmdHi', strtotime($value['DepartureDate']));
            $last_segment = $this->legDetails[$pfidKey];
            $segment['layover_waited_time'] = \Carbon\Carbon::parse($last_segment['ArrivalDate'])->diffInMinutes(\Carbon\Carbon::parse($flightSegment['DepartureDate']));
        }

        $segment['arrival_datetime'] = $flightSegment['ArrivalDate'];
        $segment['departure_datetime'] = $flightSegment['DepartureDate'];
        $segment['code_share'] = $flightSegment['OperatingCarrier'] !== 'FZ';
        $segment['duration_minutes'] = $flightSegment['FlightTime'];
        $segment['flight_number'] = $flightSegment['FlightNum'];
        $segment['marketing_code'] = $flightSegment['MarketingCarrier'];
        $segment['marketing_flight_number'] = $flightSegment['MarketingFlightNum'];
        $segment['operating_code'] = $flightSegment['OperatingCarrier'];
        $segment['operating_flight_number'] = $flightSegment['FlightNum'];
        $segment['sector'] = is_domastic($flightSegment['Origin'], $flightSegment['Destination']) ? 'DOMESTIC' : 'INTERNATIONAL';

        $segment['destination'] = fetch_airport($flightSegment['Destination']);
        $segment['destination']['terminal'] = $flightSegment['ToTerminal'];
        $segment['origin'] = fetch_airport($flightSegment['Origin']);
        $segment['origin']['terminal'] = $flightSegment['FromTerminal'];

        $airline = \App\Models\Airline::where('iata_code', 'FZ')
            ->select(['iata_code', 'thumbnail', 'name'])->first()->toArray();
        $segment['marketing_airline'] = $airline;
        $segment['operating_airline'] = $airline;
        return $segment;
    }

    public function manageFare(array $fareInfos, array $taxDetailMap, ?string $pre_booking_id, bool $code_share): array
    {
        $fareInformation = [
            'baggageInformation' => [],
            'bagage_info' => [],
            'price' => [],
            'rbd' => '',
            'booking_id' => $pre_booking_id,
            'has_meal' => false,
            'has_seat_selection' => false,
            're_booking' => '',
            'cancellation' => false,
            'entertainment' => '',
            'seats' => 0,
            'connector' => 'fly_dubai'
        ];

        $fareBreakDownData = [];
        $baggageInformation = [];
        $baggageTitleStr = "";
        $totalBaseFare = 0;
        $totalTax = 0;
        $totalFare = 0;

        $comission = $this->getCommision(request()->all(), 'FZ', $code_share);
        $this->commission = @$comission[0]['margin'] ?? null;
        $this->commission_type = @$comission[0]['margin_type'] ?? null;

        $break_down_commision = abs(extra_commision()/count(arrayConversion($fareInfos['FareInfos']['FareInfo'])))??0;

        foreach (arrayConversion($fareInfos['FareInfos']['FareInfo']) as $fareInfo) {

            $pax = $fareInfo['Pax'][0];

            $fareInformation['seats'] = $pax['SeatsAvailable'];

            $quantity = $pax['PaxCount'];
            $passengerCode = $pax['PTCID'] == 1 ? 'ADT' : ($pax['PTCID'] == 5 ? 'INF' : 'CHD');

            $baseFare = ($pax['DisplayFareAmt'] * $quantity) + $break_down_commision;
            $taxAmount = $pax['DisplayTaxSum'] * $quantity;
            $grossFare = $pax['BaseFareAmtInclTax'] * $quantity;

            $totalBaseFare += $baseFare;
            $totalTax += $taxAmount;
            $totalFare += $grossFare;

            // Discount Calculation
            $discountedAmount = airline_discount($baseFare,$grossFare, $comission, 'FZ');
            $adjustedTotal = $grossFare - ($discountedAmount > 0 ? -$discountedAmount : abs($discountedAmount));

            $fareBreakDown = [
                'quantity'      => $quantity,
                'base_fare'     => numberFormat($baseFare),
                'tax'           => numberFormat($taxAmount),
                'gross_amount'  => numberFormat($grossFare),
                'discount_psf'  => numberFormat($discountedAmount),
                'currency'      => 'PKR',
                'total_amount'  => numberFormat($adjustedTotal)
            ];

            $checkedBaggage = null;             

            foreach ($pax['ApplicableTaxDetails']['ApplicableTaxDetail'] as $applicableTax) {
                $taxId = $applicableTax['TaxID'];

                if (!isset($taxDetailMap[$taxId])) {
                    continue;
                }

                $taxDetail = $taxDetailMap[$taxId];
                if($taxDetail['TaxDesc'] == "Standard meal"){
                    $fareInformation['has_meal'] = true;
                }
                
                if($taxDetail['TaxDesc'] = "Included seat"){
                    $fareInformation['has_seat_selection'] = true;
                }

                if($taxDetail['TaxDesc'] = "In flight entertainment Business"){ 
                    $fareInformation['entertainment'] = $taxDetail['TaxDesc'];
                }

                if(in_array($taxDetail['CodeType'], array_keys(get_bagage_with_code()))){
                    $checkedBaggage = get_bagage_with_code()[$taxDetail['CodeType']];
                }
            }
            $checkedBaggage = $checkedBaggage == null ? 0 : $checkedBaggage;

            $bagageBreakDown = [
                [
                    "weight" =>  $passengerCode == 'INF' ? '10': $checkedBaggage,
                    "unit" => "kg",
                    "provisionType" => "A",
                    "provision" => "Checked baggage allowance",
                ],
                [
                    "weight" => $passengerCode == 'INF' ? 5 : ($fareInfos['FareTypeName'] == 'Business' ? 14 : 7),
                    "unit" => "kg",
                    "provisionType" => "B",
                    "provision" => "Carry-on baggage allowance",
                ]
            ];

            $fareBreakDownData[paxType()[$passengerCode]] = $fareBreakDown;
            $baggageInformation[$passengerCode] = $bagageBreakDown;
            $baggageTitleStr .= '(' . $quantity . ' ' . $passengerCode . ': ' . $quantity . 'PC' . ' ' . $checkedBaggage . 'kg)';
        }

        $fareInformation['bagage_info'] = trim($baggageTitleStr);
        $fareInformation['baggageInformation'] = $baggageInformation;

        // Final overall fare calculation
        $grossAmount = $totalFare;
        $totalDiscount = airline_discount($totalBaseFare,$grossAmount, $comission, 'FZ');
        $finalTotal = $grossAmount - ($totalDiscount > 0 ? -$totalDiscount : abs($totalDiscount));

        $fareInformation['price'] = [
            'fare_break_down' => $fareBreakDownData,
            'base_fare'       => numberFormat($totalBaseFare),
            'tax'             => numberFormat($totalTax),
            'gross_amount'    => numberFormat($grossAmount),
            'discount_psf'    => numberFormat($totalDiscount),
            'currency'        => 'PKR',
            'total_amount'    => numberFormat($finalTotal)
        ];
        $fareInformation['re_booking'] = re_booking()[$fareInfos['FareTypeName']];
        $fareInformation['cancellation'] = cancellation()[$fareInfos['FareTypeName']];
        $fareInformation['rbd'] = $fareInfos['FareTypeName'];
        return $fareInformation;
    }
}
