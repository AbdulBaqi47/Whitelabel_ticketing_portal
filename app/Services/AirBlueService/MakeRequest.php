<?php

namespace App\Services\AirBlueService;

use DOMDocument;

class MakeRequest
{
    public $message;

    const ECHOTOKEN = '-8586704355136787339';
    const TARGET = 'Production';
    const VERSION = '1.04';

    public function __construct()
    {
        $this->message = new DOMDocument('1.0', 'UTF-8');
    }


    public function getPosTag($parentTag,$auth)
    {
        $pos = $this->message->createElement('POS');
        $source = $this->message->createElement('Source');
        $source->setAttribute('ERSP_UserID', $auth['ERSP_UserID']);

        $requestorID = $this->message->createElement('RequestorID');
        $requestorID->setAttribute('Type', 29);
        $requestorID->setAttribute('ID', $auth['ID']);
        $requestorID->setAttribute('MessagePassword', $auth['MessagePassword']);

        $source->appendChild($requestorID);
        $pos->appendChild($source);
        $parentTag->appendChild($pos);
    }

    public function airItinerary($data, $parentTag)
    {
        $itinerary = $data['segments'];
        $AirItinerary = $this->message->createElement('AirItinerary');
        $parentTag->appendChild($AirItinerary);
        $originDestinationOptions = $this->message->createElement('OriginDestinationOptions');
        $AirItinerary->appendChild($originDestinationOptions);
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
        $arrivalAirport = $this->message->createElement('ArrivalAirport');
        $arrivalAirport->setAttribute('LocationCode', $itinerary_Segment['ArrivalAirport']['LocationCode']);
        $operatingAirline = $this->message->createElement('OperatingAirline');
        $operatingAirline->setAttribute('Code', $itinerary_Segment['OperatingAirline']['Code']);
        $equipment = $this->message->createElement('Equipment');
        $equipment->setAttribute('AirEquipType', $itinerary_Segment['Equipment']['AirEquipType']);
        $marketingAirline = $this->message->createElement('MarketingAirline');
        $marketingAirline->setAttribute('Code', $itinerary_Segment['MarketingAirline']['Code']);
        $flightSegment->appendChild($departureAirport);
        $flightSegment->appendChild($arrivalAirport);
        $flightSegment->appendChild($operatingAirline);
        $flightSegment->appendChild($equipment);
        $flightSegment->appendChild($marketingAirline);
    }
    public function priceInfo($data, $parentTag)
    {
        $fare_breakdown = $data['fare_info']['PTC_FareBreakdowns']['PTC_FareBreakdown'];
        
        $priceInfo = $this->message->createElement('PriceInfo');
        $parentTag->appendChild($priceInfo);
        $ptcFareBreakdowns = $this->message->createElement('PTC_FareBreakdowns');
        $priceInfo->appendChild($ptcFareBreakdowns);

        foreach (arrayConversion($fare_breakdown) as $fare_breakdown) {
            $ptcFareBreakdown = $this->message->createElement('PTC_FareBreakdown');
            $passengerTypeQuantity = $this->message->createElement('PassengerTypeQuantity');
            $passengerTypeQuantity->setAttribute('Code', $fare_breakdown['PassengerTypeQuantity']['Code']);
            $passengerTypeQuantity->setAttribute('Quantity', $fare_breakdown['PassengerTypeQuantity']['Quantity']);
            $ptcFareBreakdown->appendChild($passengerTypeQuantity);
            $passengerFare = $this->message->createElement('PassengerFare');
            $ptcFareBreakdown->appendChild($passengerFare);
            $baseFare = $this->message->createElement('BaseFare');
            $baseFare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['BaseFare']['CurrencyCode']);
            $baseFare->setAttribute('Amount', $fare_breakdown['PassengerFare']['BaseFare']['Amount']);
            $passengerFare->appendChild($baseFare);
            $taxes = $this->message->createElement('Taxes');
            $taxes->setAttribute("Amount", $fare_breakdown['PassengerFare']['Taxes']['Amount']);
            $passengerFare->appendChild($taxes);
            for ($i = 0; $i < count($fare_breakdown['PassengerFare']['Taxes']['Tax']); $i++) {
                $tax_breakdown = $fare_breakdown['PassengerFare']['Taxes']['Tax'][$i];
                $tax = $this->message->createElement('Tax');
                $tax->setAttribute('TaxCode', $tax_breakdown['TaxCode']);
                $tax->setAttribute('CurrencyCode', $tax_breakdown['CurrencyCode']);
                $tax->setAttribute('Amount', $tax_breakdown['Amount']);
                $taxes->appendChild($tax);
            }
            if (array_key_exists('Fees', $fare_breakdown['PassengerFare'])) {
                $fees = $this->message->createElement('Fees');
                $fees->setAttribute("Amount", $fare_breakdown['PassengerFare']['Fees']['Amount']);
                $passengerFare->appendChild($fees);
                for ($i = 0; $i < count(arrayConversion($fare_breakdown['PassengerFare']['Fees']['Fee'])); $i++) {
                    $fee_breakdown = arrayConversion($fare_breakdown['PassengerFare']['Fees']['Fee'])[$i];
                    $fee = $this->message->createElement('Fee');
                    $fee->setAttribute('FeeCode', $fee_breakdown['FeeCode']);
                    $fee->setAttribute('CurrencyCode', $fee_breakdown['CurrencyCode']);
                    $fee->setAttribute('Amount', $fee_breakdown['Amount']);
                    $fees->appendChild($fee);
                }
            }

            $totalFare = $this->message->createElement('TotalFare');
            $totalFare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['TotalFare']['CurrencyCode']);
            $totalFare->setAttribute('Amount', $fare_breakdown['PassengerFare']['TotalFare']['Amount']);
            $passengerFare->appendChild($totalFare);
            $fareInfoBreakDown = arrayConversion($fare_breakdown['FareInfo']);
            for ($i = 0; $i < count($fareInfoBreakDown); $i++) {
                $fare_breakdown = $fareInfoBreakDown[$i];
                $fare_info = $this->message->createElement('FareInfo');
                $ptcFareBreakdown->appendChild($fare_info);
                $departure_date = $this->message->createElement('DepartureDate', $fare_breakdown['DepartureDate']);
                $fare_info->appendChild($departure_date);
                $departure_airport = $this->message->createElement('DepartureAirport');
                $departure_airport->setAttribute('LocationCode', $fare_breakdown['DepartureAirport']['LocationCode']);
                $fare_info->appendChild($departure_airport);
                $arrival_airport = $this->message->createElement('ArrivalAirport');
                $arrival_airport->setAttribute('LocationCode', $fare_breakdown['ArrivalAirport']['LocationCode']);
                $fare_info->appendChild($arrival_airport);
                if (array_key_exists('RuleInfo', $fare_breakdown)) {
                    $RuleInfo = $this->message->createElement('RuleInfo');
                    $fare_info->appendChild($RuleInfo);
                    $ChargesRules = $this->message->createElement('ChargesRules');
                    $RuleInfo->appendChild($ChargesRules);
                    $VoluntaryChanges = $this->message->createElement('VoluntaryChanges');
                    $ChargesRules->appendChild($VoluntaryChanges);
                    for ($i = 0; $i < count($fare_breakdown['RuleInfo']['ChargesRules']['VoluntaryChanges']['Penalty']); $i++) {
                        $penalty_obj = $fare_breakdown['RuleInfo']['ChargesRules']['VoluntaryChanges']['Penalty'][$i];
                        $Penalty = $this->message->createElement('Penalty');
                        $Penalty->setAttribute("HoursBeforeDeparture", $penalty_obj['HoursBeforeDeparture']);
                        $Penalty->setAttribute("CurrencyCode", $penalty_obj['CurrencyCode']);
                        $Penalty->setAttribute("Amount", $penalty_obj['Amount']);
                        $VoluntaryChanges->appendChild($Penalty);
                    }
                }
                if (array_key_exists('FareInfo', $fare_breakdown)) {
                    $FareInfo = $this->message->createElement('FareInfo');
                    $FareInfo->setAttribute('FareBasisCode', $fare_breakdown['FareInfo']['FareBasisCode']);
                    $fare_info->appendChild($FareInfo);
                }
                $passenger_fare = $this->message->createElement('PassengerFare');
                $fare_info->appendChild($passenger_fare);

                if (array_key_exists('BaseFare', $fare_breakdown['PassengerFare'])) {
                    $base_fare = $this->message->createElement('BaseFare');
                    $base_fare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['BaseFare']['CurrencyCode']);
                    $base_fare->setAttribute('Amount', $fare_breakdown['PassengerFare']['BaseFare']['Amount']);
                    $passenger_fare->appendChild($base_fare);
                }
                if (array_key_exists('Taxes', $fare_breakdown['PassengerFare'])) {
                    $taxes = $this->message->createElement('Taxes');
                    $taxes->setAttribute("Amount", $fare_breakdown['PassengerFare']['Taxes']['Amount']);
                    for ($i = 0; $i < count($fare_breakdown['PassengerFare']['Taxes']['Tax']); $i++) {
                        $first_tax = $fare_breakdown['PassengerFare']['Taxes']['Tax'][$i];
                        $tax = $this->message->createElement('Tax');
                        $tax->setAttribute('TaxCode', $first_tax['TaxCode']);
                        $tax->setAttribute('CurrencyCode', $first_tax['CurrencyCode']);
                        $tax->setAttribute('Amount', $first_tax['Amount']);
                        $taxes->appendChild($tax);
                    }
                    $passenger_fare->appendChild($taxes);
                }
                if (array_key_exists('Fees', $fare_breakdown['PassengerFare'])) {
                    $fees = $this->message->createElement('Fees');
                    $fees->setAttribute('Amount', $fare_breakdown['PassengerFare']['Fees']['Amount']);
                    for ($i = 0; $i < count(arrayConversion($fare_breakdown['PassengerFare']['Fees']['Fee'])); $i++) {
                        $first_fee = arrayConversion($fare_breakdown['PassengerFare']['Fees']['Fee'])[$i];
                        $fee = $this->message->createElement('Fee');
                        $fee->setAttribute('FeeCode', $first_fee['FeeCode']);
                        $fee->setAttribute('CurrencyCode', $first_fee['CurrencyCode']);
                        $fee->setAttribute('Amount', $first_fee['Amount']);
                        $fees->appendChild($fee);
                    }
                    $passenger_fare->appendChild($fees);
                }
                if (array_key_exists('TotalFare', $fare_breakdown['PassengerFare'])) {
                    $total_fare = $this->message->createElement('TotalFare');
                    $total_fare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['TotalFare']['CurrencyCode']);
                    $total_fare->setAttribute('Amount', $fare_breakdown['PassengerFare']['TotalFare']['Amount']);
                    $passenger_fare->appendChild($total_fare);
                }
                if (array_key_exists('FareBaggageAllowance', $fare_breakdown['PassengerFare'])) {
                    $FareBaggageAllowance = $this->message->createElement('FareBaggageAllowance');
                    $FareBaggageAllowance->setAttribute("UnitOfMeasureQuantity", $fare_breakdown['PassengerFare']['FareBaggageAllowance']['UnitOfMeasureQuantity']);
                    $FareBaggageAllowance->setAttribute("UnitOfMeasure", $fare_breakdown['PassengerFare']['FareBaggageAllowance']['UnitOfMeasure']);
                    $passenger_fare->appendChild($FareBaggageAllowance);
                }
            }
            $ptcFareBreakdowns->appendChild($ptcFareBreakdown);
        }
    }

    public function returnAirItinerary($itineraries, $parentTag)
    {

        $AirItinerary = $this->message->createElement('AirItinerary');
        $parentTag->appendChild($AirItinerary);
        $originDestinationOptions = $this->message->createElement('OriginDestinationOptions');
        $AirItinerary->appendChild($originDestinationOptions);

        foreach($itineraries as $itinerary){
            $itinerary=$itinerary['segments'];
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
            $arrivalAirport = $this->message->createElement('ArrivalAirport');
            $arrivalAirport->setAttribute('LocationCode', $itinerary_Segment['ArrivalAirport']['LocationCode']);
            $operatingAirline = $this->message->createElement('OperatingAirline');
            $operatingAirline->setAttribute('Code', $itinerary_Segment['OperatingAirline']['Code']);
            $equipment = $this->message->createElement('Equipment');
            $equipment->setAttribute('AirEquipType', $itinerary_Segment['Equipment']['AirEquipType']);
            $marketingAirline = $this->message->createElement('MarketingAirline');
            $marketingAirline->setAttribute('Code', $itinerary_Segment['MarketingAirline']['Code']);
            $flightSegment->appendChild($departureAirport);
            $flightSegment->appendChild($arrivalAirport);
            $flightSegment->appendChild($operatingAirline);
            $flightSegment->appendChild($equipment);
            $flightSegment->appendChild($marketingAirline);
        }
    }
    public function returnPriceInfo($itineraries, $parentTag)
    {        
        $priceInfo = $this->message->createElement('PriceInfo');
        $parentTag->appendChild($priceInfo);
        $ptcFareBreakdowns = $this->message->createElement('PTC_FareBreakdowns');
        $priceInfo->appendChild($ptcFareBreakdowns);

        foreach($itineraries as $itinerary){
            $fare_breakdown = $itinerary['fare_info']['PTC_FareBreakdowns']['PTC_FareBreakdown'];
            foreach (arrayConversion($fare_breakdown) as $fare_breakdown) {
                $ptcFareBreakdown = $this->message->createElement('PTC_FareBreakdown');
                $passengerTypeQuantity = $this->message->createElement('PassengerTypeQuantity');
                $passengerTypeQuantity->setAttribute('Code', $fare_breakdown['PassengerTypeQuantity']['Code']);
                $passengerTypeQuantity->setAttribute('Quantity', $fare_breakdown['PassengerTypeQuantity']['Quantity']);
                $ptcFareBreakdown->appendChild($passengerTypeQuantity);
                $passengerFare = $this->message->createElement('PassengerFare');
                $ptcFareBreakdown->appendChild($passengerFare);
                $baseFare = $this->message->createElement('BaseFare');
                $baseFare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['BaseFare']['CurrencyCode']);
                $baseFare->setAttribute('Amount', $fare_breakdown['PassengerFare']['BaseFare']['Amount']);
                $passengerFare->appendChild($baseFare);
                $taxes = $this->message->createElement('Taxes');
                $taxes->setAttribute("Amount", $fare_breakdown['PassengerFare']['Taxes']['Amount']);
                $passengerFare->appendChild($taxes);
                for ($i = 0; $i < count($fare_breakdown['PassengerFare']['Taxes']['Tax']); $i++) {
                    $tax_breakdown = $fare_breakdown['PassengerFare']['Taxes']['Tax'][$i];
                    $tax = $this->message->createElement('Tax');
                    $tax->setAttribute('TaxCode', $tax_breakdown['TaxCode']);
                    $tax->setAttribute('CurrencyCode', $tax_breakdown['CurrencyCode']);
                    $tax->setAttribute('Amount', $tax_breakdown['Amount']);
                    $taxes->appendChild($tax);
                }
                if (array_key_exists('Fees', $fare_breakdown['PassengerFare'])) {
                    $fees = $this->message->createElement('Fees');
                    $fees->setAttribute("Amount", $fare_breakdown['PassengerFare']['Fees']['Amount']);
                    $passengerFare->appendChild($fees);
                    for ($i = 0; $i < count(arrayConversion($fare_breakdown['PassengerFare']['Fees']['Fee'])); $i++) {
                        $fee_breakdown = arrayConversion($fare_breakdown['PassengerFare']['Fees']['Fee'])[$i];
                        $fee = $this->message->createElement('Fee');
                        $fee->setAttribute('FeeCode', $fee_breakdown['FeeCode']);
                        $fee->setAttribute('CurrencyCode', $fee_breakdown['CurrencyCode']);
                        $fee->setAttribute('Amount', $fee_breakdown['Amount']);
                        $fees->appendChild($fee);
                    }
                }

                $totalFare = $this->message->createElement('TotalFare');
                $totalFare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['TotalFare']['CurrencyCode']);
                $totalFare->setAttribute('Amount', $fare_breakdown['PassengerFare']['TotalFare']['Amount']);
                $passengerFare->appendChild($totalFare);
                $fareInfoBreakDown = arrayConversion($fare_breakdown['FareInfo']);
                for ($i = 0; $i < count($fareInfoBreakDown); $i++) {
                    $fare_breakdown = $fareInfoBreakDown[$i];
                    $fare_info = $this->message->createElement('FareInfo');
                    $ptcFareBreakdown->appendChild($fare_info);
                    $departure_date = $this->message->createElement('DepartureDate', $fare_breakdown['DepartureDate']);
                    $fare_info->appendChild($departure_date);
                    $departure_airport = $this->message->createElement('DepartureAirport');
                    $departure_airport->setAttribute('LocationCode', $fare_breakdown['DepartureAirport']['LocationCode']);
                    $fare_info->appendChild($departure_airport);
                    $arrival_airport = $this->message->createElement('ArrivalAirport');
                    $arrival_airport->setAttribute('LocationCode', $fare_breakdown['ArrivalAirport']['LocationCode']);
                    $fare_info->appendChild($arrival_airport);
                    if (array_key_exists('RuleInfo', $fare_breakdown)) {
                        $RuleInfo = $this->message->createElement('RuleInfo');
                        $fare_info->appendChild($RuleInfo);
                        $ChargesRules = $this->message->createElement('ChargesRules');
                        $RuleInfo->appendChild($ChargesRules);
                        $VoluntaryChanges = $this->message->createElement('VoluntaryChanges');
                        $ChargesRules->appendChild($VoluntaryChanges);
                        for ($i = 0; $i < count($fare_breakdown['RuleInfo']['ChargesRules']['VoluntaryChanges']['Penalty']); $i++) {
                            $penalty_obj = $fare_breakdown['RuleInfo']['ChargesRules']['VoluntaryChanges']['Penalty'][$i];
                            $Penalty = $this->message->createElement('Penalty');
                            $Penalty->setAttribute("HoursBeforeDeparture", $penalty_obj['HoursBeforeDeparture']);
                            $Penalty->setAttribute("CurrencyCode", $penalty_obj['CurrencyCode']);
                            $Penalty->setAttribute("Amount", $penalty_obj['Amount']);
                            $VoluntaryChanges->appendChild($Penalty);
                        }
                    }
                    if (array_key_exists('FareInfo', $fare_breakdown)) {
                        $FareInfo = $this->message->createElement('FareInfo');
                        $FareInfo->setAttribute('FareBasisCode', $fare_breakdown['FareInfo']['FareBasisCode']);
                        $fare_info->appendChild($FareInfo);
                    }
                    $passenger_fare = $this->message->createElement('PassengerFare');
                    $fare_info->appendChild($passenger_fare);

                    if (array_key_exists('BaseFare', $fare_breakdown['PassengerFare'])) {
                        $base_fare = $this->message->createElement('BaseFare');
                        $base_fare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['BaseFare']['CurrencyCode']);
                        $base_fare->setAttribute('Amount', $fare_breakdown['PassengerFare']['BaseFare']['Amount']);
                        $passenger_fare->appendChild($base_fare);
                    }
                    if (array_key_exists('Taxes', $fare_breakdown['PassengerFare'])) {
                        $taxes = $this->message->createElement('Taxes');
                        $taxes->setAttribute("Amount", $fare_breakdown['PassengerFare']['Taxes']['Amount']);
                        for ($i = 0; $i < count($fare_breakdown['PassengerFare']['Taxes']['Tax']); $i++) {
                            $first_tax = $fare_breakdown['PassengerFare']['Taxes']['Tax'][$i];
                            $tax = $this->message->createElement('Tax');
                            $tax->setAttribute('TaxCode', $first_tax['TaxCode']);
                            $tax->setAttribute('CurrencyCode', $first_tax['CurrencyCode']);
                            $tax->setAttribute('Amount', $first_tax['Amount']);
                            $taxes->appendChild($tax);
                        }
                        $passenger_fare->appendChild($taxes);
                    }
                    if (array_key_exists('Fees', $fare_breakdown['PassengerFare'])) {
                        $fees = $this->message->createElement('Fees');
                        $fees->setAttribute('Amount', $fare_breakdown['PassengerFare']['Fees']['Amount']);
                        for ($i = 0; $i < count(arrayConversion($fare_breakdown['PassengerFare']['Fees']['Fee'])); $i++) {
                            $first_fee = arrayConversion($fare_breakdown['PassengerFare']['Fees']['Fee'])[$i];
                            $fee = $this->message->createElement('Fee');
                            $fee->setAttribute('FeeCode', $first_fee['FeeCode']);
                            $fee->setAttribute('CurrencyCode', $first_fee['CurrencyCode']);
                            $fee->setAttribute('Amount', $first_fee['Amount']);
                            $fees->appendChild($fee);
                        }
                        $passenger_fare->appendChild($fees);
                    }
                    if (array_key_exists('TotalFare', $fare_breakdown['PassengerFare'])) {
                        $total_fare = $this->message->createElement('TotalFare');
                        $total_fare->setAttribute('CurrencyCode', $fare_breakdown['PassengerFare']['TotalFare']['CurrencyCode']);
                        $total_fare->setAttribute('Amount', $fare_breakdown['PassengerFare']['TotalFare']['Amount']);
                        $passenger_fare->appendChild($total_fare);
                    }
                    if (array_key_exists('FareBaggageAllowance', $fare_breakdown['PassengerFare'])) {
                        $FareBaggageAllowance = $this->message->createElement('FareBaggageAllowance');
                        $FareBaggageAllowance->setAttribute("UnitOfMeasureQuantity", $fare_breakdown['PassengerFare']['FareBaggageAllowance']['UnitOfMeasureQuantity']);
                        $FareBaggageAllowance->setAttribute("UnitOfMeasure", $fare_breakdown['PassengerFare']['FareBaggageAllowance']['UnitOfMeasure']);
                        $passenger_fare->appendChild($FareBaggageAllowance);
                    }
                }
                $ptcFareBreakdowns->appendChild($ptcFareBreakdown);
            }
        }
    }
    public function travelerInfo($request, $parentTag)
    {
        $travelerInfo = $this->message->createElement('TravelerInfo');
        $parentTag->appendChild($travelerInfo);

        foreach($request['passengers'] as $key => $passenger ){

            $airTraveler = $this->message->createElement('AirTraveler');
            $airTraveler->setAttribute('BirthDate', $passenger['date_of_birth']);
            $personName = $this->message->createElement('PersonName');
            $givenName = $this->message->createElement('GivenName', $passenger['first_name']);
            $surname = $this->message->createElement('Surname', $passenger['last_name']);

            if($passenger['passenger_type'] != 'INF'){
                $nameTitle = $this->message->createElement('NameTitle', passenger_title_airblue($passenger));
                $personName->appendChild($nameTitle);
            }
        
            $personName->appendChild($givenName);
            $personName->appendChild($surname);
            
            $telephone = $this->message->createElement('Telephone');
            $telephone->setAttribute('PhoneLocationType', '8');
            $telephone->setAttribute('CountryAccessCode', '92');
            $telephone->setAttribute('PhoneNumber', \Illuminate\Support\Str::replaceFirst('92', '', $request['contact_number']));

            $auth_user = \Illuminate\Support\Facades\Auth::user();
            $email = $auth_user->email;
            $email = $this->message->createElement('Email', (string) $email);

            $document = $this->message->createElement('Document');
            $document->setAttribute('DocID', $passenger['d_number']);
            $document->setAttribute('DocType', d_type_airblue()[$passenger['d_type']]);
            $document->setAttribute('BirthDate', date('Y-m-d',strtotime($passenger['date_of_birth'])));
            $document->setAttribute('ExpireDate', date('Y-m-d',strtotime($passenger['d_expiry'])));
            $document->setAttribute('DocIssueCountry', $passenger['nationality']);
            $document->setAttribute('DocHolderNationality', $passenger['nationality']);

            $passengerTypeQuantity = $this->message->createElement('PassengerTypeQuantity');
            $passengerTypeQuantity->setAttribute('Code', paxTypeAirblue()[$passenger['passenger_type']]);
            $passengerTypeQuantity->setAttribute('Quantity', 1);

            $travelerRefNumber = $this->message->createElement('TravelerRefNumber');
            $travelerRefNumber->setAttribute('RPH', $key+1);

            $airTraveler->appendChild($personName);
            $airTraveler->appendChild($telephone);
            $airTraveler->appendChild($email);
            $airTraveler->appendChild($document);
            $airTraveler->appendChild($passengerTypeQuantity);
            $airTraveler->appendChild($travelerRefNumber);
            $travelerInfo->appendChild($airTraveler);
        }
    }
}
