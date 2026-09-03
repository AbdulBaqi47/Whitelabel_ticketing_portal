<?php

namespace App\Services\AirBlueService;

class Parser
{
    private $flight_prefix = 'flight_search';
    use \App\Traits\AirlinePromotion;
    private $commission = [];
    private $commission_type = [];
    public function parseAirLowFareResponse($itineraries, $request)
    {
        $flightParsedData = [];
        $searchAvailability = [];
        $legs = [];
        foreach ($itineraries as $itinerary) {

            $originDestinationRefNumber = $itinerary['OriginDestinationRefNumber'];
            $segments = [];
            $journey_duration = 0;
            $flight_number = [];
            $sector = [];

            foreach (arrayConversion($itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']) as $key => $flight_segment) {
                $segment = $this->manageSegment($flight_segment['FlightSegment']);
                $journey_duration += $segment['duration_minutes'];
                array_push($flight_number, 'PA-' . $segment['operating_flight_number']);
                $segments[] = $segment;

                if ($key == 0) {
                    array_push($sector, $flight_segment['FlightSegment']['DepartureAirport']['LocationCode']);
                    array_push($sector, $flight_segment['FlightSegment']['ArrivalAirport']['LocationCode']);
                }
            }

            $fares = [];

            $this->commission = null;
            $this->commission_type = null;

            foreach (sort_flight_options($itinerary['AirItineraryPricingInfo']) as $fareKey => $fareValue) {

                $preBookingId = random_id('FB');

                if(empty($fareValue['PTC_FareBreakdowns'])){
                    continue;
                }

                $fares[$fareKey] = $this->manageFare($fareValue, $preBookingId);

                $combineData = [
                    'fare_info_list' => $fares[$fareKey],
                    'fare_info'      => $fareValue,
                    'segments'       => $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'],
                    'segment_data'   => $segments,
                    'request'        => $request,
                    'API'            => 'AIRBLUE_API',
                    'airline'        => 'PA',
                    'commission'     =>$this->commission,
                    'commission_type'=>$this->commission_type
                ];
                set_data($preBookingId, $this->flight_prefix, 660, $combineData);
            }

            $legData = [
                'segments'          => $segments,
                'sector'            => $sector,
                'flight_number'     => $flight_number,
                'journey_duration'  => $journey_duration,
                'fare_option'       => $fares,
            ];

            if (isset($legs[$originDestinationRefNumber])) {
                $legs[$originDestinationRefNumber][] = $legData;
            } else {
                $legs[$originDestinationRefNumber] = [$legData];
            }
        }

        $searchAvailability['airline'] = \App\Models\Airline::where('iata_code', 'PA')->select('name', 'thumbnail', 'iata_code')->first();
        $searchAvailability['fare_option'] = summarizeFlightPrices($legs);
        $searchAvailability['legs'] = $legs;
        $searchAvailability['name'] = 'AIRBLUE';
        $searchAvailability['provider'] = 'AIRBLUE_API';
        $flightParsedData[] = $searchAvailability;

        return $flightParsedData;
    }

    public function manageFare(array $fareComponentList, ?string $pre_booking_id): array
    {
        $fareInformation = [
            'baggageInformation'    => [],
            'bagage_info'           => [],
            'price'                 => [],
            'rbd'                   => '',
            'is_refundable'         => true,
            'booking_id'            => $pre_booking_id,
            'has_meal'              => true,
            'booking_res_code'      => '',
            'additional_serivces'   => [],
        ];

        $fareBreakDownData = [];
        $baggageInformation = [];
        $baggageTitleStr = "";

        $extra_commision = extra_commision();
        $comission = $this->getCommision(request()->all(), 'PA', false);
        $this->commission = @$comission[0]['margin'] ?? null;
        $this->commission_type = @$comission[0]['margin_type'] ?? null;
        $ptc_fare_breakdown = arrayConversion($fareComponentList['PTC_FareBreakdowns']['PTC_FareBreakdown']);
        $break_down_commision = abs($extra_commision/count($ptc_fare_breakdown)) ?? 0;
        foreach ($ptc_fare_breakdown as $paxFareInfo) {

            $passengerTypeData = $paxFareInfo['PassengerTypeQuantity'];
            $pricingInfo       = $paxFareInfo['PassengerFare'];
            $quantity          = $passengerTypeData['Quantity'];


            $totalTax = 0;
            $surcharges = 0;
            if(array_key_exists('Taxes', $pricingInfo)){
                $totalTax   = $pricingInfo['Taxes']['Amount'] * $quantity;
            }

            if(array_key_exists('Fees', $pricingInfo)){
                $surcharges = @$pricingInfo['Fees']['Amount'] * $quantity;
            }
            
            $baseFare = ($pricingInfo['BaseFare']['Amount'] * $quantity) + $surcharges + $break_down_commision; 
            $grossFare  = $baseFare + $totalTax;

            // Discount Calculation
            $discountedAmount = airline_discount($baseFare,$grossFare, $comission, 'PA');
            $adjustedTotal = $grossFare - ($discountedAmount > 0 ? -$discountedAmount : abs($discountedAmount));

            $fareBreakDown = [
                'quantity'      => $quantity,
                'base_fare'     => numberFormat($baseFare),
                'tax'           => numberFormat($totalTax),
                'gross_amount'  => numberFormat($grossFare),
                'discount_psf'  => numberFormat($discountedAmount),
                'currency'      => 'PKR',
                'total_amount'  => numberFormat($adjustedTotal)
            ];

            $fareInfoList = @$paxFareInfo['FareInfo'][1]['PassengerFare'];

            $bagageBreakDown = [
                [
                    "weight"        => $fareInfoList['FareBaggageAllowance']['UnitOfMeasureQuantity'] ?? 0,
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

            $passengerCode = $passengerTypeData['Code'];
            $fareBreakDownData[paxType()[$passengerCode]] = $fareBreakDown;
            $baggageInformation[$passengerCode] = $bagageBreakDown;
            $weight = @$fareInfoList['FareBaggageAllowance']['UnitOfMeasureQuantity'] ?? 0 . 'KG';
            $baggageTitleStr .= '(' . $quantity . ' ' . $passengerCode . ': ' . 1 . 'PC' . ' ' . $weight . ' x ' . $quantity . ')';
        }

        $fareInformation['bagage_info'] = trim($baggageTitleStr);

        // Pricing Overview
        $pricingOverview = $fareComponentList['ItinTotalFare'];
        $totalTaxValue   = $pricingOverview['Taxes']['Amount'];

        $fareInformation['baggageInformation'] = $baggageInformation;

        $AdditionalBrandFeatures['EV'] = [
            "Seat Selection: Allowed with Higher Fee",
            "BlueMiles Rewards: 50% Miles",
            "Refunds & Exchanges: Allowed with Higher Fee"
        ];
        $AdditionalBrandFeatures['EF'] = [
            "Seat Selection: Allowed with Standard Fee",
            "BlueMiles Rewards: 100% Miles",
            "Refunds & Exchanges: Allowed with Standard Fee"
        ];
        $AdditionalBrandFeatures['EX'] = [
            "Seat Selection: Allowed with Lower Fee",
            "BlueMiles Rewards: 200% Miles",
            "Refunds & Exchanges: Allowed with Lower Fee"
        ];

        $BrandID = substr(arrayConversion($paxFareInfo['FareInfo'])[0]['FareInfo']['FareBasisCode'], 0, 2);

        $fareInformation['additional_serivces'] = $AdditionalBrandFeatures[$BrandID];


        $summarize = $pricingOverview['BaseFare']['Amount'] + (array_key_exists('Fees', $pricingOverview) ?  $pricingOverview['Fees']['Amount'] : 0) + $extra_commision;

        // Final overall fare calculation
        $grossAmount = $summarize + $totalTaxValue;
        $totalDiscount = airline_discount($summarize,$grossAmount, $comission, 'PA');
        $finalTotal = $grossAmount - ($totalDiscount > 0 ? -$totalDiscount : abs($totalDiscount));

        $fareInformation['price'] = [
            'fare_break_down' => $fareBreakDownData,
            'base_fare'       => numberFormat($summarize),
            'tax'             => numberFormat($totalTax),
            'gross_amount'    => numberFormat($grossAmount),
            'discount_psf'    => numberFormat($totalDiscount),
            'currency'        => 'PKR',
            'total_amount'    => numberFormat($finalTotal)
        ];

        $fareInformation['rbd'] =  strpos($BrandID, 'EF') !== false ? 'Flexi' : (strpos($BrandID, 'EX') !== false ? 'Xtra' : 'Value');
        $fareInformation['booking_res_code'] = arrayConversion($paxFareInfo['FareInfo'])[0]['FareInfo']['FareBasisCode'];
        return $fareInformation;
    }

    function manageSegment($flightSegment): array
    {
        $segment['arrival_datetime']        = $flightSegment['ArrivalDateTime'];
        $segment['departure_datetime']      = $flightSegment['DepartureDateTime'];
        $segment['code_share']              = false;
        $segment['duration_minutes']        = flight_duration($flightSegment['DepartureDateTime'], $flightSegment['ArrivalDateTime'], $flightSegment['DepartureAirport']['LocationCode'], $flightSegment['ArrivalAirport']['LocationCode']);//time_calculation($flightSegment['DepartureDateTime'], $flightSegment['ArrivalDateTime']);
        $segment['flight_number']           = 'PA-' . $flightSegment['FlightNumber'];
        $segment['marketing_code']          = 'PA';
        $segment['marketing_flight_number'] = $flightSegment['FlightNumber'];
        $segment['operating_code']          = $flightSegment['OperatingAirline']['Code'];
        $segment['operating_flight_number'] = $flightSegment['FlightNumber'];;
        $segment['sector']                  = is_domastic($flightSegment['DepartureAirport']['LocationCode'], $flightSegment['ArrivalAirport']['LocationCode']) ? 'DOMESTIC' : 'INTERNATIONAL';
        $segment['destination']             = \App\Models\Airport::where('iata_code', $flightSegment['ArrivalAirport']['LocationCode'])
            ->select(['name', 'municipality', 'iata_code', 'country'])->first()->toArray();
        $segment['origin']                  = \App\Models\Airport::where('iata_code', $flightSegment['DepartureAirport']['LocationCode'])
            ->select(['name', 'municipality', 'iata_code', 'country'])->first()->toArray();
        $airline = \App\Models\Airline::where('iata_code', 'PA')
            ->select(['iata_code', 'thumbnail', 'name'])->first()->toArray();
        $segment['marketing_airline']       = $airline;
        $segment['operating_airline']       = $airline;
        return $segment;
    }

    public function seatMapParser($response){
        
        $seatMapResponse = $response['Body']['AirSeatMapResponse']['AirSeatMapResult']['SeatMapResponses']['SeatMapResponse'];
        $flight_seats_detail = [];
        foreach (arrayConversion($seatMapResponse) as $value) {

            $seat_details = [];
            foreach (arrayConversion($value['SeatMapDetails']['CabinClass']) as $cabin_classes) {

                $seat_class_details = [];
                foreach ($cabin_classes['RowInfo'] as $air_row) {
                    $row_seats = [];
                    foreach ($air_row['SeatInfo'] as $seat) {

                        if($seat['Summary']['SeatNumber'] === " "){
                            continue;
                        }
                        $row_seats[] = [
                            'seat_number'       => $seat['Summary']['SeatNumber'],
                            'price'             => array_key_exists('Service', $seat) ? numberFormat($seat['Service']['Fee']['Amount']) : '0.00',
                            'seat_availability' => $seat['Summary']['AvailableInd'],
                            'seat_code'         => $air_row['RowNumber'] . '' . $seat['Summary']['SeatNumber']
                        ];
                    }

                    $seat_class_details[] = [
                        'row_number' => $air_row['RowNumber'],
                        'seats'      => $row_seats
                    ];
                }

                $seat_details[array_flip(SabreCabin())[$value['FlightSegmentInfo']['CabinClass']]] = $seat_class_details;
            }

            $flight_detail = $value['FlightSegmentInfo'];

            $flight_seats_detail[] = [
                'flight_detail' => $flight_detail,
                'sector'        => $flight_detail['DepartureAirport']['LocationCode'] .'-'. $flight_detail['ArrivalAirport']['LocationCode'],
                'seats'         => $seat_details,
            ];
        }

        return $flight_seats_detail;
    }

    public function ancillaryParser($response){
        $ancillaryItemResponse = $response['Body']['AirAncillaryItemsResponse']['AirAncillaryItemsResult']['AncillaryItemResponses']['AncillaryItemResponse'];

        $flight_accillary_detail = [];

        foreach(arrayConversion($ancillaryItemResponse) as $value){

            $ancillaryItemSet = $value['AncillaryItemSets']['AncillaryItemSet'];

            $baggage_details = $ancillaryItemSet[0];
            $wheel_chair_details = $ancillaryItemSet[1];

            $baggage_data = [];
            $wheel_chair_data = [];

            foreach(arrayConversion($baggage_details['AncillaryItems']['AncillaryItem']) as $baggage_detail){

                if($baggage_detail['Available']){
                    $baggage_data[] = $baggage_detail;
                }
            }

            foreach(arrayConversion($wheel_chair_details['AncillaryItems']['AncillaryItem']) as $wheel_chair_detail){

                if($wheel_chair_detail['Available']){
                    $wheel_chair_data[] = $wheel_chair_detail;
                }
            }

            $flight_detail = $value['FlightSegmentInfo'];

            $flight_accillary_detail[] = [
                'flight_detail'     => $flight_detail,
                'sector'            => $flight_detail['DepartureAirport']['LocationCode'] .'-'. $flight_detail['ArrivalAirport']['LocationCode'],
                'baggage'           => $baggage_data,
                'wheel_chair'       => $wheel_chair_data
            ];
        }

        return $flight_accillary_detail;
    }
}
