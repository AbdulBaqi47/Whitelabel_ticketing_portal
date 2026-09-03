<?php

namespace App\Services\SabreService;

use App\Models\Airline;
use App\Models\Organization;
use Carbon\Carbon;

class MakeRequest
{
    protected $PCC;
    protected $printer;

    public function __construct()
    {
        $sabreCreds = \App\Models\Connector::where('type', 'SABRE')->first();
        $this->PCC = $sabreCreds->pcc;
        $this->printer = $sabreCreds->printer;
    }

    public function makeBargainFinderMaxRquest($query, $connector)
    {

        $source_type = $connector->type;

        $excludeVendors = [
            ['Code' => 'PK'],
            ['Code' => 'XY'],
            ['Code' => '9P'],
            ['Code' => 'AS'],
            ['Code' => 'PA'],
            ['Code' => 'FZ']
        ];

        if ($source_type == 'SABRE_NDC') {
            $iata = is_string($connector->iata) ? json_decode($connector->iata, true) : (array) $connector->iata;
            $ndcAirlineMap = [
                'emirates'      => 'EK',
                'qatar_airways' => 'QR',
                'saudi_airline' => 'SV',
            ];
            foreach ($ndcAirlineMap as $key => $code) {
                if (!empty($iata[$key])) {
                    $excludeVendors = array_values(array_filter($excludeVendors, fn($v) => $v['Code'] !== $code));
                } elseif (!in_array(['Code' => $code], $excludeVendors)) {
                    $excludeVendors[] = ['Code' => $code];
                }
            }
        }

        $TravelerInfoSummary = [];
        // $tourcode_script = $this->applyTourCodes();

        // if (!empty($tourcode_script)) {
        //     $TravelerInfoSummary = [
        //         "AirTravelerAvail" => [
        //             [
        //                 "PassengerTypeQuantity" => []
        //             ]
        //         ],
        //         "PriceRequestInformation" => $tourcode_script,
        //     ];
        // } else {
        $TravelerInfoSummary = [
            "AirTravelerAvail" => [
                [
                    "PassengerTypeQuantity" => []
                ]
            ],
            "PriceRequestInformation" => [
                "CurrencyCode" => 'PKR',
                "TPA_Extensions" => [
                    "BrandedFareIndicators" => [
                        "MultipleBrandedFares" => true,
                        "ReturnBrandAncillaries" => true,
                    ],
                ],
            ],
        ];
        // }

        $request['OTA_AirLowFareSearchRQ'] = [
            'ResponseVersion' => 'v4',
            'ResponseType' => 'GIR',
            'Version' => '3',
            'POS' => [
                'Source' => [],
            ],
            'OriginDestinationInformation' => [],
            'TravelPreferences' => [
                "TPA_Extensions" => [
                    "DataSources" => [
                        "NDC" => $source_type == 'SABRE_NDC' ? "Enable" : "Disable",
                        "ATPCO" => $source_type == 'SABRE' ? "Enable" : "Disable",
                        "LCC" => "Disable",
                    ],
                    "PreferNDCSourceOnTie" => [
                        "Value" => true,
                    ],
                    'ExcludeVendorPref' => $excludeVendors,
                    "NDCIndicators" => [
                        "MultipleBrandedFares" => [
                            "Value" => true,
                        ],
                        "SingleBrandedFare" => [
                            "Value" => true,
                        ]
                    ],
                ],
                "CabinPref" => [[
                    "Cabin" => SabreCabin()[$query['cabin_class']],
                    "PreferLevel" => "Preferred"
                ]],
                // "VendorPref"=>[[
                //     "Code" => 'CZ',
                //     "PreferLevel" => "Preferred",
                // ]],
                "Baggage" => [
                    "CarryOnInfo" => true,
                ],
                "ETicketDesired" => true,
            ],
            'TravelerInfoSummary' => $TravelerInfoSummary,
            'AvailableFlightsOnly' => true,
            'TPA_Extensions' => [
                "IntelliSellTransaction" => [
                    "RequestType" => [
                        "Name" => "50ITINS",
                    ],
                ],
            ],
        ];
        $sourcePos = [
            "PseudoCityCode" => $this->PCC,
            "RequestorID" => [
                "Type" => "1",
                "ID" => "1",
                "CompanyName" => [
                    "Code" => "TN",
                ],
            ],
        ];

        array_push($request['OTA_AirLowFareSearchRQ']['POS']['Source'], $sourcePos);

        if ($query['route_type'] == 'MULTICITY') {
            foreach ($query['legs'] as $key => $data) {
                $segmentsRequest = [
                    'RPH' => (string) $key,
                    'DepartureDateTime' => $data['departure_date'] . 'T00:00:00',
                    "OriginLocation" => [
                        "LocationCode" => $data['origin'],
                    ],
                    "DestinationLocation" => [
                        "LocationCode" => $data['destination'],
                    ],
                ];
                array_push($request['OTA_AirLowFareSearchRQ']['OriginDestinationInformation'], $segmentsRequest);
            }
        } else {
            $flightInfo = [
                'RPH' => '1',
                'DepartureDateTime' => $query['departure_date'] . 'T00:00:00',
                "OriginLocation" => [
                    "LocationCode" => $query['origin'],
                ],
                "DestinationLocation" => [
                    "LocationCode" => $query['destination'],
                ],
            ];
            array_push($request['OTA_AirLowFareSearchRQ']['OriginDestinationInformation'], $flightInfo);

            if ($query['route_type'] == 'RETURN') {
                $returnInfo = [
                    'RPH' => '2',
                    'DepartureDateTime' => $query['return_date'] . 'T00:00:00',
                    "OriginLocation" => [
                        "LocationCode" => $query['destination'],
                    ],
                    "DestinationLocation" => [
                        "LocationCode" => $query['origin'],
                    ],
                ];
                array_push($request['OTA_AirLowFareSearchRQ']['OriginDestinationInformation'], $returnInfo);
            }
        }

        $countADT = [
            "Code" => "ADT",
            "Quantity" => (int)$query['traveler_count']['adult_count'],
        ];
        array_push($request['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'], $countADT);

        if ($query['traveler_count']['child_count'] >= 1) {
            $countCNN = [
                "Code" => "CNN",
                "Quantity" => (int) $query['traveler_count']['child_count'],
            ];
            array_push($request['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'], $countCNN);
        }
        if ($query['traveler_count']['infant_count'] >= 1) {
            $countINF = [
                "Code" => "INF",
                "Quantity" => (int) $query['traveler_count']['infant_count'],
            ];
            array_push($request['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'], $countINF);
        }
        return json_encode($request, true);
    }

