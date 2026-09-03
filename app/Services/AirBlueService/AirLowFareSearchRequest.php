<?php

namespace App\Services\AirBlueService;

use App\Services\AirBlueService\MakeRequest;

class AirLowFareSearchRequest extends MakeRequest
{
    public function makeRequest($request, $auth)
    {
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $body = $this->message->createElement('Body');
        $airLowFareSearch = $this->message->createElementNS('http://zapways.com/air/ota/3.0', 'AirLowFareSearch');
        $airLowFareSearchRQ = $this->message->createElement('airLowFareSearchRQ');
        $airLowFareSearchRQ->setAttribute('EchoToken', parent::ECHOTOKEN);
        $airLowFareSearchRQ->setAttribute('Target', parent::TARGET);
        $airLowFareSearchRQ->setAttribute('Version', parent::VERSION);
        $airLowFareSearchRQ->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.opentravel.org/OTA/2003/05');
        $this->getPosTag($airLowFareSearchRQ, $auth);
        $this->setRoutes($request, $airLowFareSearchRQ);
        $this->setPaxs($request, $airLowFareSearchRQ);
        $airLowFareSearch->appendChild($airLowFareSearchRQ);
        $body->appendChild($airLowFareSearch);
        $envelope->appendChild($body);
        $this->message->appendChild($envelope);
        return $this->message->saveXML();
    }

    public function setRoutes($request, $parentTag)
    {
        if ($request['route_type'] == 'MULTICITY') {
            foreach ($request['legs'] as $key => $leg) {
                $this->setRoute($leg['origin'], $leg['destination'], $leg['departure_date'], (string)($key + 1), $parentTag);
            }
        } else {
            $this->setRoute($request['origin'], $request['destination'], $request['departure_date'], '1', $parentTag);
            if ($request['route_type'] == 'RETURN') {
                $this->setRoute($request['destination'], $request['origin'], $request['return_date'], '2', $parentTag);
            }
        }
    }

    public function setRoute($origin, $destination, $date, $RPH, $parentTag)
    {
        $originDestinationInformation = $this->message->createElement('OriginDestinationInformation');
        $originDestinationInformation->setAttribute('RPH', $RPH);
        $departureDateTimeElement = $this->message->createElement('DepartureDateTime', $date . 'T00:00:00Z');
        $originLocation = $this->message->createElement('OriginLocation');
        $originLocation->setAttribute('LocationCode', $origin);
        $destinationLocation = $this->message->createElement('DestinationLocation');
        $destinationLocation->setAttribute('LocationCode', $destination);
        $originDestinationInformation->appendChild($departureDateTimeElement);
        $originDestinationInformation->appendChild($originLocation);
        $originDestinationInformation->appendChild($destinationLocation);
        $parentTag->appendChild($originDestinationInformation);
    }

