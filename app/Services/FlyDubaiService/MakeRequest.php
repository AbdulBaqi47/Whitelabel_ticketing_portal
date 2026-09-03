<?php

namespace App\Services\FlyDubaiService;

use Carbon\Carbon;

class MakeRequest
{

    const PAYMENT_METHOD = 'BSP';

    function makeSearchFlightRequest($request, $auth) {

        $payload = [
            "RetrieveFareQuoteDateRange" => [
                "RetrieveFareQuoteDateRangeRequest" => [
                    "SecurityGUID" => "",
                    "CarrierCodes" => [
                        "CarrierCode" => [
                            [
                                "AccessibleCarrierCode" => "FZ"
                            ]
                        ]
                    ],
                    "ChannelID" => "OTA",
                    "CountryCode" => "PK",
                    "ClientIPAddress" => "",
                    "HistoricUserName" => $auth['username'],
                    "CurrencyOfFareQuote" => "PKR",
                    "PromotionalCode" => "FAREBRANDS",
                    "IataNumberOfRequestor" => $auth['iata_number'],
                    "FullOutBoundDate" => "",
                    "FullInBoundDate" => "",
                    "CorporationID" => "-2147483648",
                    "FareFilterMethod" => "NoCombinabilityRoundtripLowestFarePerFareType",
                    "FareGroupMethod" => "WebFareTypes",
                    "InventoryFilterMethod" => "Available",
                    "FareQuoteDetails" => [
                        "FareQuoteDetailDateRange" => []
                    ]
                ]
            ]
        ];
    
        $buildPassengerInfo = function($travelerCount) {
            $info = [];
            if ($travelerCount['adult_count'] > 0) {
                $info[] = ["PassengerTypeID" => 1, "TotalSeatsRequired" => $travelerCount['adult_count']];
            }
            if ($travelerCount['child_count'] > 0) {
                $info[] = ["PassengerTypeID" => 6, "TotalSeatsRequired" => $travelerCount['child_count']];
            }
            if ($travelerCount['infant_count'] > 0) {
                $info[] = ["PassengerTypeID" => 5, "TotalSeatsRequired" => $travelerCount['infant_count']];
            }
            return $info;
        };
    
        $buildFlightSegment = function($origin, $destination, $date, $travelerCount) use ($buildPassengerInfo) {
            return [
                "Origin" => $origin,
                "Destination" => $destination,
                "PartyConfig" => "",
                "UseAirportsNotMetroGroups" => "true",
                "UseAirportsNotMetroGroupsAsRule" => "true",
                "UseAirportsNotMetroGroupsForFrom" => "true",
                "UseAirportsNotMetroGroupsForTo" => "true",
                "DateOfDepartureStart" => $date . "T00:00:00",
                "DateOfDepartureEnd" => $date . "T23:59:59",
                "FareQuoteRequestInfos" => [
                    "FareQuoteRequestInfo" => $buildPassengerInfo($travelerCount)
                ],
                "FareTypeCategory" => "1"
            ];
        };
    
        $segments = [];
        $routeType = strtoupper($request['route_type']);
    
        switch ($routeType) {
            case 'ONEWAY':
                $formattedDate = convert_dateToFDFormat($request['departure_date']);
                $payload["RetrieveFareQuoteDateRange"]["RetrieveFareQuoteDateRangeRequest"]["FullOutBoundDate"] = $formattedDate;
                $payload["RetrieveFareQuoteDateRange"]["RetrieveFareQuoteDateRangeRequest"]["FullInBoundDate"] = $formattedDate;
    
                $segments[] = $buildFlightSegment(
                    $request['origin'],
                    $request['destination'],
                    $request['departure_date'],
                    $request['traveler_count']
                );
                break;
    
            case 'RETURN':
                $payload["RetrieveFareQuoteDateRange"]["RetrieveFareQuoteDateRangeRequest"]["FullOutBoundDate"] = convert_dateToFDFormat($request['departure_date']);
                $payload["RetrieveFareQuoteDateRange"]["RetrieveFareQuoteDateRangeRequest"]["FullInBoundDate"] = convert_dateToFDFormat($request['return_date']);
    
                // Outbound
                $segments[] = $buildFlightSegment(
                    $request['origin'],
                    $request['destination'],
                    $request['departure_date'],
                    $request['traveler_count']
                );
                // Inbound
                $segments[] = $buildFlightSegment(
                    $request['destination'],
                    $request['origin'],
                    $request['return_date'],
                    $request['traveler_count']
                );
                break;
    
            case 'MULTICITY':
                if (!isset($request['legs']) || !is_array($request['legs'])) {
                    throw new \Exception("Missing or invalid legs for MULTICITY request.");
                }
    
                $firstLegDate = $request['legs'][0]['departure_date'] ?? null;
                $lastLegDate = end($request['legs'])['departure_date'] ?? null;
    
                $payload["RetrieveFareQuoteDateRange"]["RetrieveFareQuoteDateRangeRequest"]["FullOutBoundDate"] = convert_dateToFDFormat($firstLegDate);
                $payload["RetrieveFareQuoteDateRange"]["RetrieveFareQuoteDateRangeRequest"]["FullInBoundDate"] = convert_dateToFDFormat($lastLegDate);
    
                foreach ($request['legs'] as $leg) {
                    if (!isset($leg['origin'], $leg['destination'], $leg['departure_date'])) {
                        throw new \Exception("Invalid leg format in MULTICITY request.");
                    }
    
                    $segments[] = $buildFlightSegment(
                        $leg['origin'],
                        $leg['destination'],
                        $leg['departure_date'],
                        $request['traveler_count']
                    );
                }
                break;
    
            default:
                throw new \Exception("Unsupported route_type: {$routeType}");
        }
    
        $payload["RetrieveFareQuoteDateRange"]["RetrieveFareQuoteDateRangeRequest"]["FareQuoteDetails"]["FareQuoteDetailDateRange"] = $segments;
        return json_encode($payload, JSON_PRETTY_PRINT);
    }