    public function makeCreatePassengerNameRecordRequest($segment_data, $data, $request)
    {
        $pricingQualifiers = [];
        $phone = $request['contact_number'];

        $auth_user = \Illuminate\Support\Facades\Auth::user();
        $crated_by = strtoupper(format_name_from_raw($auth_user->name));
        $email = $auth_user->email;

        // Contact Numbers and Emails
        $reqContactNumber = [[
            "Phone" => '-GEO GROUP OF TRAVELS',//pnr_contact(),
            "PhoneUseType" => "",
            "NameNumber" => ""
        ]];

        $reqEmail = [["Address" => $email]];

        $reqPassengerName = [];
        $reqAdvancePassenger = [];
        $reqFlightSegment = $segment_data;
        $reqPQPassengerType = [];
        $specialServiceRequests = [];

        $countADT = $countCNN = $countINF = 0;
        $nameNo = 1;

        foreach ($request['passengers'] as $value) {
            $isInfant = $value['passenger_type'] === 'INF';
            $nameNumber = $isInfant ? "1.1" : "{$nameNo}.1";

            $title = booking_passenger_title($value);
            // Passenger Name
            $passengerName = [
                "Infant" => $isInfant,
                "PassengerType" => $value['passenger_type'],
                "NameNumber" => $nameNumber,
                "GivenName" => "{$value['first_name']} {$title}",
                "Surname" => $value['last_name']
            ];

            if ($value['passenger_type'] != 'ADT') {
                $passengerName["NameReference"] = $this->getNameRef($value['passenger_type'], $value['date_of_birth']);
            }

            $reqPassengerName[] = $passengerName;

            // Advance Passenger
            $reqAdvancePassenger[] = [
                "Document" => [
                    "IssueCountry" => $value['nationality'],
                    "NationalityCountry" => $value['nationality'],
                    "ExpirationDate" => $value['d_expiry'],
                    "Number" => $value['d_number'],
                    "Type" => $value['d_type']
                ],
                "PersonName" => [
                    "GivenName" => "{$value['first_name']} {$title}",
                    // "MiddleName" => $value['last_name'],
                    "Surname" => $value['last_name'],
                    "DateOfBirth" => $value['date_of_birth'],
                    "DocumentHolder" => true,
                    "Gender" => $value['passenger_type'] == 'INF' ?  set_inf_gender()[$value['gender']] : $value['gender'],
                    "NameNumber" => $nameNumber
                ],
                "VendorPrefs" => [
                    "Airline" => ["Hosted" => false]
                ]
            ];

            if (!$isInfant) $nameNo++;

            $formattedDate = Carbon::parse($value['date_of_birth'])->format('dMy');

            if ($value['passenger_type'] === 'CNN') {
                $specialServiceRequests[] = [
                    "SSR_Code" => "CHLD",
                    "Text" => strtoupper($formattedDate),
                    "PersonName" => [
                        "NameNumber" => $nameNumber
                    ]
                ];
            }

            if ($isInfant) {
                $specialServiceRequest = [
                    "SSR_Code" => 'INFT1',
                    // "SegmentNumber" => "1,2",
                    "Text" => "{$value['last_name']}/{$value['first_name']} {$value['title']}/{$formattedDate}",
                    "PersonName" => ["NameNumber" => "1.1"],
                ];
                $specialServiceRequests[] = $specialServiceRequest;
            }

            match ($value['passenger_type']) {
                'ADT' => $countADT++,
                'CNN' => $countCNN++,
                'INF' => $countINF++,
            };
        }

        $passengerTypes = [
            'ADT' => $countADT,
            'CNN' => $countCNN,
            'INF' => $countINF
        ];

        foreach ($passengerTypes as $code => $count) {
            if ($count > 0) {
                $reqPQPassengerType[] = [
                    "Code" => $code,
                    "Quantity" => (string) $count
                ];
            }
        }

        $pricingQualifiers = [
            "PassengerType" => $reqPQPassengerType,
            // "SpecificPenalty" => [
            //     "EitherOr" => ["Any" => true]
            // ]
        ];

        if (!empty($data['fareInfoList'][1]['brand'])) {

            if ($data['airline']['iata_code'] == 'EY' && $data['fareInfoList'][1]['brand']['code'] == 'YBASIC') {
                $pricingQualifiers['SpecificFare'] = [["FareBasis" => $data['fareInfoList'][1]['basefarecode'][0]]];
            } else {
                $pricingQualifiers['Brand'] = [["content" => $data['fareInfoList'][1]['brand']['code']]];
            }
        }

        if(($data['airline']['iata_code'] == 'QR' && $data['fareInfoList'][1]['brand']['code'] == 'ECLASSIC') || 
        ($data['airline']['iata_code'] == 'EK' && $data['fareInfoList'][1]['brand']['code'] == 'ECOFLEX')){
            unset($pricingQualifiers['Brand']);
        }

        $contactEmail = $email ? str_replace('@', '//', $email) : null;
        $specialServiceRequests[] = [
            "SSR_Code" => "CTCM",
            "SegmentNumber" => "A",
            "Text" => $phone,
            "PersonName" => ["NameNumber" => "1.1"]
        ];
        $specialServiceRequests[] = [
            "SSR_Code" => "CTCE",
            "SegmentNumber" => "A",
            "Text" => $contactEmail,
            "PersonName" => ["NameNumber" => "1.1"]
        ];

        // Reservation Payload
        $requestForCurl = [
            "CreatePassengerNameRecordRQ" => [
                "version" => "2.4.0",
                "targetCity" => $this->PCC,
                "haltOnAirPriceError" => true,
                "TravelItineraryAddInfo" => [
                    "AgencyInfo" => [
                        "Ticketing" => ["TicketType" => "7T-A"]
                    ],
                    "CustomerInfo" => [
                        "ContactNumbers" => ["ContactNumber" => $reqContactNumber],
                        "PersonName" => $reqPassengerName,
                        "Email" => $reqEmail
                    ]
                ],
                "AirBook" => [
                    "RetryRebook" => ["Option" => true],
                    "HaltOnStatus" => array_map(fn($code) => ["Code" => $code], ["HL", "KK", "LL", "NN", "NO", "UC", "US"]),
                    "OriginDestinationInformation" => ["FlightSegment" => $reqFlightSegment],
                    "RedisplayReservation" => ["NumAttempts" => 2, "WaitInterval" => 500]
                ],
                "AirPrice" => [[
                    "PriceRequestInformation" => [
                        "Retain" => true,
                        "OptionalQualifiers" => [
                            "FOP_Qualifiers" => [
                                "BasicFOP" => ["Type" => "CA"]
                            ],
                            "PricingQualifiers" => $pricingQualifiers
                        ]
                    ]
                ]],
                "SpecialReqDetails" => [
                    "AddRemark" => [
                        "RemarkInfo" => [
                            "Remark" => [[
                                "Text" => "Booking by $crated_by",
                                "Type" => "Invoice"
                            ]],
                            "FOP_Remark" => ["Type" => "CASH"]
                        ]
                    ],
                    "SpecialService" => [
                        "SpecialServiceInfo" => [
                            "AdvancePassenger" => $reqAdvancePassenger,
                            "Service" => $specialServiceRequests
                        ]
                    ]
                ],
                "PostProcessing" => [
                    "RedisplayReservation" => ["waitInterval" => 100],
                    "PostBookingHKValidation" => ["waitInterval" => 200, "numAttempts" => 4],
                    "WaitForAirlineRecLoc" => ["waitInterval" => 200, "numAttempts" => 4],
                    "RedisplayReservation" => ["waitInterval" => 1000],
                    "EndTransaction" => [
                        "Source" => [
                            "ReceivedFrom" => booking_recieved_from(),
                        ]
                    ]
                ]
            ]
        ];
        return json_encode($requestForCurl, true);
    }