    public function setPaxs($request, $parentTag)
    {
        $travelerInfoSummary = $this->message->createElement('TravelerInfoSummary');
        $airTravelerAvail = $this->message->createElement('AirTravelerAvail');
        $passengerTypeQuantityADT = $this->message->createElement('PassengerTypeQuantity');
        $passengerTypeQuantityADT->setAttribute('Code', 'ADT');
        $passengerTypeQuantityADT->setAttribute('Quantity', $request['traveler_count']['adult_count']);
        $airTravelerAvail->appendChild($passengerTypeQuantityADT);
        if ($request['traveler_count']['child_count'] > 0) {
            $passengerTypeQuantityCHD = $this->message->createElement('PassengerTypeQuantity');
            $passengerTypeQuantityCHD->setAttribute('Code', 'CHD');
            $passengerTypeQuantityCHD->setAttribute('Quantity', $request['traveler_count']['child_count']);
            $airTravelerAvail->appendChild($passengerTypeQuantityCHD);
        }

        if ($request['traveler_count']['infant_count'] > 0) {
            $passengerTypeQuantityINF = $this->message->createElement('PassengerTypeQuantity');
            $passengerTypeQuantityINF->setAttribute('Code', 'INF');
            $passengerTypeQuantityINF->setAttribute('Quantity', $request['traveler_count']['infant_count']);
            $airTravelerAvail->appendChild($passengerTypeQuantityINF);
        }
        $travelerInfoSummary->appendChild($airTravelerAvail);
        $parentTag->appendChild($travelerInfoSummary);
    }
    public function confirmBookingRequest($data, $request, $auth)
    {
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $body = $this->message->createElement('Body');
        $airLowFareSearch = $this->message->createElementNS('http://zapways.com/air/ota/3.0', 'AirBook');
        $airLowFareSearchRQ = $this->message->createElement('airBookRQ');
        $airLowFareSearchRQ->setAttribute('Target', parent::TARGET);
        $airLowFareSearchRQ->setAttribute('Version', parent::VERSION);
        $airLowFareSearchRQ->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.opentravel.org/OTA/2003/05');
        $this->getPosTag($airLowFareSearchRQ, $auth);

        $itineraries = arrayConversion($data);
        unset($itineraries['confirmation_id']);

        if($itineraries[0]['request']['route_type'] == 'RETURN') {
            $this->returnAirItinerary($itineraries, $airLowFareSearchRQ);
            $this->returnPriceInfo($itineraries, $airLowFareSearchRQ);
        }else{


            if ($itineraries[0]['request']['route_type'] == 'MULTICITY') {

                $AirItinerary = $this->message->createElement('AirItinerary');
                $airLowFareSearchRQ->appendChild($AirItinerary);

                $originDestinationOptions = $this->message->createElement('OriginDestinationOptions');
                $AirItinerary->appendChild($originDestinationOptions);

                foreach ($itineraries as $first_data) {

                    $itinerary = $first_data['segments'];
                    $originDestinationOption = $this->message->createElement('OriginDestinationOption');
                    $originDestinationOption->setAttribute('RPH', $itinerary['RPH']);
                    $originDestinationOptions->appendChild($originDestinationOption);

                    $itinerary_Segment = $itinerary['FlightSegment'];
                    $flightSegment = $this->message->createElement('FlightSegment');
                    $flightSegment->setAttribute('DepartureDateTime', $itinerary_Segment['DepartureDateTime']);
                    $flightSegment->setAttribute('ArrivalDateTime', $itinerary_Segment['ArrivalDateTime']);
                    $flightSegment->setAttribute('StopQuantity', $itinerary_Segment['StopQuantity']);
                    $flightSegment->setAttribute('ResBookDesigCode', $itinerary_Segment['ResBookDesigCode']);
                    $flightSegment->setAttribute('RPH', $itinerary_Segment['RPH']);
                    $flightSegment->setAttribute('FlightNumber', $itinerary_Segment['FlightNumber']);
                    $flightSegment->setAttribute('Status', $itinerary_Segment['Status']);
                    $originDestinationOption->appendChild($flightSegment);

                    $departureAirport = $this->message->createElement("DepartureAirport");
                    $departureAirport->setAttribute('LocationCode', $itinerary_Segment['DepartureAirport']['LocationCode']);
                    $flightSegment->appendChild($departureAirport);

                    $arrivalAirport = $this->message->createElement('ArrivalAirport');
                    $arrivalAirport->setAttribute('LocationCode', $itinerary_Segment['ArrivalAirport']['LocationCode']);
                    $flightSegment->appendChild($arrivalAirport);

                    $operatingAirline = $this->message->createElement('OperatingAirline');
                    $operatingAirline->setAttribute('Code', $itinerary_Segment['OperatingAirline']['Code']);
                    $flightSegment->appendChild($operatingAirline);

                    $equipment = $this->message->createElement('Equipment');
                    $equipment->setAttribute('AirEquipType', $itinerary_Segment['Equipment']['AirEquipType']);
                    $flightSegment->appendChild($equipment);

                    $marketingAirline = $this->message->createElement('MarketingAirline');
                    $marketingAirline->setAttribute('Code', $itinerary_Segment['MarketingAirline']['Code']);
                    $flightSegment->appendChild($marketingAirline);
                }


                

                $priceInfo = $this->message->createElement('PriceInfo');
                $airLowFareSearchRQ->appendChild($priceInfo);

                $ptcFareBreakdowns = $this->message->createElement('PTC_FareBreakdowns');
                $priceInfo->appendChild($ptcFareBreakdowns);

                foreach ($itineraries as $sec_data) {

                    $fare_breakdown = $sec_data['fare_info']['PTC_FareBreakdowns']['PTC_FareBreakdown'];
                    foreach (arrayConversion($fare_breakdown) as $fare_breakdown) {
                        $ptcFareBreakdown = $this->message->createElement('PTC_FareBreakdown');
                        $ptcFareBreakdowns->appendChild($ptcFareBreakdown);

                        // Passenger Type Quantity
                        $passengerTypeQuantity = $this->message->createElement('PassengerTypeQuantity');
                        $passengerTypeQuantity->setAttribute('Code', $fare_breakdown['PassengerTypeQuantity']['Code']);
                        $passengerTypeQuantity->setAttribute('Quantity', $fare_breakdown['PassengerTypeQuantity']['Quantity']);
                        $ptcFareBreakdown->appendChild($passengerTypeQuantity);

                        // Passenger Fare
                        $passengerFare = $this->message->createElement('PassengerFare');
                        $ptcFareBreakdown->appendChild($passengerFare);

                        // Base Fare
                        $baseFare = $this->message->createElement('BaseFare');
                        $baseFare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['BaseFare']['CurrencyCode']);
                        $baseFare->setAttribute('Amount', $fare_breakdown['PassengerFare']['BaseFare']['Amount']);
                        $passengerFare->appendChild($baseFare);

                        // Taxes
                        $taxes = $this->message->createElement('Taxes');
                        $taxes->setAttribute("Amount", $fare_breakdown['PassengerFare']['Taxes']['Amount']);
                        $passengerFare->appendChild($taxes);

                        foreach ($fare_breakdown['PassengerFare']['Taxes']['Tax'] as $tax_breakdown) {
                            $tax = $this->message->createElement('Tax');
                            $tax->setAttribute('TaxCode', $tax_breakdown['TaxCode']);
                            $tax->setAttribute('CurrencyCode', $tax_breakdown['CurrencyCode']);
                            $tax->setAttribute('Amount', $tax_breakdown['Amount']);
                            $taxes->appendChild($tax);
                        }

                        // Fees
                        if (isset($fare_breakdown['PassengerFare']['Fees'])) {
                            $fees = $this->message->createElement('Fees');
                            $fees->setAttribute("Amount", $fare_breakdown['PassengerFare']['Fees']['Amount']);
                            $passengerFare->appendChild($fees);

                            foreach (arrayConversion($fare_breakdown['PassengerFare']['Fees']['Fee']) as $fee_breakdown) {
                                $fee = $this->message->createElement('Fee');
                                $fee->setAttribute('FeeCode', $fee_breakdown['FeeCode']);
                                $fee->setAttribute('CurrencyCode', $fee_breakdown['CurrencyCode']);
                                $fee->setAttribute('Amount', $fee_breakdown['Amount']);
                                $fees->appendChild($fee);
                            }
                        }

                        // Total Fare
                        $totalFare = $this->message->createElement('TotalFare');
                        $totalFare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['TotalFare']['CurrencyCode']);
                        $totalFare->setAttribute('Amount', $fare_breakdown['PassengerFare']['TotalFare']['Amount']);
                        $passengerFare->appendChild($totalFare);

                        // Fare Info
                        foreach (arrayConversion($fare_breakdown['FareInfo']) as $fareInfoBreakDown) {
                            $fare_info = $this->message->createElement('FareInfo');
                            $ptcFareBreakdown->appendChild($fare_info);

                            // Departure Date
                            $departure_date = $this->message->createElement('DepartureDate', $fareInfoBreakDown['DepartureDate']);
                            $fare_info->appendChild($departure_date);

                            // Departure Airport
                            $departure_airport = $this->message->createElement('DepartureAirport');
                            $departure_airport->setAttribute('LocationCode', $fareInfoBreakDown['DepartureAirport']['LocationCode']);
                            $fare_info->appendChild($departure_airport);

                            // Arrival Airport
                            $arrival_airport = $this->message->createElement('ArrivalAirport');
                            $arrival_airport->setAttribute('LocationCode', $fareInfoBreakDown['ArrivalAirport']['LocationCode']);
                            $fare_info->appendChild($arrival_airport);

                            // Fare Basis Code if exists
                            if (isset($fareInfoBreakDown['FareInfo'])) {
                                $FareInfo = $this->message->createElement('FareInfo');
                                $FareInfo->setAttribute('FareBasisCode', $fareInfoBreakDown['FareInfo']['FareBasisCode']);
                                $fare_info->appendChild($FareInfo);
                            }

                            // Rule Info if exists
                            if (isset($fareInfoBreakDown['RuleInfo'])) {
                                $RuleInfo = $this->message->createElement('RuleInfo');
                                $fare_info->appendChild($RuleInfo);

                                $ChargesRules = $this->message->createElement('ChargesRules');
                                $RuleInfo->appendChild($ChargesRules);

                                $VoluntaryChanges = $this->message->createElement('VoluntaryChanges');
                                $ChargesRules->appendChild($VoluntaryChanges);

                                foreach ($fareInfoBreakDown['RuleInfo']['ChargesRules']['VoluntaryChanges']['Penalty'] as $penalty_obj) {
                                    $Penalty = $this->message->createElement('Penalty');
                                    $Penalty->setAttribute("HoursBeforeDeparture", $penalty_obj['HoursBeforeDeparture']);
                                    $Penalty->setAttribute("CurrencyCode", $penalty_obj['CurrencyCode']);
                                    $Penalty->setAttribute("Amount", $penalty_obj['Amount']);
                                    $VoluntaryChanges->appendChild($Penalty);
                                }
                            }

                            // Passenger Fare details if exists
                            if (isset($fareInfoBreakDown['PassengerFare'])) {
                                $passenger_fare = $this->message->createElement('PassengerFare');
                                $fare_info->appendChild($passenger_fare);

                                // Base Fare
                                if (isset($fareInfoBreakDown['PassengerFare']['BaseFare'])) {
                                    $base_fare = $this->message->createElement('BaseFare');
                                    $base_fare->setAttribute('CurrencyCode', $fareInfoBreakDown['PassengerFare']['BaseFare']['CurrencyCode']);
                                    $base_fare->setAttribute('Amount', $fareInfoBreakDown['PassengerFare']['BaseFare']['Amount']);
                                    $passenger_fare->appendChild($base_fare);
                                }

                                // Taxes
                                if (isset($fareInfoBreakDown['PassengerFare']['Taxes'])) {
                                    $taxes = $this->message->createElement('Taxes');
                                    $taxes->setAttribute("Amount", $fareInfoBreakDown['PassengerFare']['Taxes']['Amount']);
                                    $passenger_fare->appendChild($taxes);

                                    foreach ($fareInfoBreakDown['PassengerFare']['Taxes']['Tax'] as $first_tax) {
                                        $tax = $this->message->createElement('Tax');
                                        $tax->setAttribute('TaxCode', $first_tax['TaxCode']);
                                        $tax->setAttribute('CurrencyCode', $first_tax['CurrencyCode']);
                                        $tax->setAttribute('Amount', $first_tax['Amount']);
                                        $taxes->appendChild($tax);
                                    }
                                }

                                // Fees
                                if (isset($fareInfoBreakDown['PassengerFare']['Fees'])) {
                                    $fees = $this->message->createElement('Fees');
                                    $fees->setAttribute('Amount', $fareInfoBreakDown['PassengerFare']['Fees']['Amount']);
                                    $passenger_fare->appendChild($fees);

                                    foreach (arrayConversion($fareInfoBreakDown['PassengerFare']['Fees']['Fee']) as $first_fee) {
                                        $fee = $this->message->createElement('Fee');
                                        $fee->setAttribute('FeeCode', $first_fee['FeeCode']);
                                        $fee->setAttribute('CurrencyCode', $first_fee['CurrencyCode']);
                                        $fee->setAttribute('Amount', $first_fee['Amount']);
                                        $fees->appendChild($fee);
                                    }
                                }

                                // Total Fare
                                if (isset($fareInfoBreakDown['PassengerFare']['TotalFare'])) {
                                    $total_fare = $this->message->createElement('TotalFare');
                                    $total_fare->setAttribute('CurrencyCode', $fareInfoBreakDown['PassengerFare']['TotalFare']['CurrencyCode']);
                                    $total_fare->setAttribute('Amount', $fareInfoBreakDown['PassengerFare']['TotalFare']['Amount']);
                                    $passenger_fare->appendChild($total_fare);
                                }

                                // Baggage Allowance
                                if (isset($fareInfoBreakDown['PassengerFare']['FareBaggageAllowance'])) {
                                    $FareBaggageAllowance = $this->message->createElement('FareBaggageAllowance');
                                    $FareBaggageAllowance->setAttribute("UnitOfMeasureQuantity", $fareInfoBreakDown['PassengerFare']['FareBaggageAllowance']['UnitOfMeasureQuantity']);
                                    $FareBaggageAllowance->setAttribute("UnitOfMeasure", $fareInfoBreakDown['PassengerFare']['FareBaggageAllowance']['UnitOfMeasure']);
                                    $passenger_fare->appendChild($FareBaggageAllowance);
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($itineraries as $first_data) {
                    $this->airItinerary($first_data, $airLowFareSearchRQ);
                }

                foreach ($itineraries as $sec_data) {
                    $this->priceInfo($sec_data, $airLowFareSearchRQ);
                }
            }
        
        }
        $this->travelerInfo($request, $airLowFareSearchRQ);
        $airLowFareSearch->appendChild($airLowFareSearchRQ);
        $body->appendChild($airLowFareSearch);
        $envelope->appendChild($body);
        $this->message->appendChild($envelope);
        return $this->message->saveXML();
    }

    public function readBooking($auth, $booking_pnr)
    {
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $body = $this->message->createElement('Body');
        $airLowFareSearch = $this->message->createElementNS('http://zapways.com/air/ota/3.0', 'Read');
        $airLowFareSearchRQ = $this->message->createElement('readRQ');

        $airLowFareSearchRQ->setAttribute('Target', parent::TARGET);
        $airLowFareSearchRQ->setAttribute('Version', parent::VERSION);
        $airLowFareSearchRQ->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns',
            'http://www.opentravel.org/OTA/2003/05'
        );

        $this->getPosTag($airLowFareSearchRQ, $auth);

        $UniqueID = $this->message->createElement('UniqueID');
        $UniqueID->setAttribute('ID', $booking_pnr);

        $UniqueID->appendChild($this->message->createTextNode(''));

        $airLowFareSearchRQ->appendChild($UniqueID);

        $airLowFareSearch->appendChild($airLowFareSearchRQ);
        $body->appendChild($airLowFareSearch);
        $envelope->appendChild($body);
        $this->message->appendChild($envelope);

        return $this->message->saveXML();
    }

    public function seatMap($pnr_meta,$auth)
    {
        $this->message = new \DOMDocument('1.0', 'UTF-8');
        $this->message->formatOutput = true;
    
        // === Envelope ===
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $this->message->appendChild($envelope);
    
        // === Header ===
        $header = $this->message->createElement('Header');
        $envelope->appendChild($header);
    
        // === Body ===
        $body = $this->message->createElement('Body');
        $envelope->appendChild($body);
    
        // === AirSeatMap ===
        $airSeatMap = $this->message->createElementNS('http://zapways.com/air/ota/3.0', 'AirSeatMap');
        $body->appendChild($airSeatMap);
    
        // === airSeatMapRQ ===
        $airSeatMapRQ = $this->message->createElementNS('http://www.opentravel.org/OTA/2003/05', 'airSeatMapRQ');
        $airSeatMapRQ->setAttribute('EchoToken', parent::ECHOTOKEN);
        $airSeatMapRQ->setAttribute('Target', parent::TARGET);
        $airSeatMapRQ->setAttribute('Version', parent::VERSION);
        $airSeatMap->appendChild($airSeatMapRQ);
    
        // === POS ===
        $POS = $this->message->createElement('POS');
        $airSeatMapRQ->appendChild($POS);
    
        $Source = $this->message->createElement('Source');
        $Source->setAttribute('ERSP_UserID', $auth['ERSP_UserID']);
        $POS->appendChild($Source);
    
        $RequestorID = $this->message->createElement('RequestorID');
        $RequestorID->setAttribute('Type', '29');
        $RequestorID->setAttribute('ID', $auth['ID']);
        $RequestorID->setAttribute('MessagePassword', $auth['MessagePassword']);
        $Source->appendChild($RequestorID);
    
        // === SeatMapRequests ===
        $seatMapRequests = $this->message->createElement('SeatMapRequests');
        $airSeatMapRQ->appendChild($seatMapRequests);
    
        foreach(arrayConversion($pnr_meta['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']) as $originDestinationOption) {
            $originDestinationOption = $originDestinationOption['FlightSegment'];

            $seatMapRequest = $this->message->createElement('SeatMapRequest');
            $seatMapRequests->appendChild($seatMapRequest);
        
            // === FlightSegmentInfo ===
            $flightSegmentInfo = $this->message->createElement('FlightSegmentInfo');
            $flightSegmentInfo->setAttribute('DepartureDateTime', $originDestinationOption['DepartureDateTime']);
            $flightSegmentInfo->setAttribute('FlightNumber', $originDestinationOption['FlightNumber']);
            $flightSegmentInfo->setAttribute('FareType', $originDestinationOption['FareType']);
            $flightSegmentInfo->setAttribute('ResBookDesigCode', $originDestinationOption['ResBookDesigCode']);
            $flightSegmentInfo->setAttribute('CabinClass', $originDestinationOption['CabinClass']);
            $seatMapRequest->appendChild($flightSegmentInfo);
        
            // === DepartureAirport ===
            $departureAirport = $this->message->createElement('DepartureAirport');
            $departureAirport->setAttribute('LocationCode', $originDestinationOption['DepartureAirport']['LocationCode']);
            $flightSegmentInfo->appendChild($departureAirport);
        
            // === ArrivalAirport ===
            $arrivalAirport = $this->message->createElement('ArrivalAirport');
            $arrivalAirport->setAttribute('LocationCode', $originDestinationOption['ArrivalAirport']['LocationCode']);
            $flightSegmentInfo->appendChild($arrivalAirport);
        
            // === OperatingAirline ===
            $operatingAirline = $this->message->createElement('OperatingAirline');
            $operatingAirline->setAttribute('Code', $originDestinationOption['OperatingAirline']['Code']);
            $operatingAirline->setAttribute('FlightNumber', $originDestinationOption['FlightNumber']);
            $flightSegmentInfo->appendChild($operatingAirline);
        }
        // === BookingReferenceID ===
        $bookingReferenceID = $this->message->createElement('BookingReferenceID');
        $bookingReferenceID->setAttribute('Instance', $pnr_meta['pnr_info'][0]['Instance']);
        $bookingReferenceID->setAttribute('ID', $pnr_meta['pnr_info'][0]['ID']);
        $airSeatMapRQ->appendChild($bookingReferenceID);
    
        // === Output XML ===
        return $this->message->saveXML();
    }
    
    public function ancillary($pnr_meta, $auth)
    {
        $this->message = new \DOMDocument('1.0', 'UTF-8');
        $this->message->formatOutput = true;

        // === Envelope and Body ===
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $body = $this->message->createElement('Body');

        // === Root AirAncillaryItems tag ===
        $airAncillaryItems = $this->message->createElementNS('http://zapways.com/air/ota/3.0', 'AirAncillaryItems');

        // === airAncillaryItemsRQ ===
        $airAncillaryItemsRQ = $this->message->createElement('airAncillaryItemsRQ');
        $airAncillaryItemsRQ->setAttribute('EchoToken', parent::ECHOTOKEN);
        $airAncillaryItemsRQ->setAttribute('Target', parent::TARGET);
        $airAncillaryItemsRQ->setAttribute('Version', parent::VERSION);

        // Namespace declaration (same pattern as seatMapRequest)
        $airAncillaryItemsRQ->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns',
            'http://www.opentravel.org/OTA/2003/05'
        );

        // === POS Tag ===
        $POS = $this->message->createElement('POS');
        $Source = $this->message->createElement('Source');
        $Source->setAttribute('ERSP_UserID', $auth['ERSP_UserID']);

        $RequestorID = $this->message->createElement('RequestorID');
        $RequestorID->setAttribute('Type', '29');
        $RequestorID->setAttribute('ID', $auth['ID']);
        $RequestorID->setAttribute('MessagePassword', $auth['MessagePassword']);

        $Source->appendChild($RequestorID);
        $POS->appendChild($Source);
        $airAncillaryItemsRQ->appendChild($POS);

        // === AncillaryItemRequests ===
        $ancillaryItemRequests = $this->message->createElement('AncillaryItemRequests');
        $airAncillaryItemsRQ->appendChild($ancillaryItemRequests);

        foreach (arrayConversion($pnr_meta['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']) as $originDestinationOption) {

            $originDestinationOption = $originDestinationOption['FlightSegment'];

            // === AncillaryItemRequest ===
            $ancillaryItemRequest = $this->message->createElement('AncillaryItemRequest');
            $ancillaryItemRequests->appendChild($ancillaryItemRequest);

            // === FlightSegmentInfo ===
            $flightSegmentInfo = $this->message->createElement('FlightSegmentInfo');
            $flightSegmentInfo->setAttribute('DepartureDateTime', $originDestinationOption['DepartureDateTime']);
            $flightSegmentInfo->setAttribute('FlightNumber', $originDestinationOption['FlightNumber']);
            $flightSegmentInfo->setAttribute('FareType', $originDestinationOption['FareType']);
            $flightSegmentInfo->setAttribute('ResBookDesigCode', $originDestinationOption['ResBookDesigCode']);
            $flightSegmentInfo->setAttribute('CabinClass', $originDestinationOption['CabinClass']);

            // === Departure Airport ===
            $departureAirport = $this->message->createElement('DepartureAirport');
            $departureAirport->setAttribute('LocationCode', $originDestinationOption['DepartureAirport']['LocationCode']);

            // === Arrival Airport ===
            $arrivalAirport = $this->message->createElement('ArrivalAirport');
            $arrivalAirport->setAttribute('LocationCode', $originDestinationOption['ArrivalAirport']['LocationCode']);

            // === Operating Airline ===
            $operatingAirline = $this->message->createElement('OperatingAirline');
            $operatingAirline->setAttribute('Code', $originDestinationOption['OperatingAirline']['Code']);
            $operatingAirline->setAttribute('FlightNumber', $originDestinationOption['FlightNumber']);

            // Combine flight info
            $flightSegmentInfo->appendChild($departureAirport);
            $flightSegmentInfo->appendChild($arrivalAirport);
            $flightSegmentInfo->appendChild($operatingAirline);
            $ancillaryItemRequest->appendChild($flightSegmentInfo);
        }

        // === BookingReferenceID ===
        $bookingReferenceID = $this->message->createElement('BookingReferenceID');
        $bookingReferenceID->setAttribute('Instance', $pnr_meta['pnr_info'][0]['Instance']);
        $bookingReferenceID->setAttribute('ID', $pnr_meta['pnr_info'][0]['ID']);
        $airAncillaryItemsRQ->appendChild($bookingReferenceID);

        // === Assemble the XML ===
        $airAncillaryItems->appendChild($airAncillaryItemsRQ);
        $body->appendChild($airAncillaryItems);
        $envelope->appendChild($body);
        $this->message->appendChild($envelope);

        return $this->message->saveXML();
    }

    public function seatAddOrUpdateRequest($pnr_meta, $auth, $seatData)
    {
        // dd($pnr_meta, $auth, $seatData);
        $this->message = new \DOMDocument('1.0', 'UTF-8');
        $this->message->formatOutput = true;

        // Envelope and Body
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $body = $this->message->createElement('Body');

        // Root AirBookModify element
        $airBookModify = $this->message->createElementNS('http://zapways.com/air/ota/3.0', 'AirBookModify');

        // airBookModifyRQ root
        $airBookModifyRQ = $this->message->createElement('airBookModifyRQ');
        $airBookModifyRQ->setAttribute('Target', parent::TARGET);
        $airBookModifyRQ->setAttribute('Version', parent::VERSION);

        // Add XMLNS for OTA
        $airBookModifyRQ->setAttributeNS('http://www.w3.org/2000/xmlns/','xmlns','http://www.opentravel.org/OTA/2003/05');

        /**
         * POS Section
         */
        $POS = $this->message->createElement('POS');
        $Source = $this->message->createElement('Source');
        $Source->setAttribute('ERSP_UserID', $auth['ERSP_UserID']);

        $RequestorID = $this->message->createElement('RequestorID');
        $RequestorID->setAttribute('Type', '29');
        $RequestorID->setAttribute('ID', $auth['ID']);
        $RequestorID->setAttribute('MessagePassword', $auth['MessagePassword']);

        $Source->appendChild($RequestorID);
        $POS->appendChild($Source);
        $airBookModifyRQ->appendChild($POS);

        /**
         * AirBookModifyRQ Section
         */
        $AirBookModifyRQ = $this->message->createElement('AirBookModifyRQ');
        $AirBookModifyRQ->setAttribute('ModificationType', '5');

        $TravelerInfo = $this->message->createElement('TravelerInfo');
        $SpecialReqDetails = $this->message->createElement('SpecialReqDetails');
        $SeatRequests = $this->message->createElement('SeatRequests');

        // Loop through seat data
        foreach ($seatData as $seat) {

            foreach($seat['travelers'] as $traveler){

                preg_match('/(\d+)([A-Za-z]+)/', $traveler['seat_code'], $matches);
                $rowNumber = $matches[1];
                $seatNumber = $matches[2];

                $SeatRequest = $this->message->createElement('SeatRequest');
                $SeatRequest->setAttribute('SeatNumber', $seatNumber);
                $SeatRequest->setAttribute('RowNumber', $rowNumber);
                $SeatRequest->setAttribute('TravelerRefNumberRPHList', $traveler['reference']);
                $SeatRequest->setAttribute('FlightRefNumberRPHList', $traveler['flight_segment']);

                $SeatRequests->appendChild($SeatRequest);
            }
        }

        $SpecialReqDetails->appendChild($SeatRequests);
        $TravelerInfo->appendChild($SpecialReqDetails);
        $AirBookModifyRQ->appendChild($TravelerInfo);
        $airBookModifyRQ->appendChild($AirBookModifyRQ);

        /**
         * AirReservation > BookingReferenceID
         */
        $AirReservation = $this->message->createElement('AirReservation');
        $BookingReferenceID = $this->message->createElement('BookingReferenceID');
        $BookingReferenceID->setAttribute('Instance', $pnr_meta['pnr_info'][0]['Instance']);
        $BookingReferenceID->setAttribute('ID', $pnr_meta['pnr_info'][0]['ID']);
        $AirReservation->appendChild($BookingReferenceID);
        $airBookModifyRQ->appendChild($AirReservation);

        /**
         * Combine all
         */
        $airBookModify->appendChild($airBookModifyRQ);
        $body->appendChild($airBookModify);
        $envelope->appendChild($body);
        $this->message->appendChild($envelope);

        return $this->message->saveXML();
    }

    public function ancillaryAddOrUpdateRequest($pnr_meta, $auth, $ancillaryItems)
    {
        $this->message = new \DOMDocument('1.0', 'UTF-8');
        $this->message->formatOutput = true;

        // Envelope and Body
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $body = $this->message->createElement('Body');

        // Root AirBookModify element
        $airBookModify = $this->message->createElementNS('http://zapways.com/air/ota/3.0', 'AirBookModify');

        // airBookModifyRQ root
        $airBookModifyRQ = $this->message->createElement('airBookModifyRQ');
        $airBookModifyRQ->setAttribute('Target', parent::TARGET);
        $airBookModifyRQ->setAttribute('Version', parent::VERSION);

        // Add XMLNS for OTA
        $airBookModifyRQ->setAttributeNS('http://www.w3.org/2000/xmlns/','xmlns','http://www.opentravel.org/OTA/2003/05');
        /**
         * POS Section
         */
        $POS = $this->message->createElement('POS');
        $Source = $this->message->createElement('Source');
        $Source->setAttribute('ERSP_UserID', $auth['ERSP_UserID']);

        $RequestorID = $this->message->createElement('RequestorID');
        $RequestorID->setAttribute('Type', '29');
        $RequestorID->setAttribute('ID', $auth['ID']);
        $RequestorID->setAttribute('MessagePassword', $auth['MessagePassword']);

        $Source->appendChild($RequestorID);
        $POS->appendChild($Source);
        $airBookModifyRQ->appendChild($POS);

        /**
         * AirBookModifyRQ Section
         */
        $AirBookModifyRQ = $this->message->createElement('AirBookModifyRQ');
        $AirBookModifyRQ->setAttribute('ModificationType', '5');

        $TravelerInfo = $this->message->createElement('TravelerInfo');
        $SpecialReqDetails = $this->message->createElement('SpecialReqDetails');
        $SpecialServiceRequests = $this->message->createElement('SpecialServiceRequests');
        
        foreach ($ancillaryItems as $item) {


            foreach ($item['travelers'] as $value){
                $SpecialServiceRequest = $this->message->createElement('SpecialServiceRequest');
                $SpecialServiceRequest->setAttribute('ItemCode', array_key_exists('wheel_chair_code', $value) ? $value['wheel_chair_code'] : $value['baggage_code']);
                $SpecialServiceRequest->setAttribute('TravelerRefNumberRPHList', $value['reference']);
                $SpecialServiceRequest->setAttribute('FlightRefNumberRPHList', $value['flight_segment']);
    
                $SpecialServiceRequests->appendChild($SpecialServiceRequest);
            }
        }

        $SpecialReqDetails->appendChild($SpecialServiceRequests);
        $TravelerInfo->appendChild($SpecialReqDetails);
        $AirBookModifyRQ->appendChild($TravelerInfo);
        $airBookModifyRQ->appendChild($AirBookModifyRQ);

        /**
         * AirReservation > BookingReferenceID
         */
        $AirReservation = $this->message->createElement('AirReservation');
        $BookingReferenceID = $this->message->createElement('BookingReferenceID');
        $BookingReferenceID->setAttribute('Instance', $pnr_meta['pnr_info'][0]['Instance']);
        $BookingReferenceID->setAttribute('ID', $pnr_meta['pnr_info'][0]['ID']);
        $AirReservation->appendChild($BookingReferenceID);
        $airBookModifyRQ->appendChild($AirReservation);

        /**
         * Combine all
         */
        $airBookModify->appendChild($airBookModifyRQ);
        $body->appendChild($airBookModify);
        $envelope->appendChild($body);
        $this->message->appendChild($envelope);

        return $this->message->saveXML();
    }

    public function confirmTicket($auth, $pnr_meta)
    {
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $header = $this->message->createElement('Header');
        $envelope->appendChild($header);
        $body = $this->message->createElement('Body');
        $airLowFareSearch = $this->message->createElement('AirDemandTicket');
        $airLowFareSearch->setAttribute('xmlns', 'http://zapways.com/air/ota/3.0');
        $body->appendChild($airLowFareSearch);
        $airLowFareSearchRQ = $this->message->createElement('airDemandTicketRQ');
        $airLowFareSearchRQ->setAttribute('Target', parent::TARGET);
        $airLowFareSearchRQ->setAttribute('Version', parent::VERSION);
        $airLowFareSearchRQ->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.opentravel.org/OTA/2003/05');
        $this->getPosTag($airLowFareSearchRQ, $auth);
        $demandTicketDetail = $this->message->createElement("DemandTicketDetail");
        $airLowFareSearchRQ->appendChild($demandTicketDetail);
        $bookingReferenceID = $this->message->createElement("BookingReferenceID");
        $bookingReferenceID->setAttribute("Instance", $pnr_meta['pnr_info'][0]['Instance']);
        $bookingReferenceID->setAttribute("ID", $pnr_meta['pnr_info'][0]['ID']);
        $demandTicketDetail->appendChild($bookingReferenceID);
        $paymentInfo = $this->message->createElement("PaymentInfo");
        $paymentInfo->setAttribute("PaymentType", "CASH");
        $paymentInfo->setAttribute("CurrencyCode", 'PKR');
        $paymentInfo->setAttribute("Amount", $pnr_meta['ItinTotalFare']['TotalFare']['Amount']);
        $demandTicketDetail->appendChild($paymentInfo);
        $airLowFareSearch->appendChild($airLowFareSearchRQ);
        $envelope->appendChild($body);
        $this->message->appendChild($envelope);
        return $this->message->saveXML();
    }

    public function createAirTktRefundRequest($auth, $booking_pnr)
    {
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $body = $this->message->createElement('Body');
        $airLowFareSearch = $this->message->createElementNS('http://zapways.com/air/ota/3.0', 'AirBookModify');
        $airLowFareSearchRQ = $this->message->createElement('airBookModifyRQ');

        $airLowFareSearchRQ->setAttribute('Target', parent::TARGET);
        $airLowFareSearchRQ->setAttribute('Version', parent::VERSION);
        $airLowFareSearchRQ->setAttributeNS('http://www.w3.org/2000/xmlns/','xmlns','http://www.opentravel.org/OTA/2003/05');

        $this->getPosTag($airLowFareSearchRQ, $auth);

        $AirBookModifyRQ = $this->message->createElement('AirBookModifyRQ');
        $AirBookModifyRQ->setAttribute('ModificationType', '1');

        $AirBookModifyRQ->appendChild($this->message->createTextNode(''));

        $airLowFareSearchRQ->appendChild($AirBookModifyRQ);

        $airReservation = $this->message->createElement('AirReservation');
        $bookingReference = $this->message->createElement('BookingReferenceID');
        $bookingReference->setAttribute('ID', $booking_pnr);
        $airReservation->appendChild($bookingReference);
        $airLowFareSearchRQ->appendChild($airReservation);

        $airLowFareSearch->appendChild($airLowFareSearchRQ);
        $body->appendChild($airLowFareSearch);
        $envelope->appendChild($body);
        $this->message->appendChild($envelope);

        return $this->message->saveXML();
    }

    public function cancelBooking($auth, $booking_pnr){
        $envelope = $this->message->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $body = $this->message->createElement('Body');
        $airLowFareSearch = $this->message->createElementNS('http://zapways.com/air/ota/3.0', 'Cancel');
        $airLowFareSearchRQ = $this->message->createElement('cancelRQ');

        $airLowFareSearchRQ->setAttribute('Target', parent::TARGET);
        $airLowFareSearchRQ->setAttribute('Version', parent::VERSION);
        $airLowFareSearchRQ->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns',
            'http://www.opentravel.org/OTA/2003/05'
        );

        $this->getPosTag($airLowFareSearchRQ, $auth);

        $UniqueID = $this->message->createElement('UniqueID');
        $UniqueID->setAttribute('ID', $booking_pnr);

        $UniqueID->appendChild($this->message->createTextNode(''));

        $airLowFareSearchRQ->appendChild($UniqueID);

        $airLowFareSearch->appendChild($airLowFareSearchRQ);
        $body->appendChild($airLowFareSearch);
        $envelope->appendChild($body);
        $this->message->appendChild($envelope);

        return $this->message->saveXML(); 
    }