    function makeAddToCartPayload($auth,$data) {
        $payload = [
            "currency" => "PKR",
            "IATA" => $auth['iata_number'],
            "inventoryFilterMethod" => 0,
            "securityGUID" => "",
            "originDestinations" => originDestinations($data)
        ];

        $payload = json_encode($payload, true);

        return $payload;
    }

    public function makeSummaryPnrPayload($request, $addToCartResponse, $auth, $return) {

        $auth_user = \Illuminate\Support\Facades\Auth::user();
        $email = $auth_user->email;
        $segments = [];
        $filteredPassengers = [];
        $adultOrgId = [];
        $travelsWithPersonOrgID = null;
        foreach ($request['passengers'] as $key => $passenger) {
            $ptcId = $passenger['passenger_type'] == 'ADT' ? 1 : ($passenger['passenger_type'] == 'CNN' ? 5:1);
            $personOrgId = '-'.++$key;
            if ($ptcId == 1) {
                $adultOrgId[] = $personOrgId;
                $travelsWithPersonOrgID = $personOrgId;
            }

            if ($ptcId == 5) {
                if (count($adultOrgId) > 0) {
                    $travelsWithPersonOrgID = $adultOrgId[0];
                    unset($adultOrgId[0]);
                    if (count($adultOrgId) > 0) {
                        $adultOrgId = array_values($adultOrgId);
                    }
                }
            }

            if ($ptcId == 6) {
                if (count($adultOrgId) > 0) {
                    $travelsWithPersonOrgID = $adultOrgId[0];
                }
            }
            $filteredPassengers[] = [
                "PersonOrgID" => $personOrgId,
                "FirstName" => $passenger['first_name'] ?? '',
                "LastName" => $passenger['last_name'] ?? '',
                "MiddleName" => "",
                "Age" => \Carbon\Carbon::parse($passenger['date_of_birth'])->age,
                "DOB" => $passenger['date_of_birth'],
                "Gender" => $passenger['gender'] == 'M' ? 'male' : 'female',
                "Title" => booking_passenger_title($passenger),
                "NationalityLaguageID" => 1,
                "RelationType" => "Self",
                "WBCID" => 1,
                "PTCID" => $ptcId,
                "TravelsWithPersonOrgID" => $travelsWithPersonOrgID,
                "MarketingOptIn" => true,
                "UseInventory" => false,
                "Address" => [
                    "Address1" => "",
                    "Address2" => "",
                    "City" => "",
                    "State" => "",
                    "Postal" => "",
                    "Country" => "",
                    "CountryCode" => $passenger['nationality'],
                    "AreaCode" => "",
                    "PhoneNumber" => $request['contact_number'],
                    "Display" => ""
                ],
                "Nationality" => $passenger['nationality'],
                "ProfileId" => -2147483648,
                "IsPrimaryPassenger" => $key == 1 ? true : false,
                "ContactInfos" => [
                    [
                        "Key" => 0,
                        "ContactID" => 0,
                        "PersonOrgID" => $personOrgId,
                        "ContactField" => $email,
                        "ContactType" => 4,
                        "Extension" => "",
                        "CountryCode" => "",
                        "PhoneNumber" => $request['contact_number'],
                        "Display" => "",
                        "PreferredContactMethod" => true,
                        "ValidatedContact" => false
                    ],
                    [
                        "Key" => 0,
                        "ContactID" => 0,
                        "PersonOrgID" => $personOrgId,
                        "ContactField" => $request['contact_number'],
                        "ContactType" => 2,
                        "Extension" => "",
                        "CountryCode" => "",
                        "PhoneNumber" => $request['contact_number'],
                        "Display" => "",
                        "PreferredContactMethod" => false,
                        "ValidatedContact" => false
                    ]
                ],
                "DocumentInfos" => [
                    [
                        "DocType" => '1',
                        "DocNumber" => $passenger['d_number'],
                        "IssuingCountry" => $passenger['nationality'],
                        "IssueDate" => null,
                        "ExpiryDate" => $passenger['d_expiry'].'T00:00:00'
                    ]
                ]
            ];

            if ($return) {
                for ($i=0; $i<2; $i++) {
                    $segments[] = [
                        "PersonOrgID"=> $personOrgId,
                        "FareInformationID"=> $this->getFareId($ptcId, $addToCartResponse, $i),
                        "SpecialServices"=> [],
                        "Seats"=> []
                    ];
                }
            } else {
                $segments[] = [
                    "PersonOrgID"=> $personOrgId,
                    "FareInformationID"=> $this->getFareId($ptcId, $addToCartResponse),
                    "SpecialServices"=> [],
                    "Seats"=> []
                ];
            }
        };

        $payload = [
            "ActionType" => "GetSummary",
            "ReservationInfo" => [
                "SeriesNumber" => "299",
                "ConfirmationNumber" => ""
            ],
            "CarrierCodes" => [
                [
                    "AccessibleCarrierCode" => "FZ"
                ]
            ],
            "ClientIPAddress" => "",
            "SecurityToken" => "",
            "SecurityGUID" => "",
            "HistoricUserName" => $auth['username'],
            "CarrierCurrency" => "PKR",
            "DisplayCurrency" => "PKR",
            "IATANum" => $auth['iata_number'],
            "User" => $auth['username'],
            "ReceiptLanguageID" => "1",
            "Address" => [
                "CountryCode" => "LB"
            ],
            "ContactInfos" => null,
            "Passengers" => $filteredPassengers,
            "Segments" => $segments,
            "Payments" => null
        ];

        $payload = json_encode($payload);

        return $payload;
    }

