<?php

namespace App\Services\FlyJinnahService;

use DOMDocument;

class MakeRequest
{
    public function getAirAvailRequest($request)
    {
        $cabinClassMap = [
            'ECONOMY' => 'Y',
            'BUSINESS' => 'J'
        ];
        $cabinClass = $cabinClassMap[$request['cabin_class']] ?? 'Y';

        $paxCounts = [
            [
                'paxType' => 'ADT',
                'count' => $request['traveler_count']['adult_count'] ?? 0
            ],
            [
                'paxType' => 'CHD',
                'count' => $request['traveler_count']['child_count'] ?? 0
            ],
            [
                'paxType' => 'INF',
                'count' => $request['traveler_count']['infant_count'] ?? 0
            ]
        ];

        $searchOnds = [];

        $searchOnds[] = [
            'origin' => [
                'code' => $request['origin'],
                'locationType' => 'AIRPORT'
            ],
            'destination' => [
                'code' => $request['destination'],
                'locationType' => 'AIRPORT'
            ],
            'searchStartDate' => $request['departure_date'],
            'searchEndDate' => $request['departure_date'],
            'cabinClass' => $cabinClass,
            'bookingType' => 'NORMAL',
            'interlineQuoteDetails' => (object) []
        ];

        if (strtoupper($request['route_type']) === 'RETURN') {
            $searchOnds[] = [
                'origin' => [
                    'code' => $request['destination'],
                    'locationType' => 'AIRPORT'
                ],
                'destination' => [
                    'code' => $request['origin'],
                    'locationType' => 'AIRPORT'
                ],
                'searchStartDate' => $request['return_date'],
                'searchEndDate' => $request['return_date'],
                'cabinClass' => $cabinClass,
                'bookingType' => 'NORMAL',
                'interlineQuoteDetails' => (object) []
            ];
        }

        $payload = [
            'searchOnds' => $searchOnds,
            'paxCounts' => $paxCounts,
            'currencyCode' => 'PKR',
            'cabinClass' => $cabinClass
        ];

        return $payload;
    }