    public function airBookingModify(){ 
        $message = <<<EOM
            <?xml version="1.0" encoding="UTF-8"?>
                <Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/" xmlns:default="http://zapways.com/air/ota/3.0">
                    <Body xmlns:default="http://zapways.com/air/ota/3.0">
                        <AirBookModify xmlns="http://zapways.com/air/ota/3.0">
                            <airBookModifyRQ xmlns="http://www.opentravel.org/OTA/2003/05" Target="Test" Version="1.04">
                                <POS>
                                    <Source ERSP_UserID="2048/2B5D3BA1556724A4D2961F66DD49383409">
                                        <RequestorID Type="29" ID="ALMARWAGROUPOTA" MessagePassword="thALM#vewpLuqCV9" />
                                    </Source>
                                </POS>
                                <AirBookModifyRQ>
                                <AirItinerary>
                                    <OriginDestinationOptions>
                                        <OriginDestinationOption RPH="0-0">
                                            <FlightSegment DepartureDateTime="2025-04-23T10:00:00" ArrivalDateTime="2025-04-23T11:55:00" StopQuantity="0" ResBookDesigCode="T" RPH="0-0-EF-0-0" FlightNumber="401" Status="ONTIME">
                                                <DepartureAirport LocationCode="LHE" />
                                                <ArrivalAirport LocationCode="KHI" />
                                                <OperatingAirline Code="PA" />
                                                <Equipment AirEquipType="A321" />
                                                <MarketingAirline Code="PA" />
                                            </FlightSegment>
                                        </OriginDestinationOption>
                                        <OriginDestinationOption RPH="1-1">
                                            <FlightSegment DepartureDateTime="2025-04-24T20:30:00" ArrivalDateTime="2025-04-24T22:25:00" StopQuantity="0" ResBookDesigCode="L" RPH="1-1-EF-0-0" FlightNumber="406" Status="ONTIME">
                                                <DepartureAirport LocationCode="KHI" />
                                                <ArrivalAirport LocationCode="LHE" />
                                                <OperatingAirline Code="PA" />
                                                <Equipment AirEquipType="A321" />
                                                <MarketingAirline Code="PA" />
                                            </FlightSegment>
                                        </OriginDestinationOption>
                                    </OriginDestinationOptions>
                                </AirItinerary>
                                <PriceInfo>
                                    <PTC_FareBreakdowns>
                                        <PTC_FareBreakdown>
                                            <PassengerTypeQuantity Code="ADT" Quantity="1" />
                                            <PassengerFare>
                                                <BaseFare CurrencyCode="PKR" Amount="23645" />
                                                <Taxes Amount="2145">
                                                    <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                    <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                    <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                    <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                    <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                </Taxes>
                                                <Fees Amount="7000">
                                                    <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                </Fees>
                                                <TotalFare CurrencyCode="PKR" Amount="32790" />
                                            </PassengerFare>
                                            <FareInfo>
                                                <DepartureDate>
                                                    2025-04-23T00:00:00
                                                </DepartureDate>
                                                <DepartureAirport LocationCode="LHE" />
                                                <ArrivalAirport LocationCode="KHI" />
                                                <FareInfo FareBasisCode="EXTARTT" />
                                                <PassengerFare>
                                                    <BaseFare CurrencyCode="PKR" Amount="23645" />
                                                    <Taxes Amount="2145">
                                                        <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                        <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                        <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                        <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                    </Taxes>
                                                    <Fees Amount="7000">
                                                        <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                    </Fees>
                                                    <TotalFare CurrencyCode="PKR" Amount="32790" />
                                                </PassengerFare>
                                            </FareInfo>
                                        </PTC_FareBreakdown>
                                        <PTC_FareBreakdown>
                                            <PassengerTypeQuantity Code="CHD" Quantity="1" />
                                            <PassengerFare>
                                                <BaseFare CurrencyCode="PKR" Amount="17734" />
                                                <Taxes Amount="2145">
                                                    <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                    <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                    <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                    <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                    <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                </Taxes>
                                                <Fees Amount="7000">
                                                    <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                </Fees>
                                                <TotalFare CurrencyCode="PKR" Amount="26879" />
                                            </PassengerFare>
                                            <FareInfo>
                                                <DepartureDate>
                                                    2025-04-23T00:00:00
                                                </DepartureDate>
                                                <DepartureAirport LocationCode="LHE" />
                                                <ArrivalAirport LocationCode="KHI" />
                                                <FareInfo FareBasisCode="EXTARTT" />
                                                <PassengerFare>
                                                    <BaseFare CurrencyCode="PKR" Amount="17734" />
                                                    <Taxes Amount="2145">
                                                        <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                        <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                        <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                        <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                    </Taxes>
                                                    <Fees Amount="7000">
                                                        <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                    </Fees>
                                                    <TotalFare CurrencyCode="PKR" Amount="26879" />
                                                </PassengerFare>
                                            </FareInfo>
                                        </PTC_FareBreakdown>
                                        <PTC_FareBreakdown>
                                            <PassengerTypeQuantity Code="INF" Quantity="1" />
                                            <PassengerFare>
                                                <BaseFare CurrencyCode="PKR" Amount="2364" />
                                                <Taxes Amount="1545">
                                                    <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                    <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                    <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                </Taxes>
                                                <TotalFare CurrencyCode="PKR" Amount="3909" />
                                            </PassengerFare>
                                            <FareInfo>
                                                <DepartureDate>
                                                    2025-04-23T00:00:00
                                                </DepartureDate>
                                                <DepartureAirport LocationCode="LHE" />
                                                <ArrivalAirport LocationCode="KHI" />
                                                <FareInfo FareBasisCode="EXTARTT" />
                                                <PassengerFare>
                                                    <BaseFare CurrencyCode="PKR" Amount="2364" />
                                                    <Taxes Amount="1545">
                                                        <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                        <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                    </Taxes>
                                                    <TotalFare CurrencyCode="PKR" Amount="3909" />
                                                </PassengerFare>
                                            </FareInfo>
                                        </PTC_FareBreakdown>
                                        <PTC_FareBreakdown>
                                            <PassengerTypeQuantity Code="ADT" Quantity="1" />
                                            <PassengerFare>
                                                <BaseFare CurrencyCode="PKR" Amount="19595" />
                                                <Taxes Amount="2145">
                                                    <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                    <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                    <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                    <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                    <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                </Taxes>
                                                <Fees Amount="7000">
                                                    <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                </Fees>
                                                <TotalFare CurrencyCode="PKR" Amount="28740" />
                                            </PassengerFare>
                                            <FareInfo>
                                                <DepartureDate>
                                                    2025-04-24T00:00:00
                                                </DepartureDate>
                                                <DepartureAirport LocationCode="KHI" />
                                                <ArrivalAirport LocationCode="LHE" />
                                                <FareInfo FareBasisCode="EFTARTL" />
                                                <PassengerFare>
                                                    <BaseFare CurrencyCode="PKR" Amount="19595" />
                                                    <Taxes Amount="2145">
                                                        <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                        <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                        <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                        <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                    </Taxes>
                                                    <Fees Amount="7000">
                                                        <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                    </Fees>
                                                    <TotalFare CurrencyCode="PKR" Amount="28740" />
                                                </PassengerFare>
                                            </FareInfo>
                                        </PTC_FareBreakdown>
                                        <PTC_FareBreakdown>
                                            <PassengerTypeQuantity Code="CHD" Quantity="1" />
                                            <PassengerFare>
                                                <BaseFare CurrencyCode="PKR" Amount="14696" />
                                                <Taxes Amount="2145">
                                                    <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                    <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                    <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                    <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                    <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                </Taxes>
                                                <Fees Amount="7000">
                                                    <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                </Fees>
                                                <TotalFare CurrencyCode="PKR" Amount="23841" />
                                            </PassengerFare>
                                            <FareInfo>
                                                <DepartureDate>
                                                    2025-04-24T00:00:00
                                                </DepartureDate>
                                                <DepartureAirport LocationCode="KHI" />
                                                <ArrivalAirport LocationCode="LHE" />
                                                <FareInfo FareBasisCode="EFTARTL" />
                                                <PassengerFare>
                                                    <BaseFare CurrencyCode="PKR" Amount="14696" />
                                                    <Taxes Amount="2145">
                                                        <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                        <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                        <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                        <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                    </Taxes>
                                                    <Fees Amount="7000">
                                                        <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                    </Fees>
                                                    <TotalFare CurrencyCode="PKR" Amount="23841" />
                                                </PassengerFare>
                                            </FareInfo>
                                        </PTC_FareBreakdown>
                                        <PTC_FareBreakdown>
                                            <PassengerTypeQuantity Code="INF" Quantity="1" />
                                            <PassengerFare>
                                                <BaseFare CurrencyCode="PKR" Amount="1960" />
                                                <Taxes Amount="1545">
                                                    <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                    <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                    <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                    <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                </Taxes>
                                                <TotalFare CurrencyCode="PKR" Amount="3505" />
                                            </PassengerFare>
                                            <FareInfo>
                                                <DepartureDate>
                                                    2025-04-24T00:00:00
                                                </DepartureDate>
                                                <DepartureAirport LocationCode="KHI" />
                                                <ArrivalAirport LocationCode="LHE" />
                                                <FareInfo FareBasisCode="EFTARTL" />
                                                <PassengerFare>
                                                    <BaseFare CurrencyCode="PKR" Amount="1960" />
                                                    <Taxes Amount="1545">
                                                        <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                        <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                    </Taxes>
                                                    <TotalFare CurrencyCode="PKR" Amount="3505" />
                                                </PassengerFare>
                                            </FareInfo>
                                        </PTC_FareBreakdown>
                                    </PTC_FareBreakdowns>
                                </PriceInfo>
                                <TravelerInfo>
                                    <AirTraveler BirthDate="1998-04-16">
                                        <PersonName>
                                            <NameTitle>
                                                MR
                                            </NameTitle>
                                            <GivenName>
                                                MATI
                                            </GivenName>
                                            <Surname>
                                                KHAN
                                            </Surname>
                                        </PersonName>
                                        <Telephone PhoneLocationType="8" CountryAccessCode="+92" PhoneNumber="923119468498" />
                                        <Email>
                                            mati.rehman054@gmail.com
                                        </Email>
                                        <Document DocID="asd123as" DocType="2" BirthDate="1998-04-16" ExpireDate="2026-04-16" DocIssueCountry="PK" DocHolderNationality="PK" />
                                        <PassengerTypeQuantity Code="ADT" Quantity="1" />
                                        <TravelerRefNumber RPH="1" />
                                    </AirTraveler>
                                    <AirTraveler BirthDate="2021-04-16">
                                        <PersonName>
                                            <NameTitle>
                                                MSTR
                                            </NameTitle>
                                            <GivenName>
                                                MATI
                                            </GivenName>
                                            <Surname>
                                                KHAN
                                            </Surname>
                                        </PersonName>
                                        <Telephone PhoneLocationType="8" CountryAccessCode="+92" PhoneNumber="923119468498" />
                                        <Email>
                                            mati.rehman054@gmail.com
                                        </Email>
                                        <Document DocID="asd123as" DocType="2" BirthDate="2021-04-16" ExpireDate="2026-04-16" DocIssueCountry="PK" DocHolderNationality="PK" />
                                        <PassengerTypeQuantity Code="CHD" Quantity="1" />
                                        <TravelerRefNumber RPH="2" />
                                    </AirTraveler>
                                    <AirTraveler BirthDate="2025-04-16">
                                        <PersonName>
                                            <GivenName>
                                                KAMAL
                                            </GivenName>
                                            <Surname>
                                                KHAN
                                            </Surname>
                                        </PersonName>
                                        <Telephone PhoneLocationType="8" CountryAccessCode="+92" PhoneNumber="923119468498" />
                                        <Email>
                                            mati.rehman054@gmail.com
                                        </Email>
                                        <Document DocID="asd123as" DocType="2" BirthDate="2025-04-16" ExpireDate="2026-04-16" DocIssueCountry="PK" DocHolderNationality="PK" />
                                        <PassengerTypeQuantity Code="INF" Quantity="1" />
                                        <TravelerRefNumber RPH="3" />
                                    </AirTraveler>
                                </TravelerInfo>
                                </AirBookModifyRQ>

                                <AirReservation>
                                    <AirItinerary>
                                        <OriginDestinationOptions>
                                            <OriginDestinationOption RPH="B1">
                                                <FlightSegment DepartureDateTime="2025-04-23T10:00:00" ArrivalDateTime="2025-04-23T11:55:00" StopQuantity="0" RPH="1" FlightNumber="401" Status="ONTIME">
                                                    <DepartureAirport LocationCode="LHE" Terminal="" />
                                                    <ArrivalAirport LocationCode="KHI" Terminal="" />
                                                    <OperatingAirline Code="PA" />
                                                    <Equipment AirEquipType="A321" />
                                                    <MarketingAirline Code="PA" />
                                                </FlightSegment>
                                            </OriginDestinationOption>
                                            <OriginDestinationOption RPH="B2">
                                                <FlightSegment DepartureDateTime="2025-04-24T13:00:00" ArrivalDateTime="2025-04-24T14:55:00" StopQuantity="0" RPH="2" FlightNumber="402" Status="ONTIME">
                                                    <DepartureAirport LocationCode="KHI" Terminal="" />
                                                    <ArrivalAirport LocationCode="LHE" Terminal="" />
                                                    <OperatingAirline Code="PA" />
                                                    <Equipment AirEquipType="A321" />
                                                    <MarketingAirline Code="PA" />
                                                </FlightSegment>
                                            </OriginDestinationOption>
                                        </OriginDestinationOptions>
                                    </AirItinerary>
                                    <PriceInfo>
                                        <ItinTotalFare>
                                            <TotalFare CurrencyCode="PKR" Amount="121940" />
                                        </ItinTotalFare>
                                        <PTC_FareBreakdowns>
                                            <PTC_FareBreakdown>
                                                <PassengerTypeQuantity Code="ADT" Quantity="1" />
                                                <PassengerFare>
                                                    <BaseFare CurrencyCode="PKR" Amount="44470" />
                                                    <Taxes Amount="4290">
                                                        <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="P2" CurrencyCode="PKR" Amount="3000" />
                                                        <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="N9" CurrencyCode="PKR" Amount="50" />
                                                        <Tax TaxCode="SP" CurrencyCode="PKR" Amount="1000" />
                                                        <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="200" />
                                                        <Tax TaxCode="YI" CurrencyCode="PKR" Amount="40" />
                                                    </Taxes>
                                                    <Fees Amount="14000">
                                                        <Fee FeeCode="Q1" Amount="14000" />
                                                    </Fees>
                                                </PassengerFare>
                                                <TravelerRefNumber RPH="DoTv3gVyOCAVQilPELIUwXLLWnhvMkkI" />
                                                <FareInfo RuleNumber="4100">
                                                    <DepartureAirport LocationCode="LHE" />
                                                    <ArrivalAirport LocationCode="KHI" />
                                                    <PassengerFare>
                                                        <BaseFare CurrencyCode="PKR" Amount="22235" />
                                                        <Taxes Amount="2145">
                                                            <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                            <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                            <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                            <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                            <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        </Taxes>
                                                        <Fees Amount="7000">
                                                            <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                        </Fees>
                                                        <FareBaggageAllowance UnitOfMeasureQuantity="20" UnitOfMeasure="KGS" />
                                                    </PassengerFare>
                                                </FareInfo>
                                                <FareInfo RuleNumber="4100">
                                                    <DepartureAirport LocationCode="KHI" />
                                                    <ArrivalAirport LocationCode="LHE" />
                                                    <PassengerFare>
                                                        <BaseFare CurrencyCode="PKR" Amount="22235" />
                                                        <Taxes Amount="2145">
                                                            <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                            <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                            <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                            <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                            <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        </Taxes>
                                                        <Fees Amount="7000">
                                                            <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                        </Fees>
                                                        <FareBaggageAllowance UnitOfMeasureQuantity="20" UnitOfMeasure="KGS" />
                                                    </PassengerFare>
                                                </FareInfo>
                                            </PTC_FareBreakdown>
                                            <PTC_FareBreakdown>
                                                <PassengerTypeQuantity Code="CHD" Quantity="1" />
                                                <PassengerFare>
                                                    <BaseFare CurrencyCode="PKR" Amount="33352" />
                                                    <Taxes Amount="4290">
                                                        <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="P2" CurrencyCode="PKR" Amount="3000" />
                                                        <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="N9" CurrencyCode="PKR" Amount="50" />
                                                        <Tax TaxCode="SP" CurrencyCode="PKR" Amount="1000" />
                                                        <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="200" />
                                                        <Tax TaxCode="YI" CurrencyCode="PKR" Amount="40" />
                                                    </Taxes>
                                                    <Fees Amount="14000">
                                                        <Fee FeeCode="Q1" Amount="14000" />
                                                    </Fees>
                                                </PassengerFare>
                                                <TravelerRefNumber RPH="DoTv3gVyOCAVQilPELIUwY62pEwxB0pU" />
                                                <FareInfo RuleNumber="4100">
                                                    <DepartureAirport LocationCode="LHE" />
                                                    <ArrivalAirport LocationCode="KHI" />
                                                    <PassengerFare>
                                                        <BaseFare CurrencyCode="PKR" Amount="16676" />
                                                        <Taxes Amount="2145">
                                                            <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                            <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                            <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                            <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                            <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        </Taxes>
                                                        <Fees Amount="7000">
                                                            <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                        </Fees>
                                                        <FareBaggageAllowance UnitOfMeasureQuantity="20" UnitOfMeasure="KGS" />
                                                    </PassengerFare>
                                                </FareInfo>
                                                <FareInfo RuleNumber="4100">
                                                    <DepartureAirport LocationCode="KHI" />
                                                    <ArrivalAirport LocationCode="LHE" />
                                                    <PassengerFare>
                                                        <BaseFare CurrencyCode="PKR" Amount="16676" />
                                                        <Taxes Amount="2145">
                                                            <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                            <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                            <Tax TaxCode="SP" CurrencyCode="PKR" Amount="500" />
                                                            <Tax TaxCode="XZ" CurrencyCode="PKR" Amount="100" />
                                                            <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        </Taxes>
                                                        <Fees Amount="7000">
                                                            <Fee FeeCode="Q1" CurrencyCode="PKR" Amount="7000" />
                                                        </Fees>
                                                        <FareBaggageAllowance UnitOfMeasureQuantity="20" UnitOfMeasure="KGS" />
                                                    </PassengerFare>
                                                </FareInfo>
                                            </PTC_FareBreakdown>
                                            <PTC_FareBreakdown>
                                                <PassengerTypeQuantity Code="INF" Quantity="1" />
                                                <PassengerFare>
                                                    <BaseFare CurrencyCode="PKR" Amount="4448" />
                                                    <Taxes Amount="3090">
                                                        <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="P2" CurrencyCode="PKR" Amount="3000" />
                                                        <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                        <Tax TaxCode="N9" CurrencyCode="PKR" Amount="50" />
                                                        <Tax TaxCode="YI" CurrencyCode="PKR" Amount="40" />
                                                    </Taxes>
                                                    <Fees Amount="0" />
                                                </PassengerFare>
                                                <TravelerRefNumber RPH="DoTv3gVyOCAVQilPELIUwbXsJmB8b1w/" />
                                                <FareInfo RuleNumber="4100">
                                                    <DepartureAirport LocationCode="LHE" />
                                                    <ArrivalAirport LocationCode="KHI" />
                                                    <PassengerFare>
                                                        <BaseFare CurrencyCode="PKR" Amount="2224" />
                                                        <Taxes Amount="1545">
                                                            <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                            <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                            <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        </Taxes>
                                                        <Fees Amount="0" />
                                                        <FareBaggageAllowance UnitOfMeasureQuantity="0" UnitOfMeasure="KGS" />
                                                    </PassengerFare>
                                                </FareInfo>
                                                <FareInfo RuleNumber="4100">
                                                    <DepartureAirport LocationCode="KHI" />
                                                    <ArrivalAirport LocationCode="LHE" />
                                                    <PassengerFare>
                                                        <BaseFare CurrencyCode="PKR" Amount="2224" />
                                                        <Taxes Amount="1545">
                                                            <Tax TaxCode="AD" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="P2" CurrencyCode="PKR" Amount="1500" />
                                                            <Tax TaxCode="P3" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="PK" CurrencyCode="PKR" Amount="0" />
                                                            <Tax TaxCode="N9" CurrencyCode="PKR" Amount="25" />
                                                            <Tax TaxCode="YI" CurrencyCode="PKR" Amount="20" />
                                                        </Taxes>
                                                        <Fees Amount="0" />
                                                        <FareBaggageAllowance UnitOfMeasureQuantity="0" UnitOfMeasure="KGS" />
                                                    </PassengerFare>
                                                </FareInfo>
                                            </PTC_FareBreakdown>
                                        </PTC_FareBreakdowns>
                                    </PriceInfo>
                                    <TravelerInfo>
                                        <AirTraveler BirthDate="1998-04-16" PassengerTypeCode="ADT">
                                            <PersonName>
                                                <GivenName>
                                                    MATI
                                                </GivenName>
                                                <Surname>
                                                    KHAN
                                                </Surname>
                                                <NameTitle>
                                                    MR
                                                </NameTitle>
                                            </PersonName>
                                            <Telephone PhoneLocationType="8" CountryAccessCode="+92" PhoneNumber="923119468498" />
                                            <Email>
                                                mati.rehman054@gmail.com
                                            </Email>
                                            <Document DocID="asd123as" DocType="2" BirthDate="1998-04-16" ExpireDate="2026-04-16" DocIssueCountry="PK" DocHolderNationality="PK" />
                                            <PassengerTypeQuantity Code="ADT" Quantity="1" />
                                            <TravelerRefNumber RPH="DoTv3gVyOCAVQilPELIUwXLLWnhvMkkI" />
                                            <FlightSegmentRPHs>
                                                <FlightSegmentRPH>
                                                    1
                                                </FlightSegmentRPH>
                                                <FlightSegmentRPH>
                                                    2
                                                </FlightSegmentRPH>
                                            </FlightSegmentRPHs>
                                        </AirTraveler>
                                        <AirTraveler BirthDate="2021-04-16" PassengerTypeCode="CHD">
                                            <PersonName>
                                                <GivenName>
                                                    MATI
                                                </GivenName>
                                                <Surname>
                                                    KHAN
                                                </Surname>
                                                <NameTitle>
                                                    MSTR
                                                </NameTitle>
                                            </PersonName>
                                            <Telephone PhoneLocationType="8" CountryAccessCode="+92" PhoneNumber="923119468498" />
                                            <Email>
                                                mati.rehman054@gmail.com
                                            </Email>
                                            <Document DocID="asd123as" DocType="2" BirthDate="2021-04-16" ExpireDate="2026-04-16" DocIssueCountry="PK" DocHolderNationality="PK" />
                                            <PassengerTypeQuantity Code="CHD" Quantity="1" />
                                            <TravelerRefNumber RPH="DoTv3gVyOCAVQilPELIUwY62pEwxB0pU" />
                                            <FlightSegmentRPHs>
                                                <FlightSegmentRPH>
                                                    1
                                                </FlightSegmentRPH>
                                                <FlightSegmentRPH>
                                                    2
                                                </FlightSegmentRPH>
                                            </FlightSegmentRPHs>
                                        </AirTraveler>
                                        <AirTraveler BirthDate="2025-04-16" PassengerTypeCode="INF">
                                            <PersonName>
                                                <GivenName>
                                                    KAMAL
                                                </GivenName>
                                                <Surname>
                                                    KHAN
                                                </Surname>
                                            </PersonName>
                                            <Telephone PhoneLocationType="8" CountryAccessCode="+92" PhoneNumber="923119468498" />
                                            <Email>
                                                mati.rehman054@gmail.com
                                            </Email>
                                            <Document DocID="asd123as" DocType="2" BirthDate="2025-04-16" ExpireDate="2026-04-16" DocIssueCountry="PK" DocHolderNationality="PK" />
                                            <PassengerTypeQuantity Code="INF" Quantity="1" />
                                            <TravelerRefNumber RPH="DoTv3gVyOCAVQilPELIUwbXsJmB8b1w/" />
                                            <FlightSegmentRPHs>
                                                <FlightSegmentRPH>
                                                    1
                                                </FlightSegmentRPH>
                                                <FlightSegmentRPH>
                                                    2
                                                </FlightSegmentRPH>
                                            </FlightSegmentRPHs>
                                        </AirTraveler>
                                    </TravelerInfo>
                                    <Ticketing TicketTimeLimit="2025-04-22T14:24:07" TicketingStatus="OK" FlightSegmentRefNumber="1" TravelerRefNumber="DoTv3gVyOCAVQilPELIUwXLLWnhvMkkI" TimeLimitMinutes="-120" PassengerTypeCode="ADT">
                                        <TicketingVendor Code="PA" />
                                    </Ticketing>
                                    <Ticketing TicketTimeLimit="2025-04-22T14:24:07" TicketingStatus="OK" FlightSegmentRefNumber="2" TravelerRefNumber="DoTv3gVyOCAVQilPELIUwXLLWnhvMkkI" TimeLimitMinutes="-120" PassengerTypeCode="ADT">
                                        <TicketingVendor Code="PA" />
                                    </Ticketing>
                                    <Ticketing TicketTimeLimit="2025-04-22T14:24:07" TicketingStatus="OK" FlightSegmentRefNumber="1" TravelerRefNumber="DoTv3gVyOCAVQilPELIUwY62pEwxB0pU" TimeLimitMinutes="-120" PassengerTypeCode="CHD">
                                        <TicketingVendor Code="PA" />
                                    </Ticketing>
                                    <Ticketing TicketTimeLimit="2025-04-22T14:24:07" TicketingStatus="OK" FlightSegmentRefNumber="2" TravelerRefNumber="DoTv3gVyOCAVQilPELIUwY62pEwxB0pU" TimeLimitMinutes="-120" PassengerTypeCode="CHD">
                                        <TicketingVendor Code="PA" />
                                    </Ticketing>
                                    <Ticketing TicketTimeLimit="2025-04-22T14:24:07" TicketingStatus="OK" FlightSegmentRefNumber="1" TravelerRefNumber="DoTv3gVyOCAVQilPELIUwbXsJmB8b1w/" TimeLimitMinutes="-120" PassengerTypeCode="INF">
                                        <TicketingVendor Code="PA" />
                                    </Ticketing>
                                    <Ticketing TicketTimeLimit="2025-04-22T14:24:07" TicketingStatus="OK" FlightSegmentRefNumber="2" TravelerRefNumber="DoTv3gVyOCAVQilPELIUwbXsJmB8b1w/" TimeLimitMinutes="-120" PassengerTypeCode="INF">
                                        <TicketingVendor Code="PA" />
                                    </Ticketing>
                                    <BookingReferenceID Instance="PA0101217120" ID="LRAPNF" />
                                    <BookingReferenceID Type="14" Instance="PA0101217120" ID="LRAPNF" />
                                </AirReservation>
                            </airBookModifyRQ>
                        </AirBookModify>
                    </Body>
                </Envelope>
            EOM;
        return $message;
    }
}