    function getFareId($ptcId, $addToCartResponse, $key = 0) {
        
        if(is_null($addToCartResponse['flightGroups'])){
            \Illuminate\Support\Facades\Log::info(['flightGroups',$addToCartResponse, $key]);
            dd($key);
        }
        foreach($addToCartResponse['flightGroups'] as $index => $group) {
            if ($index == $key) {
                foreach ($group['fareBrands'] as $brand) {
                    foreach ($brand['fareInfos'] as $fare) {
                        foreach ($fare['paxFareInfos'] as $pax) {
                            if ($pax['PTC'] == $ptcId) return $pax['fareID'];
                        }
                    }
                }
            } else {
                continue;
            }
        }
    }

    function makeCommitPnrPayload($auth) {
        $payload = [
            'ActionType' => 'CommitSummary',
            'ReservationInfo' => [
                'SeriesNumber' => '299',
                'ConfirmationNumber' => null
            ],
            'SecurityGUID' => '',
            'CarrierCodes' => [
                [
                    'AccessibleCarrierCode' => 'FZ'
                ]
                ],
                'ClientIPAddress' => '',
                'HistoricUserName' => $auth['username']
            ];

        $payload = json_encode($payload, true);

        return $payload;
    }

    function makeRetreivePnrPayload($pnrConfirmationNo, $auth) {
        $payload = [
            "ActionType" => 2,
            "ReservationInfo" => [
                "SeriesNumber" => "299",
                "ConfirmationNumber" => $pnrConfirmationNo
            ],
            "SecurityGUID" => "",
            "CarrierCodes" => [
                    [
                        "AccessibleCarrierCode" => "FZ"
                    ]
                ],
                "ClientIPAddress" => "",
                "HistoricUserName" => $auth['username'],
            ];

        $payload = json_encode($payload);

        return $payload;
    }