    public function makeNDCCreatePNR($flightOffer, $request){

        $travelers  = [];
        $auth_user  = \Illuminate\Support\Facades\Auth::user();
        $email      = $auth_user->email;
        
        foreach($request['passengers'] as $key => $passenger)
        {
            $title = booking_passenger_title($passenger);
            $travelers[$key]    = [
                "id"                    => 'Passenger' . ($key + 1),
                "passengerCode"         => $passenger['passenger_type'] === 'CNN' ? "CHD" : $passenger['passenger_type'],
                "givenName"             => "{$passenger['first_name']} {$title}",
                "surname"               => $passenger['last_name'],
                "birthDate"             => $passenger['date_of_birth'],
                "identityDocuments"     => [
                    [
                        "documentNumber"        =>  $passenger['d_number'],
                        "documentType"          =>  "PASSPORT",
                        "expiryDate"            =>  $passenger['d_expiry'],
                        "issuingCountryCode"    =>  $passenger['nationality'],
                        "residenceCountryCode"  =>  $passenger['nationality'],
                        "givenName"             =>  "{$passenger['first_name']} {$title}",
                        "surname"               =>  $passenger['last_name'],
                        "birthDate"             =>  $passenger['date_of_birth'],
                        "gender"                =>  $passenger['passenger_type'] == 'INF' ? ($passenger['gender'] == 'M' ? 'INFANT_MALE' : 'INFANT_FEMALE') : ($passenger['gender'] == 'M' ? 'MALE' : 'FEMALE'),
                    ]
                ],
            ];
            
            if($passenger['passenger_type'] === 'INF')
            {
                $travelers[$key]['passengerReference']  = 'Passenger1';
            }
            else
            {
                $travelers[$key]["emails"]    = [(string)$email];
                $travelers[$key]["phones"]    = [
                    [
                        "number"        => '+' . $request['contact_number'],
                        "label"         => "M",
                    ]
                ];
            }
        }

        $params = [
            "flightOffer"   => $flightOffer,
            "travelers"     => $travelers,
            "contactInfo" => [
                "emails"    => [(string)$email],
                "phones"    => ['+' . $request['contact_number']],
            ],
        ];

        return json_encode($params, true);
    }

