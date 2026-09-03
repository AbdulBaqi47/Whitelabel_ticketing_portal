<?php

namespace App\Services\PIAHITITService;

class MakeRequest
{

    public function makeLowFareRequest($query, $auth)
    {
        $passengerTypeQuantityList = [];
        foreach ($query['traveler_count'] as $key => $value) {
            if ($value > 0) {
                $passengerTypeQuantityList[] = [
                    'hasStrecher' => '',
                    'passengerType' => [
                        'code' => passenger_type()[$key],
                    ],
                    'quantity' => $value
                ];
            }
        }

        $origin = [];
        if ($query['route_type'] == 'ONEWAY' || $query['route_type'] == 'RETURN') {
            $first_origin = [
                'dateOffset' => '0',
                'departureDateTime' => $query['departure_date'],
                'originLocation' => [
                    'locationCode' => $query['origin']
                ],
                'destinationLocation' => [
                    'locationCode' => $query['destination']
                ],
                'flexibleFaresOnly' => 'false',
                'includeInterlineFlights' => 'false',
                'openFlight' => '',
            ];

            if ($query['route_type'] == 'RETURN') {
                $second_origin = [
                    'dateOffset' => '0',
                    'departureDateTime' => $query['return_date'],
                    'originLocation' => [
                        'locationCode' => $query['destination'],
                    ],
                    'destinationLocation' => [
                        'locationCode' => $query['origin'],
                    ],
                    'flexibleFaresOnly' => 'false',
                    'includeInterlineFlights' => 'false',
                    'openFlight' => '',
                ];
                $origin = [$first_origin, $second_origin];
            } else {
                $origin = $first_origin;
            }
        } else {
            for ($i = 0; $i < count($query['legs']); $i++) {
                $origin[] = [
                    'dateOffset' => '0',
                    'departureDateTime' => $query['legs'][$i]['departure_date'],
                    'originLocation' => [
                        'locationCode' => $query['legs'][$i]['origin'],
                    ],
                    'destinationLocation' => [
                        'locationCode' => $query['legs'][$i]['destination'],
                    ],
                    'flexibleFaresOnly' => 'false',
                    'includeInterlineFlights' => 'false',
                    'openFlight' => '',
                ];
            }
        }
        $params = [
            'AirAvailabilityRequest' => [
                'clientInformation' => [
                    'clientIP' => $auth['client_ip_address'],
                    'userName' => $auth['username'],
                    'password' => $auth['password'],
                    'member' => '0',
                    'preferredCurrency' => 'PKR',
                ],
                'originDestinationInformationList' => $origin,
                'travelerInformation' => [
                    'passengerTypeQuantityList' => $passengerTypeQuantityList
                ],
                'tripType' => hitit_trip_type()[$query['route_type']],
                'frequentFlyerRedemption' => '',
                'generateOnlyAvailability' => '',
                'reissue' => '',
                'showInterlineFlights' => '',
                'useCitySearch' => '',
                'seeServiceLog' => '',
                'seeTtbsLog' => '',
                'allFaresPerFlights' => '',
                'availabilityExtended' => '',
                'zedIetReservation' => ''
            ],
        ];
        return $params;
    }

    public function makeCreatePassengerNameRecordRequest($request, $itinSegments, $auth)
    {

        $airTravelerSequence = 1;
        $airTravelerList = [];
        $specialRequestDetails = [];
        $infAts = 1;

        $contact_number = $request['contact_number'];

        foreach ($request['passengers'] as $value) {
            $dobFormatted = explode("-", $value['date_of_birth']);
            $ssrDate = $dobFormatted[2] . strtoupper(date("M", mktime(0, 0, 0, $dobFormatted[1], 1))) . substr($dobFormatted[0], -2);

            switch ($value['passenger_type']) {
                case 'INF': // Infant
                    $specialRequestDetails['specialServiceRequestList'][] = createSSR($infAts, '1', 'INFT', $value['first_name'], $value['last_name'], $ssrDate);
                    break;

                case 'CNN': // Child
                    $airTravelerList[] = formatPassengerData($value, 'CHLD', $value['gender'], $contact_number);
                    $specialRequestDetails['specialServiceRequestList'][] = createSSR($airTravelerSequence, '0', 'CHLD', $value['first_name'], $value['last_name'], $ssrDate);
                    break;

                default: // Adult
                    $airTravelerList[] = formatPassengerData($value, 'ADLT', $value['gender'], $contact_number);
                    break;
            }

            $airTravelerSequence++;
        }

        return array(
            'AirBookingRequest' => array(
                'clientInformation' => array(
                    'clientIP' => $auth['client_ip_address'],
                    'member' => '0',
                    'password' => $auth['password'],
                    'userName' => $auth['username'],
                    'preferredCurrency' => "PKR",
                ),
                'airItinerary' => array(
                    'bookOriginDestinationOptions' => array(
                        'bookOriginDestinationOptionList' => $itinSegments
                    ),
                    'adviceCodeSegmentExist' => 'false'
                ),
                'airTravelerList' => $airTravelerList,
                'contactInfoList' => array(
                    'shareContactInfo' => '',
                    'shareMarketInd' => '',
                    'useForInvoicing' => '',
                    'email' => array(
                        'markedForSendingRezInfo' => '',
                        'preferred' => '',
                        'email' => (string) \Illuminate\Support\Facades\Auth::user()->email,
                        'shareMarketInd' => '',
                    ),
                    'personName' => array(
                        'givenName' => $request['passengers'][0]['first_name'] . ' ' . $request['passengers'][0]['last_name'],
                        'surname' => '',
                        'shareMarketInd' => '',
                    ),
                    'phoneNumber' => array(
                        'markedForSendingRezInfo' => '',
                        'shareMarketInd' => '',
                        'subscriberNumber' => $contact_number,
                        'preferred' => '',
                    ),
                ),
                'infantWithSeatCount' => 0,
                'requestPurpose' => 'MODIFY_PERMANENTLY_AND_CALC',
                'specialRequestDetails' => $specialRequestDetails,
                'exchangeable' => true
            )
        );
        
    }