    public function makeGetBFeePayload($pnrConfirmationNo, $reservationBalance) {
        $payload = [
            "entityId" => (string)$pnrConfirmationNo,
            "paymentMethod" => self::PAYMENT_METHOD,
            "currency" => "PKR",
            "amount" =>(string)$reservationBalance
        ];

        $payload = json_encode($payload);

        return $payload;
    }

    function makeGetPaymentChargesAndDiscountDetailPayload($pnrConfirmationNo, $reservationBalance, $auth) {
        $payload = [
            "CheckPNRStatus" => false,
            "ReservationInfo"=> [
                "SeriesNumber"=> "299",
                "ConfirmationNumber"=> (string)$pnrConfirmationNo
            ],
            "TransactionInfo"=> [
                "CarrierCodes"=> [
                    [
                        "AccessibleCarrierCode"=> "FZ"
                    ]
                ],
                "SecurityGUID"=>"",
                "ClientIPAddress"=> "",
                "HistoricUserName"=> $auth['username']
            ],
            "PNRPayments" =>  [
                [
                    "PaymentAmount"=> $reservationBalance,
                    "PaymentMethod"=> "WBSP"
                ]
            ]
        ];

        $payload = json_encode($payload);

        return $payload;
    }

    function makeProcessFOPPayload($pnrConfirmationNo, $chargedReservationBalance, $auth) {
        $payload = [
          "CheckPNRStatus" => false,
          "ApplyDiscounts" => true,
          "TransactionInfo" => [
                "SecurityGUID" => "",
                "CarrierCodes" => [
                   [
                      "AccessibleCarrierCode" => "FZ"
                   ]
                ],
                "ClientIPAddress" => "",
                "HistoricUserName" => $auth['username']
            ],
          "ReservationInfo" => [
                "SeriesNumber" => "299",
                "ConfirmationNumber" => (string)$pnrConfirmationNo
            ],
          "PNRPayments" => [
                [
                    "AccountNumber" => "",
                    "AccountPin" => "",
                    "CardHolder" => "",
                    "CurrencyPaid" => "PKR",
                    "CVCode" => "",
                    "DatePaid" => \Carbon\Carbon::now()->toDateTimeString(),
                    "ExpirationDate" => \Carbon\Carbon::now()->toDateTimeString(),
                    "ExchangeRate" => 1,
                    "ExchangeRateDate" => \Carbon\Carbon::now()->toDateTimeString(),
                    "OriginalAmount" => $chargedReservationBalance,
                    "PaymentAmount" => $chargedReservationBalance,
                    "BalanceAmount" => 0,
                    "PaymentMethod" => "WBSP",
                    "UserID" => $auth['username'],
                    "IataNumber" => $auth['iata_number'],
                    "ReservationCurrency" => "PKR",
                    "ReservationAmount" => $chargedReservationBalance,
                    "TransactionStatus" => "NOTYETPROCESSED",
                    "BaseAmount" => $chargedReservationBalance,
                    "PaymentComment" => "",
                    "AuthorizationCode" => "",
                    "PaymentReference" => "",
                    "CardCurrency" => "",
                    "MerchantID" => "",
                    "ProcessorID" => "",
                    "ProcessorName" => "",
                    "FingerPrintingSessionID" => "",
                    "GcxID" => "1",
                    "GcxOptOption" => "1",
                    "TerminalID" => 0,
                    "ResponseMessage" => "",
                    "Payor" => (object)[]
                ]
            ]
       ];
       $payload = json_encode($payload);

        return $payload;
    }

    function makeCancelPnrPayload($pnrConfirmationNo, $type) {
        $action = $type == 'void' ? 'voidBooking' : 'cancelBooking';
        $payload = [
            "channel"=> "OTA",
            "securityGUID"=> "",
            "subChannel"=> "",
            "pointOfSale"=> "PK",
            "currency"=> "PKR",
            "carrier"=> "FZ",
            "PNR"=> (string)$pnrConfirmationNo,
            "modifyDetails"=> [
                "action"=> $action
            ]
        ];

        $payload = json_encode($payload, true);

        return $payload;
    }

    function makeCommitPnrSaveResPayload($pnrConfirmationNo, $auth) {
        $payload = [
            "ActionType" => "SaveReservation",
            "ReservationInfo" => [
                "SeriesNumber" => "299",
                "ConfirmationNumber" => (string) $pnrConfirmationNo
            ],
            "SecurityGUID" => "",
            "CarrierCodes" => [
                [
                    "AccessibleCarrierCode" => "FZ"
                ]
                ],
                "ClientIPAddress" => "",
                "HistoricUserName" => $auth['username']
            ];

        $payload = json_encode($payload, true);

        return $payload;
    }
}