    public function makeRepriceItenaryRequest($offerId){

        $request = [
            'query' => [[
                "offerItemId" => $offerId,
            ]]
        ];
        return json_encode($request, true);
    }
    
    public function makeRevalidatePriceRequest($segment_data, $traveler_count)
    {
        $passengerQuantities = [];
        
        if (!empty($traveler_count['adult_count']) && $traveler_count['adult_count'] > 0) {
            $passengerQuantities[] = [
                "Code" => "ADT",
                "Quantity" => (int)$traveler_count['adult_count'],
            ];
        }

        if (!empty($traveler_count['child_count']) && $traveler_count['child_count'] > 0) {
            $passengerQuantities[] = [
                "Code" => "CNN",
                "Quantity" => (int)$traveler_count['child_count'],
            ];
        }

        if (!empty($traveler_count['infant_count']) && $traveler_count['infant_count'] > 0) {
            $passengerQuantities[] = [
                "Code" => "INF",
                "Quantity" => (int)$traveler_count['infant_count'],
            ];
        }

        $requestForCurl = [
            "OTA_AirLowFareSearchRQ" => [
                "Version"=> "4",
                "POS" => [
                    "Source" => [
                        [
                            "PseudoCityCode" => $this->PCC,
                            "RequestorID" => [
                                "Type" => "1",
                                "ID" => "1",
                                "CompanyName" => [
                                    "Code" => "TN",
                                    "content" => "TN"
                                ]
                            ]
                        ]
                    ]
                ],
                "OriginDestinationInformation" => $segment_data,
                "TravelPreferences" => [
                    "TPA_Extensions" => [
                        "VerificationItinCallLogic" => [
                            "Value" => "B",
                        ]
                    ]
                ],
                "TravelerInfoSummary" => [
                    "SeatsRequested" => [$traveler_count['adult_count'] + $traveler_count['child_count'] + $traveler_count['infant_count']],
                    "AirTravelerAvail" => [[
                        "PassengerTypeQuantity" => $passengerQuantities
                    ]]
                ],
                "TPA_Extensions" => [
                    "IntelliSellTransaction" => [
                        "RequestType" => [
                            "Name" => "50ITINS"
                        ]
                    ],
                ]
            ]
        ];
        
        return json_encode($requestForCurl, true);
    }