    public function makeCancelPNRRequest($pnrRefList, $auth)
    {
        $params = array(
            'AirCancelBookingRequest' => array(
                'clientInformation' => array(
                    'clientIP' => $auth['client_ip_address'],
                    'member' => '0',
                    'password' => $auth['password'],
                    'userName' => $auth['username'],
                    'preferredCurrency' => 'PKR',
                ),
                'bookingReferenceID' => array(
                    'companyName' => array(
                        'cityCode' => $pnrRefList['companyName']['cityCode'],
                        'code' => $pnrRefList['companyName']['code'],
                        'codeContext' => $pnrRefList['companyName']['codeContext'],
                        'companyFullName' => $pnrRefList['companyName']['companyFullName'],
                        'companyShortName' => $pnrRefList['companyName']['companyShortName'],
                        'countryCode' => $pnrRefList['companyName']['countryCode']
                    ),
                    "ID" => $pnrRefList['ID'],
                    "referenceID" => $pnrRefList['referenceID'],
                ),
                'requestPurpose' => 'COMMIT'
            )
        );

        return $params;
    }

    public function makeIssueCreateRequest($pnrRefList, $auth)
    {
        $params = array(
            'AirTicketReservationRequest' => array(
                'clientInformation' => array(
                    'clientIP' => $auth['client_ip_address'],
                    'member' => '0',
                    'password' => $auth['password'],
                    'userName' => $auth['username'],
                    'preferredCurrency' => 'PKR',
                ),
                'bookingReferenceID' => [
                    'companyName' => [
                        'cityCode' => $pnrRefList['companyName']['cityCode'],
                        'code' => $pnrRefList['companyName']['code'],
                        'codeContext' => $pnrRefList['companyName']['codeContext'],
                        'companyFullName' => $pnrRefList['companyName']['companyFullName'],
                        'companyShortName' => $pnrRefList['companyName']['companyShortName'],
                        'countryCode' => $pnrRefList['companyName']['countryCode']
                    ],
                    "ID" => $pnrRefList['ID'],
                    "referenceID" => $pnrRefList['referenceID'],
                ],
                'fullfillment' => [
                    'paymentDetails' => [
                        'paymentDetailList' => [
                            'miscChargeOrder' => [
                                'avsEnabled' => '',
                                'capturePaymentToolNumber' => false,
                                'paymentCode' => 'INV',
                                'threeDomainSecurityEligible' => false,
                                'transactionFeeApplies' => '',
                                'MCONumber' => '4000012651'
                            ],
                            'payLater' => '',
                            'paymentAmount' => [
                                'currency' => [
                                    'code' => $pnrRefList['currency']['code']
                                ],
                                'mileAmount' => '',
                                'value' => $pnrRefList['value'],
                            ],
                            'paymentType' => 'MISC_CHARGE_ORDER',
                            'primaryPayment' => true,
                        ]
                    ]
                ],
                'requestPurpose' => 'COMMIT'
            )
        );

        return $params;
    }

    public function voidTKTRequest($pnrRefList, $auth)
    {
        $params = array(
            'VoidTicketRequest' => array(
                'clientInformation' => array(
                    'clientIP' => $auth['client_ip_address'],
                    'member' => '0',
                    'password' => $auth['password'],
                    'userName' => $auth['username'],
                    'preferredCurrency' => 'PKR',
                ),
                'bookingReferenceID' => [
                    'companyName' => [
                        'cityCode' => $pnrRefList['companyName']['cityCode'],
                        'code' => $pnrRefList['companyName']['code'],
                        'codeContext' => $pnrRefList['companyName']['codeContext'],
                        'companyFullName' => $pnrRefList['companyName']['companyFullName'],
                        'companyShortName' => $pnrRefList['companyName']['companyShortName'],
                        'countryCode' => $pnrRefList['companyName']['countryCode']
                    ],
                    "ID" => $pnrRefList['ID'],
                    "referenceID" => $pnrRefList['referenceID'],
                ],
                'operationType' => 'VOID_BOOKING'
            )
        );
        return $params;
    }
    public function bookingDetailRequest($pnr, $auth)
    {
        $params = array(
            'AirBookingReadRequest' => array(
                'clientInformation' => array(
                    'clientIP' => $auth['client_ip_address'],
                    'member' => '0',
                    'password' => $auth['password'],
                    'userName' => $auth['username'],
                    'preferredCurrency' => 'PKR',
                ),
                'bookingReferenceID' => [
                    "ID" => $pnr,
                ],
            )
        );
        return $params;
    }
}
