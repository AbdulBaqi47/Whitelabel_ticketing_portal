<?php

namespace App\Services\FlyJinnahService;

class Parser
{
    use \App\Traits\AirlinePromotion;
    protected $response;
    private $flight_prefix = 'flight_search';
    private $baggage_prefix = 'baggage_prefix';
    private $meal_prefix = 'meal_prefix';
    private $seat_prefix = 'seat_prefix';
    private $commission = null;
    private $commission_type = null;
    private $marketing_airline = "";
    private $flight_details = [];
    private $last_arrival;

    public function searchFlightParser(array $response, array $request, string $jsessionId): array
    {
        $pricedItinerary = $response['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary'];
        $itineraries = combineSegmentsByRoute($pricedItinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'], $request);
        $price_bundle_details = arrayConversion($pricedItinerary['AirItinerary']['OriginDestinationOptions']['AABundledServiceExt']);

        $echoToken = $response['Body']['OTA_AirPriceRS']['EchoToken'];
        $transactionIdentifier = $response['Body']['OTA_AirPriceRS']['TransactionIdentifier'];

        $legs = [];
        foreach (arrayConversion($itineraries) as $key => $itinerary) {

            $segments = [];
            $journey_duration = 0;
            $flight_number = [];
            $sector = [];

            $this->last_arrival = null;

            foreach (arrayConversion($itinerary) as $segment_key => $segment_value) {


                if($segment_key > 0){
                    $this->last_arrival = arrayConversion($itinerary)[$segment_key-1]['ArrivalDateTime'];
                }
                
                $segment = $this->manageSegment($segment_value);
                $journey_duration += ($segment['duration_minutes'] + $segment['layover_waited_time']);
                array_push($flight_number, $segment['operating_flight_number']);
                $segments[] = $segment;


                if($key == 0 && $segment_key == 0){
                    $this->marketing_airline = $segment['marketing_code'];
                }

                if ($segment_key == 0) {
                    array_push($sector, $segment_value['DepartureAirport']['LocationCode']);
                    array_push($sector, $segment_value['ArrivalAirport']['LocationCode']);
                }
            }

            $legs[] = [
                'segments' => $segments,
                'journey_duration' => $journey_duration,
                'sector' => $sector,
                'flight_number' => $flight_number
            ];
        }

        $fare_set = [];
        foreach($price_bundle_details as $price_bundle){

            $fare_option = [];
            
            foreach (arrayConversion(add_basic_service($price_bundle['bundledService'])) as $value) {

                if(in_array($value['bundledServiceName'], ['CORPORATE', 'Premium'])){
                    continue;
                }

                $pre_booking_id = random_id('FB');

                $fare = $this->manageFare((array)$value, (array)$pricedItinerary['AirItineraryPricingInfo'], $pre_booking_id);
                $traveler_reference = $fare['traveler_reference'];
                unset($fare['traveler_reference']);
    
                $fare_option[] = $fare;
    
                $combineData = [
                    'legs'                  => $legs,
                    'price'                 => $fare,
                    'API'                   => 'ONEAPI',
                    'echoToken'             => $echoToken,
                    'bundledService'        => $value,
                    'itinerary_details'     => $itineraries,
                    'request'               => $request,
                    'jsessionId'            => $jsessionId,
                    'traveler_reference'    => $traveler_reference,
                    'transactionIdentifier' => $transactionIdentifier,
                ];
    
                set_data($pre_booking_id, $this->flight_prefix, 660, $combineData);
            }
            $fare_set[] = $fare_option;
        }

        $searchAvailability['airline'] = fetch_airline($this->marketing_airline);
        $searchAvailability['fare_option'] = $fare_set;
        $searchAvailability['legs'] = $legs;
        $searchAvailability['name'] = 'FLYJINNAH';
        $searchAvailability['provider'] = 'ONEAPI';

        return $searchAvailability;
    }

    function manageSegment($flightSegment): array
    {
        $segment['arrival_datetime'] = $flightSegment['ArrivalDateTime'];
        $segment['departure_datetime'] = $flightSegment['DepartureDateTime'];
        $segment['code_share'] = false;

        $segment['duration_minutes'] = flight_duration($flightSegment['DepartureDateTime'], $flightSegment['ArrivalDateTime'], $flightSegment['DepartureAirport']['LocationCode'], $flightSegment['ArrivalAirport']['LocationCode']);
        $segment['flight_number'] = substr($flightSegment['FlightNumber'], 2);
        $segment['marketing_code'] = substr($flightSegment['FlightNumber'], 0, 2);
        $segment['marketing_flight_number'] = substr($flightSegment['FlightNumber'], 2);
        $segment['operating_code'] = substr($flightSegment['FlightNumber'], 0, 2);
        $segment['operating_flight_number'] = substr($flightSegment['FlightNumber'], 2);
        $segment['sector'] = is_domastic($flightSegment['DepartureAirport']['LocationCode'], $flightSegment['ArrivalAirport']['LocationCode']) ? 'DOMESTIC' : 'INTERNATIONAL';
        

        $segment['layover_waited_time'] = is_null($this->last_arrival)
            ? 0 : abs(\Carbon\Carbon::parse($this->last_arrival)->diffInMinutes(\Carbon\Carbon::parse($flightSegment['DepartureDateTime'])));

        $segment['origin'] = fetch_airport($flightSegment['DepartureAirport']['LocationCode']);
        $segment['origin']['terminal'] = $flightSegment['DepartureAirport']['Terminal'];
        $segment['destination'] = fetch_airport($flightSegment['ArrivalAirport']['LocationCode']);
        $segment['destination']['terminal'] = $flightSegment['ArrivalAirport']['Terminal'];
        $airline = fetch_airline(substr($flightSegment['FlightNumber'], 0, 2));
        $segment['marketing_airline'] = $airline;
        $segment['operating_airline'] = $airline;
        return $segment;
    }

    public function manageFare(array $bundledService, array $pricedItinerary, ?string $booking_id): array
    {
        $fareInformation = [
            'price'         => [],
            'rbd'           => '',
            'booking_id'    => $booking_id,
            'has_baggage'   => false,
            'has_meal'      => false,
            'is_refundable' => false
        ];

        $fareBreakDownData = [];
        $baggageInformation = [];
        $totalTaxValue = 0;
        $totalFeeValue = 0;
        $sumarize_per_pax_bundled_fee = 0;
        $traveler_reference = [];
        $baggageTitleStr = "";

        foreach (arrayConversion($pricedItinerary['PTC_FareBreakdowns']['PTC_FareBreakdown']) as $paxFareInfo) {

            $passengerTypeData = $paxFareInfo['PassengerTypeQuantity'];
            $pricingInfo       = $paxFareInfo['PassengerFare'];
            $quantity          = $passengerTypeData['Quantity'];

            foreach (arrayConversion($paxFareInfo['TravelerRefNumber']) as $traveler_ref) {
                array_push($traveler_reference, $traveler_ref['RPH']);
            }

            $perPaxBundledFee = array_key_exists('perPaxBundledFee', $bundledService) ? $bundledService['perPaxBundledFee'] : 0;
            $sumarize_per_pax_bundled_fee += $perPaxBundledFee;
            $baseFare = ((float) $pricingInfo['BaseFare']['Amount'] + $perPaxBundledFee) * $quantity;

            $taxes = $pricingInfo['Taxes']['Tax'] ?? [];
            $totalTax = sum_amount_from_array($taxes) * $quantity;

            $fees = $pricingInfo['Fees']['Fee'] ?? [];
            $surcharges = sum_amount_from_array($fees) * $quantity;

            $grossFare = $baseFare + $totalTax + $surcharges;

            $totalFeeValue += $surcharges;
            $totalTaxValue += $totalTax;

            $fareBreakDown = [
                'quantity'     => $quantity,
                'base_fare'    => numberFormat($baseFare + $surcharges),
                'tax'          => numberFormat($totalTax),
                'discount'     => 0,
                'gross_amount' => numberFormat($grossFare),
                'total_amount' => numberFormat($grossFare),
                'discount'     => 0,
                'fees'         => 0,
                'currency'     => 'PKR'
            ];

            $passengerCode = $passengerTypeData['Code'];
            $fareBreakDownData[paxType()[$passengerCode]] = $fareBreakDown;

            if(in_array($passengerCode, ['ADT', 'CHD'])){
                $checked_baggage = checked_baggage($bundledService['bundledServiceName']);
                $bagageBreakDown = [
                    [
                        "weight"        => $checked_baggage,
                        "unit"          => "kg",
                        "provisionType" => "A",
                        "provision"     => "Checked baggage allowance",
                    ],
                    [
                        "weight"        => 10,
                        "unit"          => "kg",
                        "provisionType" => "B",
                        "provision"     => "Carry-on baggage allowance",
                    ]
                ];

                $baggageInformation[$passengerCode] = $bagageBreakDown;
                $weight = $checked_baggage . 'KG';
                $baggageTitleStr .= '(' . $quantity . ' ' . $passengerCode . ': ' . 1 . 'PC' . ' ' . $weight . ' x ' . $quantity . ')';
            }
        }

        $fareInformation['bagage_info'] = trim($baggageTitleStr);
        $fareInformation['baggageInformation'] = $baggageInformation;

        // Pricing Overview
        $pricingOverview = $pricedItinerary['ItinTotalFare'];
        $commission      = 0;

        $summarize = ($pricingOverview['BaseFare']['Amount'] + $sumarize_per_pax_bundled_fee) + $totalFeeValue;

        $fareInformation['price'] = [
            'fare_break_down' => $fareBreakDownData,
            'base_fare'       => numberFormat($summarize),
            'tax'             => numberFormat($totalTaxValue),
            'discount'        => numberFormat($commission),
            'gross_amount'    => numberFormat($summarize + $totalTaxValue),
            'total_amount'    => numberFormat($summarize + $totalTaxValue),
            'discount'        => 0,
            'fees'            => 0,
            'currency'        => 'PKR',
        ];

        $fareInformation['has_baggage'] =
        $fareInformation['has_meal'] =
        $fareInformation['is_refundable'] =
            $bundledService['bundledServiceName'] !== "Basic";
        $fareInformation['included_services'] = $bundledService['includedServies'];
        $fareInformation['rbd'] =  $bundledService['bundledServiceName'];
        $fareInformation['traveler_reference'] = $traveler_reference;
        return $fareInformation;
    }
    
    public function baggageParser($baggages, $bundle_service)
    {
        $AA_OTA_AirBaggageDetailsRS = $baggages['Body']['AA_OTA_AirBaggageDetailsRS'];

        $flight_baggage_detail = [];
        $flight_details = [];

        foreach (arrayConversion($AA_OTA_AirBaggageDetailsRS['BaggageDetailsResponses']['OnDBaggageDetailsResponse']) as $key => $value) {

            $pre_booking_id = random_id('FID');

            $baggage_details = [];
            foreach (arrayConversion($value['Baggage']) as $baggage) {
                $baggage_details[] = [
                    'baggage_code' => $baggage['baggageCode'],
                    'price' => $baggage['baggageCharge'],
                ];
            }
            $flight_detail = $value['OnDFlightSegmentInfo'];

            foreach(arrayConversion($flight_detail) as $value){
                $flight_details[$value['FlightNumber']] = $value;
            }

            $segment_code = "";
            if (array_key_exists(0, $flight_detail)) {
                $segment_code = implode('-', array_unique(explode('/', implode('/', array_column($flight_detail, 'SegmentCode')))));
            } else {
                $segment_code = $flight_detail['SegmentCode'];
            }
            $flight_baggage_detail[] = [
                'is_required' => service_required($bundle_service[$key]['bundledServiceName']),
                'sector' => str_replace('/', '-', $segment_code),
                'baggage' => $baggage_details,
                'flight_id' => $pre_booking_id,
            ];

            set_data($pre_booking_id, $this->baggage_prefix, 660, $flight_detail);
        }
        $this->flight_details = $flight_details;
        return $flight_baggage_detail;
    }

    public function mealParser($meals, $bundle_service)
    {
        $AA_OTA_AirMealDetailsRS = $meals['Body']['AA_OTA_AirMealDetailsRS'];
        $flight_meal_detail = [];
        foreach(arrayConversion($AA_OTA_AirMealDetailsRS['MealDetailsResponses']['MealDetailsResponse']) as $key => $value){

            $preBookingId = random_id('FID');
            $meal_details = [];

            foreach($value['Meal'] as $meal) {
                $mealData = [
                    'meal_name'         => $meal['mealName'],
                    'meal_description'  => $meal['mealDescription'],
                    'meal_image'        => $meal['mealImageLink'],
                    'price'             => $meal['mealCharge'],
                    'available_meal'    => $meal['availableMeals'],
                    'meal_code'         => $meal['mealCode']
                ];
                if (isset($meal_details[$meal['mealCategoryCode']])) {
                    $meal_details[$meal['mealCategoryCode']][] = $mealData;
                } else {
                    $meal_details[$meal['mealCategoryCode']] = [$mealData];
                }
            }

            $flight_detail = $this->flight_details[$value['FlightSegmentInfo']['FlightNumber']];
            $flight_meal_detail[] = [
                'is_required' => service_required($bundle_service[$key]['bundledServiceName']),
                'sector' => str_replace('/','-', $flight_detail['SegmentCode']),
                'meal' => $meal_details,
                'flight_id' => $preBookingId,
            ];
            set_data($preBookingId, $this->meal_prefix, 660, $flight_detail);
        }

        return $flight_meal_detail;
    }

    public function seatParser($meals, $bundle_service)
    {
        
        $OTA_AirSeatMapRS = $meals['Body']['OTA_AirSeatMapRS'];
        $flight_seats_detail = [];
        foreach (arrayConversion($OTA_AirSeatMapRS['SeatMapResponses']['SeatMapResponse']) as $key => $value) {

            $pre_booking_id = random_id('FID');
            $seat_details = [];
            foreach (arrayConversion($value['SeatMapDetails']['CabinClass']) as $cabin_classes) {

                $seat_class_details = [];
                foreach ($cabin_classes['AirRows']['AirRow'] as $air_row) {

                    $row_seats = [];
                    foreach ($air_row['AirSeats']['AirSeat'] as $seat) {

                        $row_seats[] = [
                            'seat_number' => $seat['SeatNumber'],
                            'price' => $seat['SeatCharacteristics'],
                            'seat_availability' => $seat['SeatAvailability'] == 'VAC' ? true : false,
                            'seat_code' => $air_row['RowNumber'] . '' . $seat['SeatNumber']
                        ];
                    }

                    $seat_class_details[] = [
                        'row_number' => $air_row['RowNumber'],
                        'seats' => $row_seats
                    ];
                }

                $seat_details[array_flip(SabreCabin())[$cabin_classes['CabinType']]] = $seat_class_details;
            }

            $flight_detail = $this->flight_details[$value['FlightSegmentInfo']['FlightNumber']];

            $flight_seats_detail[] = [
                'is_required' => service_required($bundle_service[$key]['bundledServiceName']),
                'sector' => str_replace('/', '-', $flight_detail['SegmentCode']),
                'seats' => $seat_details,
                'flight_id' => $pre_booking_id,
            ];

            set_data($pre_booking_id, $this->seat_prefix, 660, $flight_detail);
        }

        return $flight_seats_detail;
    }
}