    public function promoCode($mrk_air)
    {
        return \App\Models\Airline::whereIataCode($mrk_air)->pluck('tour_code');
    }

    public function getNameRef($type, $date)
    {
        $to = Carbon::parse($date);
        $from = Carbon::now();
        $nameRef = '';
        switch ($type) {
            case 'CNN':
                $diff = (int) $to->diffInYears($from);
                $nameRef = $diff < 10 ? 'C0' . $diff : 'C' . $diff;
                break;
            case 'INF':
                $diff = (int) $to->diffInMonths($from);
                $nameRef = $diff < 10 ? 'I0' . $diff : 'I' . $diff;
                break;
        }
        return $nameRef;
    }

    public function makeCancelBookingRequest($pnr, $offerID = null)
    {
        $request = [
            "confirmationId" => $pnr,
            "retrieveBooking" => false,
            "cancelAll" => true,
            "errorHandlingPolicy" => "ALLOW_PARTIAL_CANCEL"
        ];

        if(!is_null($offerID)){
            $request['offerItemId'] = $offerID;
        }

        return json_encode($request);
    }

    public function isPromoExists($mrk_air)
    {
        return \App\Models\Airline::whereIataCode($mrk_air)->whereNotNull('tour_code')->exists();
    }

    public function makeIssueTicketRequest($pnr, $commissionablePercentage, $passenger_count)
    {

        $priceQuoteRecordIds = [];
        for($i = 1; $i <= $passenger_count; $i++){
            $priceQuoteRecordIds[] = [
                "Number" => $i,
            ];
        }

        $request = [
            "AirTicketRQ" => [
                "DesignatePrinter" => [
                    "Printers" => [
                        "Hardcopy" => [
                            "LNIATA" => $this->printer,
                        ],
                        "Ticket" => [
                            "CountryCode" => "PK"
                        ],
                    ],
                ],
                "Itinerary" => [
                    "ID" => $pnr
                ],
                "Ticketing" => [[
                    "PricingQualifiers" => [
                        "PriceQuote" => [
                            [
                                "Record" => $priceQuoteRecordIds
                            ]
                        ],
                    ],
                ]],
                "PostProcessing" => [
                    "EndTransaction" => [
                        "Source" => [
                            "ReceivedFrom" => "GEO Travels & Tours"
                        ]
                    ]
                ]
            ]
        ];

        if($commissionablePercentage != ""){
            $request['AirTicketRQ']['Ticketing'][0]["MiscQualifiers"] = [
                "Commission" => [
                    "Percent" => (int) $commissionablePercentage,
                ]
            ];
        }

        if ($this->PCC) {
            $request["AirTicketRQ"]["targetCity"] = $this->PCC;
        }
        return json_encode($request, true);
    }

