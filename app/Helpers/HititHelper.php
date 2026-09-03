<?php

/**
 * Helper function to create passenger data array.
 */

if (!function_exists('formatPassengerData')) {
    function formatPassengerData($passenger, $typeCode, $gender, $contact_number)
    {
        return [
            'unaccompaniedMinor' => '',
            'birthDate' => $passenger['date_of_birth'],
            'shareMarketInd' => '',
            'gender' => $gender,
            'passengerTypeCode' => $typeCode,
            'personName' => [
                'shareMarketInd' => '',
                'givenName' => $passenger['first_name'],
                'surname' => $passenger['last_name'],
                'unaccompaniedMinor' => '',
            ],
            'requestedSeatCount' => '1',
            'accompaniedByInfant' => '0',
            'hasStrecher' => '',
            'parentSequence' => '',
            'contactPerson' => [
                'shareMarketInd' => '',
                'preferred' => '',
                'useForInvoicing' => '',
                'email' => [
                    'email' => (string) \Illuminate\Support\Facades\Auth::user()->email,
                    'markedForSendingRezInfo' => '',
                    'preferred' => '',
                    'shareMarketInd' => '',
                    'useForInvoicing' => '',
                    'shareContactInfo' => '',
                ],
                'markedForSendingRezInfo' => 'false',
                'personName' => [
                    'givenName' => $passenger['first_name'],
                    'surname' => $passenger['last_name'],
                    'preferred' => '',
                    'shareMarketInd' => '',
                    'markedForSendingRezInfo' => '',
                    'useForInvoicing' => '',
                    'shareContactInfo' => '',
                ],
                'phoneNumber' => [
                    'markedForSendingRezInfo' => '',
                    'shareMarketInd' => '',
                    'subscriberNumber' => $contact_number,
                    'preferred' => '',
                ],
                'shareMarketInd' => 'true',
                'socialSecurityNumber' => '',
                'shareContactInfo' => 'true',
            ],
            'documentInfoList' => [
                'birthDate' => $passenger['date_of_birth'] .'T00:00:00+05:00',
                'docHolderFormattedName' => [
                    'givenName' => $passenger['first_name'],
                    'shareMarketInd' => false,
                    'surname' => $passenger['last_name']
                ],
                'gender' => $gender,
                'docHolderNationality' => 'PAK',
                'docID' => $passenger['d_number'],
                'docType' => 'PASSPORT',
            ]
        ];
    }
}

/**
 * Helper function to create SSR details.
 */
if (!function_exists('createSSR')) {
    function createSSR($sequence, $flightSegment, $code, $firstName, $lastName, $explanation)
    {
        return [
            'airTravelerSequence' => $sequence,
            'flightSegmentSequence' => $flightSegment,
            'SSR' => [
                'code' => $code,
                'explanation' => "{$lastName}/{$firstName} {$explanation}",
                'allowedQuantityPerPassenger' => '',
                'bundleRelatedSsr' => '',
                'extraBaggage' => '',
                'free' => '',
                'showOnItinerary' => '',
                'unitOfMeasureExist' => '',
                'ticketed' => '',
                'exchangeable' => '',
                'iciAllowed' => '',
                'refundable' => ''
            ],
            'serviceQuantity' => '1',
            'status' => ($code === 'DOCS') ? 'HK' : 'NN',
            'ticketed' => '',
        ];
    }
}
if(!function_exists('ssrDateFormator')){
    function ssrDateFormator($date)
    {
        $date = explode("-", $date);
        return $date[2] . strtoupper(date("M", mktime(null, null, null, $date[1], 1))) . substr($date[0], -2);
    }
}