    public function priceRequest($request, $auth, $sector_data)
    {
        $message = new DOMDocument();
        // Create the SOAP envelope
        $soapEnvelope = $message->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soap:Envelope");
        $message->appendChild($soapEnvelope);

        $soapHeader = $message->createElement("soap:Header");
        $soapEnvelope->appendChild($soapHeader);

        $wsseSecurity = $message->createElement("wsse:Security");
        $wsseSecurity->setAttribute("soap:mustUnderstand", "1");
        $wsseSecurity->setAttribute("xmlns:wsse", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd");
        $soapHeader->appendChild($wsseSecurity);

        $wsseUsernameToken = $message->createElement("wsse:UsernameToken");
        $wsseUsernameToken->setAttribute("wsu:Id", "UsernameToken-32124385");
        $wsseUsernameToken->setAttribute("xmlns:wsu", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd");
        $wsseSecurity->appendChild($wsseUsernameToken);

        $wsseUsername = $message->createElement("wsse:Username", $auth['username']);
        $wssePassword = $message->createElement("wsse:Password", $auth['password']);
        $wssePassword->setAttribute("Type", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText");
        $wsseUsernameToken->appendChild($wsseUsername);
        $wsseUsernameToken->appendChild($wssePassword);

        // Create the SOAP body
        $soapBody = $message->createElement("soap:Body");
        $soapBody->setAttribute("xmlns:ns1", "http://www.opentravel.org/OTA/2003/05");
        $soapEnvelope->appendChild($soapBody);

        // OTA_AirPriceRQ
        $otaAirPriceRQ = $message->createElement("ns1:OTA_AirPriceRQ");
        $otaAirPriceRQ->setAttribute("EchoToken", '12662148060105253838426');
        $otaAirPriceRQ->setAttribute("PrimaryLangID", "en-us");
        $otaAirPriceRQ->setAttribute("SequenceNmbr", "1");
        $otaAirPriceRQ->setAttribute("TimeStamp", \Carbon\Carbon::now()->format('Y-m-d\TH:i:s'));
        $otaAirPriceRQ->setAttribute("Version", "20061.00");
        $soapBody->appendChild($otaAirPriceRQ);

        // POS
        $pos = $message->createElement("ns1:POS");
        $source = $message->createElement("ns1:Source");
        $source->setAttribute("TerminalID", $auth['username']);
        $requestorID = $message->createElement("ns1:RequestorID");
        $requestorID->setAttribute("ID", $auth['username']);
        $requestorID->setAttribute("Type", "4");
        $source->appendChild($requestorID);
        $bookingChannel = $message->createElement("ns1:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");
        $source->appendChild($bookingChannel);
        $pos->appendChild($source);
        $otaAirPriceRQ->appendChild($pos);
        $direction = 'OneWay';

        if ($request['route_type'] == 'RETURN') {
            $direction = "Return";
        }

        $airItinerary = $message->createElement("ns1:AirItinerary");
        $airItinerary->setAttribute("DirectionInd", $direction);
        $originDestinationOptions = $message->createElement("ns1:OriginDestinationOptions");

        if ($request['route_type'] === 'ONEWAY') {
            $originDestinationOption = $message->createElement("ns1:OriginDestinationOption");

            foreach (arrayConversion($sector_data) as $sector) {
                $flightSegment = $message->createElement("ns1:FlightSegment");
                $flightSegment->setAttribute("ArrivalDateTime", $sector['arrivalDateTimeLocal']);
                $flightSegment->setAttribute("DepartureDateTime", $sector['departureDateTimeLocal']);
                $flightSegment->setAttribute("FlightNumber", $sector['flightNumber']);

                $departureAirport = $message->createElement("ns1:DepartureAirport");
                $departureAirport->setAttribute("LocationCode", $sector['origin']['airportCode']);
                $flightSegment->appendChild($departureAirport);

                $arrivalAirport = $message->createElement("ns1:ArrivalAirport");
                $arrivalAirport->setAttribute("LocationCode", $sector['destination']['airportCode']);
                $flightSegment->appendChild($arrivalAirport);

                $operatingAirline = $message->createElement("ns1:OperatingAirline");
                $operatingAirline->setAttribute("Code", substr($sector['flightNumber'], 0, 2));
                $flightSegment->appendChild($operatingAirline);

                $originDestinationOption->appendChild($flightSegment);
            }

            $originDestinationOptions->appendChild($originDestinationOption);
        } else {
            foreach (arrayConversion($sector_data) as $sector) {
                $originDestinationOption = $message->createElement("ns1:OriginDestinationOption");

                foreach ($sector as $segment) {
                    $flightSegment = $message->createElement("ns1:FlightSegment");
                    $flightSegment->setAttribute("ArrivalDateTime", $segment['arrivalDateTimeLocal']);
                    $flightSegment->setAttribute("DepartureDateTime", $segment['departureDateTimeLocal']);
                    $flightSegment->setAttribute("FlightNumber", $segment['flightNumber']);

                    $departureAirport = $message->createElement("ns1:DepartureAirport");
                    $departureAirport->setAttribute("LocationCode", $segment['origin']['airportCode']);
                    $flightSegment->appendChild($departureAirport);

                    $arrivalAirport = $message->createElement("ns1:ArrivalAirport");
                    $arrivalAirport->setAttribute("LocationCode", $segment['destination']['airportCode']);
                    $flightSegment->appendChild($arrivalAirport);

                    $operatingAirline = $message->createElement("ns1:OperatingAirline");
                    $operatingAirline->setAttribute("Code", substr($segment['flightNumber'], 0, 2));
                    $flightSegment->appendChild($operatingAirline);

                    $originDestinationOption->appendChild($flightSegment);
                }
                $originDestinationOptions->appendChild($originDestinationOption);
            }
        }

        $airItinerary->appendChild($originDestinationOptions);
        $otaAirPriceRQ->appendChild($airItinerary);

        // TravelerInfoSummary
        $travelerInfoSummary = $message->createElement("ns1:TravelerInfoSummary");
        $airTravelerAvail = $message->createElement("ns1:AirTravelerAvail");

        $traveler_count = $request['traveler_count'];
        $passengerTypes = ['ADT' => $traveler_count['adult_count'], 'CHD' => $traveler_count['child_count'], 'INF' => $traveler_count['infant_count']];
        foreach ($passengerTypes as $code => $quantity) {
            if ($quantity > 0) {
                $passengerTypeQuantity = $message->createElement("ns1:PassengerTypeQuantity");
                $passengerTypeQuantity->setAttribute("Code", $code);
                $passengerTypeQuantity->setAttribute("Quantity", $quantity);
                $airTravelerAvail->appendChild($passengerTypeQuantity);
            }
        }

        $travelerInfoSummary->appendChild($airTravelerAvail);
        $otaAirPriceRQ->appendChild($travelerInfoSummary);

        $message->preserveWhiteSpace = false;
        $message->formatOutput = true;
        return $message->saveXML();
    }

    public function finalizePriceRequest($confirmation_data, $auth)
    {
        $echotoken = $confirmation_data[0]['echoToken'];
        $transID = $confirmation_data[0]['transactionIdentifier'];
        $request = $confirmation_data[0]['request'];

        $message = new DOMDocument('1.0', 'UTF-8');
        $message->formatOutput = true;
        $message->preserveWhiteSpace = false;

        // Create SOAP envelope
        $soapEnvelope = $message->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soap:Envelope");
        $soapEnvelope->setAttribute('xmlns:xsd', "http://www.w3.org/2001/XMLSchema");
        $soapEnvelope->setAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
        $soapEnvelope->setAttribute('xmlns:ns1', "http://www.opentravel.org/OTA/2003/05");
        $message->appendChild($soapEnvelope);

        // Header
        $soapHeader = $message->createElement("soap:Header");
        $soapEnvelope->appendChild($soapHeader);

        $wsseSecurity = $message->createElement("wsse:Security");
        $wsseSecurity->setAttribute("soap:mustUnderstand", "1");
        $wsseSecurity->setAttribute("xmlns:wsse", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd");
        $soapHeader->appendChild($wsseSecurity);

        $wsseUsernameToken = $message->createElement("wsse:UsernameToken");
        $wsseUsernameToken->setAttribute("wsu:Id", "UsernameToken-" . rand(10000000, 99999999));
        $wsseUsernameToken->setAttribute("xmlns:wsu", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd");
        $wsseSecurity->appendChild($wsseUsernameToken);

        $wsseUsername = $message->createElement("wsse:Username", $auth['username']);
        $wssePassword = $message->createElement("wsse:Password", $auth['password']);
        $wssePassword->setAttribute("Type", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText");
        $wsseUsernameToken->appendChild($wsseUsername);
        $wsseUsernameToken->appendChild($wssePassword);

        // Body
        $soapBody = $message->createElement("soap:Body");
        $soapEnvelope->appendChild($soapBody);

        // OTA_AirPriceRQ
        $otaAirPriceRQ = $message->createElement("ns1:OTA_AirPriceRQ");
        $otaAirPriceRQ->setAttribute("EchoToken", $echotoken);
        $otaAirPriceRQ->setAttribute("PrimaryLangID", "en-us");
        $otaAirPriceRQ->setAttribute("SequenceNmbr", "1");
        $otaAirPriceRQ->setAttribute("TimeStamp", date('Y-m-d\TH:i:s'));
        $otaAirPriceRQ->setAttribute("TransactionIdentifier", $transID);
        $otaAirPriceRQ->setAttribute("Version", "20061.00");
        $soapBody->appendChild($otaAirPriceRQ);

        // POS
        $pos = $message->createElement("ns1:POS");
        $source = $message->createElement("ns1:Source");
        $source->setAttribute("TerminalID", "TestUser/Test Runner");

        $requestorID = $message->createElement("ns1:RequestorID");
        $requestorID->setAttribute("Type", "4");
        $requestorID->setAttribute("ID", $auth['username']);
        $source->appendChild($requestorID);

        $bookingChannel = $message->createElement("ns1:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");
        $source->appendChild($bookingChannel);

        $pos->appendChild($source);
        $otaAirPriceRQ->appendChild($pos);

        // AirItinerary
        $direction = $request['route_type'] == 'RETURN' ? 'Return' : 'OneWay';
        $airItinerary = $message->createElement("ns1:AirItinerary");
        $airItinerary->setAttribute("DirectionInd", $direction);

        $originDestinationOptions = $message->createElement("ns1:OriginDestinationOptions");


        $itinerary_details = $confirmation_data[0]['itinerary_details'];
        foreach (arrayConversion($itinerary_details) as $group) {
            $originDestinationOption = $message->createElement("ns1:OriginDestinationOption");

            foreach (arrayConversion($group) as $flightSegment) {
                $flightSegmentNode = $message->createElement("ns1:FlightSegment");
                $flightSegmentNode->setAttribute("ArrivalDateTime", $flightSegment['ArrivalDateTime']);
                $flightSegmentNode->setAttribute("DepartureDateTime", $flightSegment['DepartureDateTime']);
                $flightSegmentNode->setAttribute("FlightNumber", $flightSegment['FlightNumber']);
                $flightSegmentNode->setAttribute("RPH", $flightSegment['RPH'] ?? '');
                $flightSegmentNode->setAttribute("returnFlag", "false");

                // Departure
                $departureAirport = $message->createElement("ns1:DepartureAirport");
                $departureAirport->setAttribute("LocationCode", $flightSegment['DepartureAirport']['LocationCode']);
                if (!empty($flightSegment['DepartureAirport']['Terminal'])) {
                    $departureAirport->setAttribute("Terminal", $flightSegment['DepartureAirport']['Terminal']);
                }
                $flightSegmentNode->appendChild($departureAirport);

                // Arrival
                $arrivalAirport = $message->createElement("ns1:ArrivalAirport");
                $arrivalAirport->setAttribute("LocationCode", $flightSegment['ArrivalAirport']['LocationCode']);
                if (!empty($flightSegment['ArrivalAirport']['Terminal'])) {
                    $arrivalAirport->setAttribute("Terminal", $flightSegment['ArrivalAirport']['Terminal']);
                }
                $flightSegmentNode->appendChild($arrivalAirport);

                // Airline
                $operatingAirline = $message->createElement("ns1:OperatingAirline");
                $operatingAirline->setAttribute("Code", substr($flightSegment['FlightNumber'], 0, 2));
                $flightSegmentNode->appendChild($operatingAirline);

                $originDestinationOption->appendChild($flightSegmentNode);
            }
            $originDestinationOptions->appendChild($originDestinationOption);
        }

        $airItinerary->appendChild($originDestinationOptions);
        $otaAirPriceRQ->appendChild($airItinerary);

        // Traveler Info
        $travelerInfoSummary = $message->createElement("ns1:TravelerInfoSummary");
        $airTravelerAvail = $message->createElement("ns1:AirTravelerAvail");

        $traveler_count = $request['traveler_count'];
        $passengerTypes = [
            'ADT' => $traveler_count['adult_count'] ?? 0,
            'CHD' => $traveler_count['child_count'] ?? 0,
            'INF' => $traveler_count['infant_count'] ?? 0,
        ];

        foreach ($passengerTypes as $code => $quantity) {
            if ($quantity > 0) {
                $passengerTypeQuantity = $message->createElement("ns1:PassengerTypeQuantity");
                $passengerTypeQuantity->setAttribute("Code", $code);
                $passengerTypeQuantity->setAttribute("Quantity", $quantity);
                $airTravelerAvail->appendChild($passengerTypeQuantity);
            }
        }

        $travelerInfoSummary->appendChild($airTravelerAvail);
        $otaAirPriceRQ->appendChild($travelerInfoSummary);

        $bundledServices = array_column($confirmation_data, 'bundledService');

        if (!empty($bundledServices)) {
            $bundledServiceSelection = $message->createElement("ns1:BundledServiceSelectionOptions");

            $hasValidService = false;
            if (!empty($bundledServices[0]['bunldedServiceId']) && $bundledServices[0]['bundledServiceName'] != "Basic") {
                $hasValidService = true;
                $outboundServiceId = $message->createElement("ns1:OutBoundBunldedServiceId", $bundledServices[0]['bunldedServiceId']);
                $bundledServiceSelection->appendChild($outboundServiceId);
            }
    
            if (!empty($bundledServices[1]['bunldedServiceId']) && $bundledServices[1]['bundledServiceName'] != "Basic") {
                $hasValidService = true;
                $inboundServiceId = $message->createElement("ns1:InBoundBunldedServiceId", $bundledServices[1]['bunldedServiceId']);
                $bundledServiceSelection->appendChild($inboundServiceId);
            }

            if($hasValidService){
                $otaAirPriceRQ->appendChild($bundledServiceSelection);
            }
        }

        return $message->saveXML();
    }


    public function addBundleServices($auth, $confirmation_data, $extra)
    {
        $flightArray = [];
        $request = $confirmation_data[0]['request'];
        $echotoken = $confirmation_data[0]['echoToken'];
        $transID =  $confirmation_data[0]['transactionIdentifier'];

        $message = new DOMDocument();

        // Create the SOAP envelope
        $soapEnvelope = $message->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soap:Envelope");
        $soapEnvelope->setAttribute('xmlns:xsd', "http://www.w3.org/2001/XMLSchema");
        $soapEnvelope->setAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
        $soapEnvelope->setAttribute('xmlns:ns1', "http://www.opentravel.org/OTA/2003/05");
        $message->appendChild($soapEnvelope);

        $soapHeader = $message->createElement("soap:Header");
        $soapEnvelope->appendChild($soapHeader);

        $wsseSecurity = $message->createElement("wsse:Security");
        $wsseSecurity->setAttribute("soap:mustUnderstand", "1");
        $wsseSecurity->setAttribute("xmlns:wsse", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd");
        $soapHeader->appendChild($wsseSecurity);

        $wsseUsernameToken = $message->createElement("wsse:UsernameToken");
        $wsseUsernameToken->setAttribute("wsu:Id", "UsernameToken-32124385");
        $wsseUsernameToken->setAttribute("xmlns:wsu", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd");
        $wsseSecurity->appendChild($wsseUsernameToken);

        $wsseUsername = $message->createElement("wsse:Username", $auth['username']);
        $wssePassword = $message->createElement("wsse:Password", $auth['password']);
        $wssePassword->setAttribute("Type", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText");
        $wsseUsernameToken->appendChild($wsseUsername);
        $wsseUsernameToken->appendChild($wssePassword);

        // Create the SOAP body
        $soapBody = $message->createElement("soap:Body");
        $soapBody->setAttribute("xmlns:ns1", "http://www.opentravel.org/OTA/2003/05");
        $soapEnvelope->appendChild($soapBody);

        // OTA_AirPriceRQ
        $otaAirPriceRQ = $message->createElement("ns1:OTA_AirPriceRQ");
        $otaAirPriceRQ->setAttribute("EchoToken", $echotoken);
        $otaAirPriceRQ->setAttribute("PrimaryLangID", "en-us");
        $otaAirPriceRQ->setAttribute("SequenceNmbr", "1");
        $otaAirPriceRQ->setAttribute("TransactionIdentifier", $transID);
        $otaAirPriceRQ->setAttribute("Version", "20061.00");
        $soapBody->appendChild($otaAirPriceRQ);

        // POS
        $pos = $message->createElement("ns1:POS");
        $source = $message->createElement("ns1:Source");
        $source->setAttribute("TerminalID", "TestUser/Test Runner");
        $requestorID = $message->createElement("ns1:RequestorID");
        $requestorID->setAttribute("ID", $auth['username']);
        $requestorID->setAttribute("Type", "4");
        $source->appendChild($requestorID);
        $bookingChannel = $message->createElement("ns1:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");
        $source->appendChild($bookingChannel);
        $pos->appendChild($source);
        $otaAirPriceRQ->appendChild($pos);

        $direction = $request['route_type'] == 'RETURN' ? 'Return' : 'OneWay';

        $airItinerary = $message->createElement("ns1:AirItinerary");
        $airItinerary->setAttribute("DirectionInd", $direction);
        $originDestinationOptions = $message->createElement("ns1:OriginDestinationOptions");

        $itinerary_details = $confirmation_data[0]['itinerary_details'];

        foreach (arrayConversion($itinerary_details) as $group) {
            $originDestinationOption = $message->createElement("ns1:OriginDestinationOption");

            foreach (arrayConversion($group) as $segment) {

                $flightSegment = $message->createElement("ns1:FlightSegment");
                $flightSegment->setAttribute("ArrivalDateTime", $segment['ArrivalDateTime']);
                $flightSegment->setAttribute("DepartureDateTime", $segment['DepartureDateTime']);
                $flightSegment->setAttribute("FlightNumber", $segment['FlightNumber']);
                $flightSegment->setAttribute("RPH", $segment['RPH']);
                $flightSegment->setAttribute("returnFlag", "false");



                $departureAirport = $message->createElement("ns1:DepartureAirport");
                $departureAirport->setAttribute("LocationCode", $segment['DepartureAirport']['LocationCode']);
                $flightSegment->appendChild($departureAirport);

                $arrivalAirport = $message->createElement("ns1:ArrivalAirport");
                $arrivalAirport->setAttribute("LocationCode", $segment['ArrivalAirport']['LocationCode']);
                $flightSegment->appendChild($arrivalAirport);

                $operatingAirline = $message->createElement("ns1:OperatingAirline");
                $operatingAirline->setAttribute("Code", substr($segment['FlightNumber'], 0, 2));
                $flightSegment->appendChild($operatingAirline);

                $originDestinationOption->appendChild($flightSegment);
            }

            $originDestinationOptions->appendChild($originDestinationOption);
        }

        $airItinerary->appendChild($originDestinationOptions);
        $otaAirPriceRQ->appendChild($airItinerary);

        // TravelerInfoSummary
        $travelerInfoSummary = $message->createElement("ns1:TravelerInfoSummary");
        $airTravelerAvail = $message->createElement("ns1:AirTravelerAvail");

        $traveler_count = $request['traveler_count'];
        $passengerTypes = ['ADT' => $traveler_count['adult_count'], 'CHD' => $traveler_count['child_count'], 'INF' => $traveler_count['infant_count']];
        foreach ($passengerTypes as $code => $quantity) {
            if ($quantity <= 0) {
                continue;
            }
            $passengerTypeQuantity = $message->createElement("ns1:PassengerTypeQuantity");
            $passengerTypeQuantity->setAttribute("Code", $code);
            $passengerTypeQuantity->setAttribute("Quantity", $quantity);
            $airTravelerAvail->appendChild($passengerTypeQuantity);
        }

        $travelerInfoSummary->appendChild($airTravelerAvail);

        $specialReqDetails = $message->createElement("ns1:SpecialReqDetails");

        if (array_key_exists('baggage', $extra) && count($extra['baggage']) > 0) {
            $baggageRequests = $message->createElement('ns1:BaggageRequests');

            
            foreach ($extra['baggage'] as $value) {

                $flight_data_set = get_data($value['flight_id'], 'baggage_prefix');

                foreach(arrayConversion($flight_data_set)  as $flight_data){

                    foreach (arrayConversion($flight_data) as $flight) {

                        foreach ($value['travelers'] as $traveler) {
                            $baggageRequest = $message->createElement('ns1:BaggageRequest');
                            $baggageRequest->setAttribute("baggageCode", $traveler['baggage_code']);
                            $baggageRequest->setAttribute("TravelerRefNumberRPHList", $traveler['reference']);
                            $baggageRequest->setAttribute("FlightRefNumberRPHList", $flight['RPH']);
                            $baggageRequest->setAttribute("DepartureDate", $flight['DepartureDateTime']);
                            $baggageRequest->setAttribute("FlightNumber", $flight['FlightNumber']);
                            $baggageRequests->appendChild($baggageRequest);
                        }
                    }
                }
                
            }
            $specialReqDetails->appendChild($baggageRequests);
        }

        if (array_key_exists('meal', $extra) && count($extra['meal']) > 0) {
            $mealRequests = $message->createElement('ns1:MealRequests');

            foreach ($extra['meal'] as $value) {

                $flight = get_data($value['flight_id'], 'meal_prefix');
                
                foreach ($value['travelers'] as $traveler) {

                    $reference = $traveler['reference'];

                    foreach ($traveler['meal'] as $meal) {
                        $mealRequest = $message->createElement('ns1:MealRequest');
                        $mealRequest->setAttribute("mealCode", $meal['meal_code']);
                        $mealRequest->setAttribute("mealQuantity", (string) $meal['quantity']);
                        $mealRequest->setAttribute("TravelerRefNumberRPHList", $reference);
                        $mealRequest->setAttribute("FlightRefNumberRPHList", $flight['RPH']);
                        $mealRequest->setAttribute("DepartureDate", $flight['DepartureDateTime']);
                        $mealRequest->setAttribute("FlightNumber", $flight['FlightNumber']);
                        $mealRequests->appendChild($mealRequest);
                    }
                }
            }
            $specialReqDetails->appendChild($mealRequests);
        }

        if (array_key_exists('seat', $extra) && count($extra['seat']) > 0) {
            $seatRequests = $message->createElement('ns1:SeatRequests');

            foreach ($extra['seat'] as $value) {

                $flight = get_data($value['flight_id'], 'seat_prefix');
                foreach ($value['travelers'] as $traveler) {
                    $seatRequest = $message->createElement('ns1:SeatRequest');
                    $seatRequest->setAttribute("SeatNumber", $traveler['seat_code']);
                    $seatRequest->setAttribute("TravelerRefNumberRPHList", $traveler['reference']);
                    $seatRequest->setAttribute("FlightRefNumberRPHList", $flight['RPH']);
                    $seatRequests->appendChild($seatRequest);
                }
            }
            $specialReqDetails->appendChild($seatRequests);
        }

        $travelerInfoSummary->appendChild($specialReqDetails);

        $otaAirPriceRQ->appendChild($travelerInfoSummary);

        $message->preserveWhiteSpace = false;
        $message->formatOutput = true;

        return $message->saveXML();
    }

    public function baggage($confirmation_data, $auth)
    {

        $echotoken = $confirmation_data[0]['echoToken'];
        $transID = $confirmation_data[0]['transactionIdentifier'];

        $message = new DOMDocument('1.0', 'UTF-8');

        // Create the SOAP envelope with namespaces
        $soapEnvelope = $message->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soapenv:Envelope");
        $message->appendChild($soapEnvelope);

        // Add namespaces
        $soapEnvelope->setAttribute("xmlns:ns", "http://www.opentravel.org/OTA/2003/05");
        $soapEnvelope->setAttribute("xmlns:soapenv", "http://schemas.xmlsoap.org/soap/envelope/");

        // Create the SOAP header
        $soapHeader = $message->createElement("soapenv:Header");
        $soapEnvelope->appendChild($soapHeader);

        // Add wsse:Security header
        $wsseSecurity = $message->createElement("wsse:Security");
        $wsseSecurity->setAttribute("soapenv:mustUnderstand", "1");
        $wsseSecurity->setAttribute("xmlns:wsse", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd");
        $soapHeader->appendChild($wsseSecurity);

        // Add wsse:UsernameToken
        $wsseUsernameToken = $message->createElement("wsse:UsernameToken");
        $wsseUsernameToken->setAttribute("wsu:Id", "UsernameToken-26506823");
        $wsseUsernameToken->setAttribute("xmlns:wsu", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd");
        $wsseSecurity->appendChild($wsseUsernameToken);

        // Add Username and Password
        $wsseUsername = $message->createElement("wsse:Username", $auth['username']);
        $wssePassword = $message->createElement("wsse:Password", $auth['password']);
        $wssePassword->setAttribute("Type", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText");
        $wsseUsernameToken->appendChild($wsseUsername);
        $wsseUsernameToken->appendChild($wssePassword);

        // Create the SOAP body
        $soapBody = $message->createElement("soapenv:Body");
        $soapEnvelope->appendChild($soapBody);

        // Create the main request
        $request = $message->createElement("ns:AA_OTA_AirBaggageDetailsRQ");
        $request->setAttribute("EchoToken", $echotoken);
        $request->setAttribute("PrimaryLangID", "en-us");
        $request->setAttribute("SequenceNmbr", "1");
        $request->setAttribute("TransactionIdentifier", $transID);
        $request->setAttribute("Version", "20061.00");
        $soapBody->appendChild($request);

        // Add POS section
        $pos = $message->createElement("ns:POS");
        $source = $message->createElement("ns:Source");
        $source->setAttribute("TerminalID", "TestUser/Test Runner");
        $requestorID = $message->createElement("ns:RequestorID");
        $requestorID->setAttribute("ID", $auth['username']);
        $requestorID->setAttribute("Type", "4");
        $source->appendChild($requestorID);
        $bookingChannel = $message->createElement("ns:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");
        $source->appendChild($bookingChannel);
        $pos->appendChild($source);
        $request->appendChild($pos);

        // Add BaggageDetailsRequests
        $baggageDetailsRequests = $message->createElement("ns:BaggageDetailsRequests");
        $itinerary_details = $confirmation_data[0]['itinerary_details'];

        foreach (arrayConversion($itinerary_details) as $group) {
            foreach (arrayConversion($group) as $flightSegment) {

                $baggageDetailsRequest = $message->createElement("ns:BaggageDetailsRequest");

                $flightSegmentInfo = $message->createElement("ns:FlightSegmentInfo");
                $flightSegmentInfo->setAttribute("ArrivalDateTime", $flightSegment['ArrivalDateTime']);
                $flightSegmentInfo->setAttribute("DepartureDateTime", $flightSegment['DepartureDateTime']);
                $flightSegmentInfo->setAttribute("FlightNumber", $flightSegment['FlightNumber']);
                $flightSegmentInfo->setAttribute("RPH", $flightSegment['RPH']);
                $flightSegmentInfo->setAttribute("returnFlag", "false");

                // DepartureAirport
                $departureAirport = $message->createElement("ns:DepartureAirport");
                $departureAirport->setAttribute("LocationCode", $flightSegment['DepartureAirport']['LocationCode']);
                $departureAirport->setAttribute("Terminal", $flightSegment['DepartureAirport']['Terminal']);
                $flightSegmentInfo->appendChild($departureAirport);

                // ArrivalAirport
                $arrivalAirport = $message->createElement("ns:ArrivalAirport");
                $arrivalAirport->setAttribute("LocationCode", $flightSegment['ArrivalAirport']['LocationCode']);
                $arrivalAirport->setAttribute("Terminal", $flightSegment['ArrivalAirport']['Terminal']);
                $flightSegmentInfo->appendChild($arrivalAirport);

                // OperatingAirline
                $operatingAirline = $message->createElement("ns:OperatingAirline");
                $operatingAirline->setAttribute("Code", substr($flightSegment['FlightNumber'], 0, 2));
                $flightSegmentInfo->appendChild($operatingAirline);

                $baggageDetailsRequest->appendChild($flightSegmentInfo);
                $baggageDetailsRequests->appendChild($baggageDetailsRequest);
            }
        }

        $request->appendChild($baggageDetailsRequests);

        // Output the XML
        $message->preserveWhiteSpace = false;
        $message->formatOutput = true;

        return $message->saveXML();
    }

    public function meal($confirmation_data, $auth)
    {
        $echotoken = $confirmation_data[0]['echoToken'];
        $transID = $confirmation_data[0]['transactionIdentifier'];
        $message = new DOMDocument('1.0', 'UTF-8');

        // Create the SOAP envelope with namespaces
        $soapEnvelope = $message->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soapenv:Envelope");
        $message->appendChild($soapEnvelope);

        // Add namespaces
        $soapEnvelope->setAttribute("xmlns:ns", "http://www.opentravel.org/OTA/2003/05");
        $soapEnvelope->setAttribute("xmlns:soapenv", "http://schemas.xmlsoap.org/soap/envelope/");

        // Create the SOAP header
        $soapHeader = $message->createElement("soapenv:Header");
        $soapEnvelope->appendChild($soapHeader);

        // Add wsse:Security header
        $wsseSecurity = $message->createElement("wsse:Security");
        $wsseSecurity->setAttribute("soapenv:mustUnderstand", "1");
        $wsseSecurity->setAttribute("xmlns:wsse", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd");
        $soapHeader->appendChild($wsseSecurity);

        // Add wsse:UsernameToken
        $wsseUsernameToken = $message->createElement("wsse:UsernameToken");
        $wsseUsernameToken->setAttribute("wsu:Id", "UsernameToken-26506823");
        $wsseUsernameToken->setAttribute("xmlns:wsu", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd");
        $wsseSecurity->appendChild($wsseUsernameToken);

        // Add Username and Password
        $wsseUsername = $message->createElement("wsse:Username", $auth['username']);
        $wssePassword = $message->createElement("wsse:Password", $auth['password']);
        $wssePassword->setAttribute("Type", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText");
        $wsseUsernameToken->appendChild($wsseUsername);
        $wsseUsernameToken->appendChild($wssePassword);

        // Create the SOAP body
        $soapBody = $message->createElement("soapenv:Body");
        $soapEnvelope->appendChild($soapBody);

        // Create the main request
        $request = $message->createElement("ns:AA_OTA_AirMealDetailsRQ");
        $request->setAttribute("EchoToken", $echotoken);
        $request->setAttribute("PrimaryLangID", "en-us");
        $request->setAttribute("SequenceNmbr", "1");
        $request->setAttribute("TransactionIdentifier", $transID);
        $request->setAttribute("Version", "20061.00");
        $soapBody->appendChild($request);

        // Add POS section
        $pos = $message->createElement("ns:POS");
        $source = $message->createElement("ns:Source");
        $source->setAttribute("TerminalID", "TestUser/Test Runner");
        $requestorID = $message->createElement("ns:RequestorID");
        $requestorID->setAttribute("ID", $auth['username']);
        $requestorID->setAttribute("Type", "4");
        $source->appendChild($requestorID);
        $bookingChannel = $message->createElement("ns:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");
        $source->appendChild($bookingChannel);
        $pos->appendChild($source);
        $request->appendChild($pos);

        // Add BaggageDetailsRequests
        $mealDetailsRequests = $message->createElement("ns:MealDetailsRequests");
        $itinerary_details = $confirmation_data[0]['itinerary_details'];
        foreach (arrayConversion($itinerary_details) as $group) {
            foreach (arrayConversion($group) as $flightSegment) {

                $mealDetailsRequest = $message->createElement("ns:MealDetailsRequest");

                $flightSegmentInfo = $message->createElement("ns:FlightSegmentInfo");
                $flightSegmentInfo->setAttribute("ArrivalDateTime", $flightSegment['ArrivalDateTime']);
                $flightSegmentInfo->setAttribute("DepartureDateTime", $flightSegment['DepartureDateTime']);
                $flightSegmentInfo->setAttribute("FlightNumber", $flightSegment['FlightNumber']);
                $flightSegmentInfo->setAttribute("RPH", $flightSegment['RPH']);
                $flightSegmentInfo->setAttribute("returnFlag", "false");

                // DepartureAirport
                $departureAirport = $message->createElement("ns:DepartureAirport");
                $departureAirport->setAttribute("LocationCode", $flightSegment['DepartureAirport']['LocationCode']);
                $departureAirport->setAttribute("Terminal", $flightSegment['DepartureAirport']['Terminal']);
                $flightSegmentInfo->appendChild($departureAirport);

                // ArrivalAirport
                $arrivalAirport = $message->createElement("ns:ArrivalAirport");
                $arrivalAirport->setAttribute("LocationCode", $flightSegment['ArrivalAirport']['LocationCode']);
                $arrivalAirport->setAttribute("Terminal", $flightSegment['ArrivalAirport']['Terminal']);
                $flightSegmentInfo->appendChild($arrivalAirport);

                // OperatingAirline
                $operatingAirline = $message->createElement("ns:OperatingAirline");
                $operatingAirline->setAttribute("Code", substr($flightSegment['FlightNumber'], 0, 2));
                $flightSegmentInfo->appendChild($operatingAirline);

                $mealDetailsRequest->appendChild($flightSegmentInfo);
                $mealDetailsRequests->appendChild($mealDetailsRequest);
            }
        }

        $request->appendChild($mealDetailsRequests);

        // Output the XML
        $message->preserveWhiteSpace = false;
        $message->formatOutput = true;

        return $message->saveXML();
    }

    public function seat($confirmation_data, $auth)
    {
        $echotoken = $confirmation_data[0]['echoToken'];
        $transID = $confirmation_data[0]['transactionIdentifier'];

        $message = new DOMDocument('1.0', 'UTF-8');

        // Create the SOAP envelope with namespaces
        $soapEnvelope = $message->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soapenv:Envelope");
        $message->appendChild($soapEnvelope);

        // Add namespaces
        $soapEnvelope->setAttribute("xmlns:ns", "http://www.opentravel.org/OTA/2003/05");
        $soapEnvelope->setAttribute("xmlns:soapenv", "http://schemas.xmlsoap.org/soap/envelope/");

        // Create the SOAP header
        $soapHeader = $message->createElement("soapenv:Header");
        $soapEnvelope->appendChild($soapHeader);

        // Add wsse:Security header
        $wsseSecurity = $message->createElement("wsse:Security");
        $wsseSecurity->setAttribute("soapenv:mustUnderstand", "1");
        $wsseSecurity->setAttribute("xmlns:wsse", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd");
        $soapHeader->appendChild($wsseSecurity);

        // Add wsse:UsernameToken
        $wsseUsernameToken = $message->createElement("wsse:UsernameToken");
        $wsseUsernameToken->setAttribute("wsu:Id", "UsernameToken-26506823");
        $wsseUsernameToken->setAttribute("xmlns:wsu", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd");
        $wsseSecurity->appendChild($wsseUsernameToken);

        // Add Username and Password
        $wsseUsername = $message->createElement("wsse:Username", $auth['username']);
        $wssePassword = $message->createElement("wsse:Password", $auth['password']);
        $wssePassword->setAttribute("Type", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText");
        $wsseUsernameToken->appendChild($wsseUsername);
        $wsseUsernameToken->appendChild($wssePassword);

        // Create the SOAP body
        $soapBody = $message->createElement("soapenv:Body");
        $soapEnvelope->appendChild($soapBody);

        // Create the main request
        $request = $message->createElement("ns:OTA_AirSeatMapRQ");
        $request->setAttribute("EchoToken", $echotoken);
        $request->setAttribute("PrimaryLangID", "en-us");
        $request->setAttribute("SequenceNmbr", "1");
        $request->setAttribute("TransactionIdentifier", $transID);
        $request->setAttribute("Version", "20061.00");
        $soapBody->appendChild($request);

        // Add POS section
        $pos = $message->createElement("ns:POS");
        $source = $message->createElement("ns:Source");
        $source->setAttribute("TerminalID", "TestUser/Test Runner");
        $requestorID = $message->createElement("ns:RequestorID");
        $requestorID->setAttribute("ID", $auth['username']);
        $requestorID->setAttribute("Type", "4");
        $source->appendChild($requestorID);
        $bookingChannel = $message->createElement("ns:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");
        $source->appendChild($bookingChannel);
        $pos->appendChild($source);
        $request->appendChild($pos);

        $seatDetailsRequests = $message->createElement("ns:SeatMapRequests");

        $itinerary_details = $confirmation_data[0]['itinerary_details'];
        foreach (arrayConversion($itinerary_details) as $group) {
            foreach (arrayConversion($group) as $flightSegment) {

                $seatDetailsRequest = $message->createElement("ns:SeatMapRequest");

                $flightSegmentInfo = $message->createElement("ns:FlightSegmentInfo");
                $flightSegmentInfo->setAttribute("ArrivalDateTime", $flightSegment['ArrivalDateTime']);
                $flightSegmentInfo->setAttribute("DepartureDateTime", $flightSegment['DepartureDateTime']);
                $flightSegmentInfo->setAttribute("FlightNumber", $flightSegment['FlightNumber']);
                $flightSegmentInfo->setAttribute("RPH", $flightSegment['RPH']);
                $flightSegmentInfo->setAttribute("returnFlag", "false");

                // DepartureAirport
                $departureAirport = $message->createElement("ns:DepartureAirport");
                $departureAirport->setAttribute("LocationCode", $flightSegment['DepartureAirport']['LocationCode']);
                $departureAirport->setAttribute("Terminal", $flightSegment['DepartureAirport']['Terminal']);
                $flightSegmentInfo->appendChild($departureAirport);

                // ArrivalAirport
                $arrivalAirport = $message->createElement("ns:ArrivalAirport");
                $arrivalAirport->setAttribute("LocationCode", $flightSegment['ArrivalAirport']['LocationCode']);
                $arrivalAirport->setAttribute("Terminal", $flightSegment['ArrivalAirport']['Terminal']);
                $flightSegmentInfo->appendChild($arrivalAirport);

                // OperatingAirline
                $operatingAirline = $message->createElement("ns:OperatingAirline");
                $operatingAirline->setAttribute("Code", substr($flightSegment['FlightNumber'], 0, 2));
                $flightSegmentInfo->appendChild($operatingAirline);

                $seatDetailsRequest->appendChild($flightSegmentInfo);
                $seatDetailsRequests->appendChild($seatDetailsRequest);
            }
        }

        $request->appendChild($seatDetailsRequests);

        $message->preserveWhiteSpace = false;
        $message->formatOutput = true;

        return $message->saveXML();
    }

    public function createBookingRequest($auth, $confirmation_data, $request)
    {
        $echotoken = $confirmation_data[0]['echoToken'];
        $transID = $confirmation_data[0]['transactionIdentifier'];
        $traveler_reference = $confirmation_data[0]['traveler_reference'];

        $message = new DOMDocument('1.0', 'UTF-8');

        $soapEnvelope = $message->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soap:Envelope");
        $soapEnvelope->setAttribute('xmlns:xsd', "http://www.w3.org/2001/XMLSchema");
        $soapEnvelope->setAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
        $soapEnvelope->setAttribute('xmlns:ns1', "http://www.opentravel.org/OTA/2003/05");
        $soapEnvelope->setAttribute('xmlns:ns2', "http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05");
        $message->appendChild($soapEnvelope);

        // Create Header
        $soapHeader = $message->createElement("soap:Header");
        $soapEnvelope->appendChild($soapHeader);

        $wsseSecurity = $message->createElement("wsse:Security");
        $wsseSecurity->setAttribute("soap:mustUnderstand", "1");
        $wsseSecurity->setAttribute("xmlns:wsse", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd");
        $soapHeader->appendChild($wsseSecurity);

        $wsseUsernameToken = $message->createElement("wsse:UsernameToken");
        $wsseUsernameToken->setAttribute("wsu:Id", "UsernameToken-17855236");
        $wsseUsernameToken->setAttribute("xmlns:wsu", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd");
        $wsseSecurity->appendChild($wsseUsernameToken);

        $wsseUsername = $message->createElement("wsse:Username", $auth['username']);
        $wsseUsernameToken->appendChild($wsseUsername);

        $wssePassword = $message->createElement("wsse:Password", $auth['password']);
        $wssePassword->setAttribute("Type", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText");
        $wsseUsernameToken->appendChild($wssePassword);

        // Create Body
        $soapBody = $message->createElement("soap:Body");
        $soapEnvelope->appendChild($soapBody);

        // OTA_AirBookRQ
        $otaAirBookRQ = $message->createElement("ns1:OTA_AirBookRQ");
        $otaAirBookRQ->setAttribute("EchoToken", $echotoken);
        $otaAirBookRQ->setAttribute("PrimaryLangID", "en-us");
        $otaAirBookRQ->setAttribute("SequenceNmbr", "1");
        $otaAirBookRQ->setAttribute("TimeStamp", \Carbon\Carbon::now()->format('Y-m-d\TH:i:s'));
        $otaAirBookRQ->setAttribute("TransactionIdentifier", $transID);

        $soapBody->appendChild($otaAirBookRQ);

        // POS Section
        $pos = $message->createElement("ns1:POS");
        $source = $message->createElement("ns1:Source");
        $source->setAttribute("TerminalID", "TestUser/Test Runner");
        $requestorID = $message->createElement("ns1:RequestorID");
        $requestorID->setAttribute("ID", $auth['username']);
        $requestorID->setAttribute("Type", "4");
        $source->appendChild($requestorID);

        $bookingChannel = $message->createElement("ns1:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");
        $source->appendChild($bookingChannel);
        $pos->appendChild($source);
        $otaAirBookRQ->appendChild($pos);

        $airItinerary = $message->createElement("ns1:AirItinerary");
        $originDestinationOptions = $message->createElement("ns1:OriginDestinationOptions");

        $itinerary_details = $confirmation_data[0]['itinerary_details'];
        foreach (arrayConversion($itinerary_details) as $group) {
            $originDestinationOption = $message->createElement("ns1:OriginDestinationOption");
            foreach (arrayConversion($group) as $segment) {
                $flightSegment = $message->createElement("ns1:FlightSegment");
                $flightSegment->setAttribute("ArrivalDateTime", $segment['ArrivalDateTime']);
                $flightSegment->setAttribute("DepartureDateTime", $segment['DepartureDateTime']);
                $flightSegment->setAttribute("FlightNumber", $segment['FlightNumber']);
                $flightSegment->setAttribute("RPH", $segment['RPH']);

                $departureAirport = $message->createElement("ns1:DepartureAirport");
                $departureAirport->setAttribute("LocationCode", $segment['DepartureAirport']['LocationCode']);
                $departureAirport->setAttribute("Terminal", $segment['DepartureAirport']['Terminal']);
                $flightSegment->appendChild($departureAirport);

                $arrivalAirport = $message->createElement("ns1:ArrivalAirport");
                $arrivalAirport->setAttribute("LocationCode", $segment['ArrivalAirport']['LocationCode']);
                $arrivalAirport->setAttribute("Terminal", $segment['ArrivalAirport']['Terminal']);
                $flightSegment->appendChild($arrivalAirport);

                $operatingAirline = $message->createElement("ns1:OperatingAirline");
                $operatingAirline->setAttribute("Code", substr($segment['FlightNumber'], 0, 2));
                $flightSegment->appendChild($operatingAirline);

                $originDestinationOption->appendChild($flightSegment);
            }
            $originDestinationOptions->appendChild($originDestinationOption);
        }

        $airItinerary->appendChild($originDestinationOptions);
        $otaAirBookRQ->appendChild($airItinerary);

        $travelerInfo = $message->createElement("ns1:TravelerInfo");

        foreach ($request['passengers'] as $key => $passenger) {

            $airTraveler = $message->createElement("ns1:AirTraveler");
            $airTraveler->setAttribute("BirthDate",  \Carbon\Carbon::parse($passenger['date_of_birth'])->format('Y-m-d\TH:i:s'));
            $airTraveler->setAttribute("PassengerTypeCode", jinnah_passenger()[$passenger['passenger_type']]);


            $personName = $message->createElement("ns1:PersonName");
            $personName->appendChild($message->createElement("ns1:GivenName", strtoupper($passenger['first_name'])));
            $personName->appendChild($message->createElement("ns1:Surname", strtoupper($passenger['last_name'])));

            $personName->appendChild($message->createElement("ns1:NameTitle", booking_passenger_title($passenger)));

            $airTraveler->appendChild($personName);

            $telephone = $message->createElement("ns1:Telephone");
            $telephone->setAttribute("CountryAccessCode", "92");
            $telephone->setAttribute("PhoneNumber", (string) substr($request['contact_number'], 3));
            $airTraveler->appendChild($telephone);

            $address = $message->createElement("ns1:Address");
            $countryName = $message->createElement("ns1:CountryName");
            $countryName->setAttribute("Code", $passenger['nationality']);
            $address->appendChild($countryName);
            $airTraveler->appendChild($address);

            $document = $message->createElement("ns1:Document");
            $document->setAttribute("DocID", $passenger['d_number']);
            $document->setAttribute("DocIssueCountry", "");
            $document->setAttribute("DocType", "PSPT");
            $document->setAttribute("DocHolderNationality", $passenger['nationality']);
            $airTraveler->appendChild($document);

            $travelerRefNumber = $message->createElement("ns1:TravelerRefNumber");
            $travelerRefNumber->setAttribute("RPH", $traveler_reference[$key]);
            $airTraveler->appendChild($travelerRefNumber);

            $travelerInfo->appendChild($airTraveler);
        }

        $otaAirBookRQ->appendChild($travelerInfo);

        $AAAirBookRQExt = $message->createElement('ns2:AAAirBookRQExt');
        $soapBody->appendChild($AAAirBookRQExt);
        $ContactInfo = $message->createElement('ns2:ContactInfo');
        $AAAirBookRQExt->appendChild($ContactInfo);
        $PersonName = $message->createElement('ns2:PersonName');
        $PersonName->appendChild($message->createElement("ns2:Title", $request['passengers'][0]['title']));
        $PersonName->appendChild($message->createElement("ns2:FirstName", strtoupper($request['passengers'][0]['first_name'])));
        $PersonName->appendChild($message->createElement("ns2:LastName", strtoupper($request['passengers'][0]['last_name'])));
        $ContactInfo->appendChild($PersonName);

        $Telephone = $message->createElement('ns2:Telephone');
        $Telephone->appendChild($message->createElement("ns2:PhoneNumber", substr($request['contact_number'], 2)));
        $Telephone->appendChild($message->createElement("ns2:CountryCode", 92));
        $ContactInfo->appendChild($Telephone);

        $auth_user = \Illuminate\Support\Facades\Auth::user();

        $Email = $message->createElement('ns2:Email', (string) $auth_user->email);
        $ContactInfo->appendChild($Email);
        $Address = $message->createElement('ns2:Address');
        $ContactInfo->appendChild($Address);
        $CountryName = $message->createElement('ns2:CountryName');

        $CountryName->appendChild($message->createElement("ns2:CountryCode", 'PK'));
        $Address->appendChild($CountryName);
        $CityName = $message->createElement('ns2:CityName', 'ISB');

        $Address->appendChild($CityName);

        $message->formatOutput = true;
        return $message->saveXML();
    }

    public function retrievePNRRequest($confirmation_id, $auth)
    {

        $message = new DOMDocument('1.0', 'UTF-8');
        $message->preserveWhiteSpace = false;
        $message->formatOutput = true;

        // Envelope
        $envelope = $message->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soap:Envelope");
        $envelope->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:xsd", "http://www.w3.org/2001/XMLSchema");
        $envelope->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        $message->appendChild($envelope);

        // Header
        $header = $message->createElement("soap:Header");
        $security = $message->createElement("wsse:Security");
        $security->setAttribute("soap:mustUnderstand", "1");
        $security->setAttribute("xmlns:wsse", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd");

        $usernameToken = $message->createElement("wsse:UsernameToken");
        $usernameToken->setAttribute("wsu:Id", "UsernameToken-17855236");
        $usernameToken->setAttribute("xmlns:wsu", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd");

        $user = $message->createElement("wsse:Username", $auth['username']);
        $pass = $message->createElement("wsse:Password", $auth['password']);
        $pass->setAttribute("Type", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText");

        $usernameToken->appendChild($user);
        $usernameToken->appendChild($pass);
        $security->appendChild($usernameToken);
        $header->appendChild($security);
        $envelope->appendChild($header);

        // Body
        $body = $message->createElement("soap:Body");
        $body->setAttribute("xmlns:ns1", "http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05");
        $body->setAttribute("xmlns:ns2", "http://www.opentravel.org/OTA/2003/05");

        // OTA_ReadRQ
        $otaReadRQ = $message->createElement("ns2:OTA_ReadRQ");
        $otaReadRQ->setAttribute("EchoToken", '12662148060105253838426');
        $otaReadRQ->setAttribute("PrimaryLangID", "en-us");
        $otaReadRQ->setAttribute("SequenceNmbr", "1");
        $otaReadRQ->setAttribute("TimeStamp", now()->toAtomString());
        $otaReadRQ->setAttribute("Version", "20061.00");

        // POS
        $pos = $message->createElement("ns2:POS");
        $source = $message->createElement("ns2:Source");
        $source->setAttribute("TerminalID", "TestUser/Test Runner");

        $requestorID = $message->createElement("ns2:RequestorID");
        $requestorID->setAttribute("ID", $auth['username']);
        $requestorID->setAttribute("Type", "4");

        $bookingChannel = $message->createElement("ns2:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");

        $source->appendChild($requestorID);
        $source->appendChild($bookingChannel);
        $pos->appendChild($source);
        $otaReadRQ->appendChild($pos);

        // ReadRequests
        $readRequests = $message->createElement("ns2:ReadRequests");
        $readRequest = $message->createElement("ns2:ReadRequest");
        $uniqueID = $message->createElement("ns2:UniqueID");
        $uniqueID->setAttribute("ID", $confirmation_id);
        $uniqueID->setAttribute("Type", "14");

        $readRequest->appendChild($uniqueID);
        $readRequests->appendChild($readRequest);
        $otaReadRQ->appendChild($readRequests);

        // Add OTA_ReadRQ to body
        $body->appendChild($otaReadRQ);

        // Add AAReadRQExt
        $aaReadRQExt = $message->createElement("ns1:AAReadRQExt");
        $loadOptions = $message->createElement("ns1:AALoadDataOptions");

        foreach (['LoadTravelerInfo', 'LoadAirItinery', 'LoadPriceInfoTotals', 'LoadFullFilment'] as $tag) {
            $loadTag = $message->createElement("ns1:$tag", 'true');
            $loadOptions->appendChild($loadTag);
        }

        $aaReadRQExt->appendChild($loadOptions);
        $body->appendChild($aaReadRQExt);

        // Append body to envelope
        $envelope->appendChild($body);

        return $message->saveXML();
    }

    public function issueTicket($auth, $bookingReferenceId, $amount, $transactionIdentifier)
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        // Envelope
        $envelope = $doc->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soapenv:Envelope");
        $envelope->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:xsd", "http://www.w3.org/2001/XMLSchema");
        $envelope->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        $doc->appendChild($envelope);

        // Header
        $header = $doc->createElement("soapenv:Header");
        $security = $doc->createElement("ns2:Security");
        $security->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns2", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd");
        $security->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns3", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd");

        $usernameToken = $doc->createElement("ns2:UsernameToken");
        $usernameToken->setAttribute("ns3:Id", "UsernameToken-17855236");

        $username = $doc->createElement("ns2:Username", $auth['username']);
        $password = $doc->createElement("ns2:Password", $auth['password']);

        $usernameToken->appendChild($username);
        $usernameToken->appendChild($password);
        $security->appendChild($usernameToken);
        $header->appendChild($security);
        $envelope->appendChild($header);

        // Body
        $body = $doc->createElement("soapenv:Body");

        // OTA_AirBookModifyRQ
        $modifyRQ = $doc->createElement("ns8:OTA_AirBookModifyRQ");
        $modifyRQ->setAttribute("EchoToken", uniqid());
        $modifyRQ->setAttribute("SequenceNmbr", "1");
        $modifyRQ->setAttribute("TransactionIdentifier", (string) $transactionIdentifier);
        $modifyRQ->setAttribute("Version", "20061.0");
        $modifyRQ->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns8", "http://www.opentravel.org/OTA/2003/05");
        $modifyRQ->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns", "http://www.isaaviation.com/thinair/webservices/api/airinventory");
        $modifyRQ->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns2", "http://www.isaaviation.com/thinair/webservices/api/airreservation");
        $modifyRQ->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns4", "http://www.isaaviation.com/thinair/webservices/api/commons");
        $modifyRQ->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns3", "http://www.isaaviation.com/thinair/webservices/api/airschedules");
        $modifyRQ->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns5", "http://www.isaaviation.com/thinair/webservices/api/eticketupdate");
        $modifyRQ->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns6", "http://www.isaaviation.com/thinair/webservices/api/updatepfs");
        $modifyRQ->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns7", "http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05");

        // POS
        $pos = $doc->createElement("ns8:POS");
        $source = $doc->createElement("ns8:Source");
        $requestorID = $doc->createElement("ns8:RequestorID");
        $requestorID->setAttribute("ID", $auth['username']);
        $requestorID->setAttribute("Type", "4");
        $bookingChannel = $doc->createElement("ns8:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");

        $source->appendChild($requestorID);
        $source->appendChild($bookingChannel);
        $pos->appendChild($source);
        $modifyRQ->appendChild($pos);

        // AirBookModifyRQ with Payment
        $airBookModify = $doc->createElement("ns8:AirBookModifyRQ");
        $airBookModify->setAttribute("ModificationType", "14");

        $fulfillment = $doc->createElement("ns8:Fulfillment");
        $paymentDetails = $doc->createElement("ns8:PaymentDetails");
        $paymentDetail = $doc->createElement("ns8:PaymentDetail");
        $directBill = $doc->createElement("ns8:DirectBill");
        $companyName = $doc->createElement("ns8:CompanyName");
        $companyName->setAttribute("Code", (string)"AABISB8397");

        $directBill->appendChild($companyName);
        $paymentDetail->appendChild($directBill);

        $paymentAmount = $doc->createElement("ns8:PaymentAmount");
        $paymentAmount->setAttribute("Amount", number_format($amount, 2, '.', ''));
        $paymentAmount->setAttribute("CurrencyCode", 'PKR');
        $paymentAmount->setAttribute("DecimalPlaces", "2");
        $paymentDetail->appendChild($paymentAmount);
        $paymentDetails->appendChild($paymentDetail);
        $fulfillment->appendChild($paymentDetails);
        $airBookModify->appendChild($fulfillment);

        $bookingReference = $doc->createElement("ns8:BookingReferenceID");
        $bookingReference->setAttribute("ID", $bookingReferenceId);
        $bookingReference->setAttribute("Type", "14");
        $airBookModify->appendChild($bookingReference);

        $modifyRQ->appendChild($airBookModify);
        $body->appendChild($modifyRQ);

        // Add AAirBookModifyRQExt
        $aaExt = $doc->createElement("ns7:AAAirBookModifyRQExt");
        $aaExt->setAttributeNS("http://www.w3.org/2000/xmlns/", "xmlns:ns7", "http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05");

        $loadOptions = $doc->createElement("ns7:AALoadDataOptions");

        foreach (['LoadTravelerInfo', 'LoadAirItinery', 'LoadPriceInfoTotals', 'LoadFullFilment'] as $tag) {
            $loadOptions->appendChild($doc->createElement("ns7:$tag", 'true'));
        }

        $aaExt->appendChild($loadOptions);
        $body->appendChild($aaExt);

        $envelope->appendChild($body);

        return $doc->saveXML();
    }
}