    public function makeIssueTicketNDCRequest($pnr, $passenger_count)
    {
        $priceQuoteRecordIds = [];
        for($i = 1; $i <= $passenger_count; $i++){
            $priceQuoteRecordIds[] = (string)$i;
        }

        $params = [
            "confirmationId"    => $pnr,
            "fulfillments"      => [
                [
                    "ticketingQualifiers" => [
                        "priceQuoteRecordIds" => $priceQuoteRecordIds,
                    ],
                    "payment" => [
                        "primaryFormOfPayment" => 1,
                    ],
                ],
            ],
            "designatePrinters" => [
                [
                    "hardcopy" => [
                        "address" => $this->printer,
                    ],
                ],
                [
                    "ticket" => [
                        "countryCode" => 'PK',
                    ],
                ],
            ],
            "formsOfPayment" => [
                [
                    "type" => "CASH",
                ],
            ],
        ];

        return json_encode($params, true);
    }

    public function applyTourCodes()
    {
        $priceRequestInformation  = [];
        $tour_codes = Airline::whereNotNull('tour_code')->get(['tour_code', 'iata_code'])->toArray();
        if (!empty($tour_codes)) {
            $NegotiatedFareCode = [];
            foreach ($tour_codes as $tour_code) {
                $NegotiatedFareCode[] = [
                    "Code" => $tour_code['tour_code'],
                    "Supplier" => [
                        [
                            "Code" => $tour_code['iata_code'],
                        ]
                    ],
                ];
            }
            $priceRequestInformation = [
                "CurrencyCode" => 'PKR',
                "NegotiatedFareCode" => $NegotiatedFareCode,
                "TPA_Extensions" => [
                    "BrandedFareIndicators" => [
                        "MultipleBrandedFares" => true,
                        // "ReturnBrandAncillaries" => true
                    ]
                ]
            ];
        } else {
            return false;
        }
        return $priceRequestInformation;
    }

    public function makeVoidTicketRequest($tickets)
    {
        $request = [
            "errorHandlingPolicy" => "HALT_ON_ERROR",
            "tickets" => $tickets,
            "targetPcc" => $this->PCC,
            "designatePrinters" => [
                [
                    "ticket" => [
                        "address" => $this->printer,
                        "countryCode" => "PK"
                    ]
                ]
            ],
        ];
        
        return json_encode($request);
    }



    public function makeRefundTicketRequest($tickets)
    {
        $request = [
            "errorHandlingPolicy" => "HALT_ON_ERROR",
            "tickets" => $tickets,
            "targetPcc" => $this->PCC,
            "designatePrinters" => [
                [
                    "ticket" => [
                        "address" => $this->printer,
                        "countryCode" => "PK"
                    ]
                ]
            ]
        ];
        return json_encode($request);
    }
}
