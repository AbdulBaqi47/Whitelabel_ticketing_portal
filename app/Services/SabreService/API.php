<?php

namespace App\Services\SabreService;

use App\Services\SabreService\Network as SabreServiceNetwork;
use DateTime;
use Illuminate\Support\Facades\Log;

class API
{
    use \App\Traits\SabreBooking;
    protected $baseURL = 'https://api.cert.platform.sabre.com';
    //     protected $baseURL = 'https://api.havail.sabre.com';
    //     protected $baseURL = 'https://api.platform.sabre.com';

    protected $response;
    protected $access_token;

    public function __construct()
    {
        $access_token = \App\Models\AccessToken::where('type', 'bearer')->first();
        if ($access_token) {
            $this->access_token = $access_token->token;
        }
    }
    /**
     * Calling sabre to get the access token
     *
     * @param [string] $endpoint
     * @param [string] $basicEncode
     */
    public function getSabreAccessToken($basicEncode): array
    {
        $params = 'grant_type=client_credentials';
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization:  ' . $basicEncode
        ];

        $url = $this->baseURL . '/v2/auth/token';
        $response = (new SabreServiceNetwork)->fetch($url, $params, $headers);
        return $response;
    }


    public function bargainFinderMax($request)
    {
        $connector = \App\Models\Connector::where('uuid', $request['uid'])->first();
        $url = $this->baseURL . '/v4/offers/shop';
        $params = (new MakeRequest)->makeBargainFinderMaxRquest($request, $connector);

        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];
        $response = (new SabreServiceNetwork)->fetch($url, $params, $headers);
        
        if (!isset($response)) {
            return $response;
        }

        if (array_key_exists('groupedItineraryResponse', $response) && $response['groupedItineraryResponse']['statistics']['itineraryCount'] < 0) {
            Log::error($response);
            return $response;
        }
        if (array_key_exists('status', $response) && $response['status'] === 'NotProcessed') {
            Log::error([$response['errorCode'], $response['message']]);
            return $response;
        }
        if ($response['groupedItineraryResponse']['statistics']['itineraryCount'] == 0) {
            return false;
        }
        $parsedResponse = (new Parsers)->parseBargainFinderResponse($response, $request);
        return $parsedResponse;
    }

    // NORMAL BOOKING APTO
    public function createPassengerNameRecord($data, $request)
    {
        
        $segment_data = $this->segmentData($data, $request);

        $revalidate = $this->revalidatePrice($segment_data, $data, $request);

        if(!$revalidate['status'] || $revalidate['amount'] != $data['total_price']){
            return ['status' => false, 'message' => 'The selected fare is no longer available. Please search again to view the latest fares.'];
        
        }

        $url = $this->baseURL . '/v2.4.0/passenger/records?mode=create';
        $params = (new MakeRequest)->makeCreatePassengerNameRecordRequest($segment_data, $data, $request);
        
        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];


        $response = (new Network)->fetch($url, $params, $headers);

        if (array_key_exists('CreatePassengerNameRecordRS', $response)) {
            $CreatePassengerNameRecordRS = $response['CreatePassengerNameRecordRS'];
            $ApplicationResults = $CreatePassengerNameRecordRS['ApplicationResults'];
            if ($ApplicationResults['status'] === 'Incomplete' || $ApplicationResults['status'] === 'NotProcessed') {
                return ['status' => false, 'message' => 'Failed to create PNR, something went wrong'];
            }
            if (array_key_exists('ItineraryRef', $CreatePassengerNameRecordRS)) {
                $booking_detail = $this->getBookingDetails($CreatePassengerNameRecordRS['ItineraryRef']['ID']);
                return ['status' => true, 'data' => $this->sabreBooking($CreatePassengerNameRecordRS['ItineraryRef']['ID'], $request, $data, $booking_detail), 'message' => 'Booking Created Successfully'];
            }
        } elseif (array_key_exists('errorCode', $response)) {
            Log::info("--CreatePassengerNameRecordRS error start--");
            Log::error($response['message']);
            Log::info("--CreatePassengerNameRecordRS error end--");
            return ['status' => false, 'message' => $response['message']];
        }
    }

    // NORMAL REPRICE
    public function revalidatePrice($segment_data, $data, $request)
    {
        $segment_data = $this->revalidateSegmentData($data, $request);
        $url = $this->baseURL . '/v4/shop/flights/revalidate';
        $params = (new MakeRequest)->makeRevalidatePriceRequest($segment_data, $data['request']['traveler_count']);

        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];
        $response = (new Network)->fetch($url, $params, $headers);

        if (!isset($response)) {
            return ['status' => false];
        }

        if (array_key_exists('groupedItineraryResponse', $response) && $response['groupedItineraryResponse']['statistics']['itineraryCount'] < 0) {
            return ['status' => false];
        }
        if (array_key_exists('status', $response) && $response['status'] === 'NotProcessed') {
            return ['status' => false];
        }
        if ($response['groupedItineraryResponse']['statistics']['itineraryCount'] == 0) {
            return ['status' => false];
        }

        $amount = 0;
        $pricing_information = arrayConversion($response['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0]['pricingInformation']);
        if($data['airline']['iata_code'] == 'HY'){
            $amount = $pricing_information[1]['fare']['totalFare']['totalPrice'];
        }else{
            $amount = $pricing_information[0]['fare']['totalFare']['totalPrice'];
        }

        return ['status' => true,'amount'=> $amount];
    }

    public function getBookingDetails($pnr)
    {
        $url = $this->baseURL . '/v1/trip/orders/getBooking';
        $params = json_encode(['confirmationId' => $pnr]);
        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];

        $response = (new Network)->fetch($url, $params, $headers);

        return $response;
    }

    public function cancelBooking($reservation_id)
    {
        $url = $this->baseURL . '/v1/trip/orders/cancelBooking';
        $params = (new MakeRequest)->makeCancelBookingRequest($reservation_id);
        Log::info("Booking Cancel Request ", [$params]);
        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];
        $response = (new Network)->fetch($url, $params, $headers);
        Log::info("Booking Cancel Response ", [json_encode($response)]);
        if (!array_key_exists('errors', $response)) {
            return ['status' => true, 'response' => $response];
        } else {
            Log::info("--booking details error start--");
            Log::error([$response['errors']]);
            Log::info("--booking details error end--");
            return ['status' => false, 'response' => $response['errors']];
        }
    }

    public function issueTicket($pnr, $commissionablePercentage, $provider_name)
    {
        $url = "";
        $params = "";

        $passenger_count = \App\Models\Passenger::join('bookings', 'bookings.id', '=', 'passengers.booking_id')
            ->where('bookings.booking_pnr', $pnr)
            ->select('passengers.*')
            ->count();

        if($provider_name == 'SABRE'){
            $url = $this->baseURL . '/v1.2.1/air/ticket';
            $params = (new MakeRequest)->makeIssueTicketRequest($pnr, $commissionablePercentage, $passenger_count);
        }else{
            $url = $this->baseURL . '/v1/trip/orders/fulfillFlightTickets';
            $params = (new MakeRequest)->makeIssueTicketNDCRequest($pnr, $passenger_count);
        }

        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];
        $response = (new Network)->fetch($url, $params, $headers);
        return $response;
    }

    public function voidTickets($tickets, $provider_name, $booking_pnr)
    {

        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];

        if($provider_name == 'SABRE'){

            $url = $this->baseURL . '/v1/trip/orders/voidFlightTickets';
            $params = (new MakeRequest)->makeVoidTicketRequest($tickets);
            $response = (new Network)->fetch($url, $params, $headers);
            if(request()->action == 'cancel_booking'){
                $this->cancelBooking(request()->reservation_id);
            }
            return $response;
        }

        if($provider_name == 'SABRE_NDC'){

            $url = $this->baseURL . '/v1/trip/orders/checkFlightTickets';
            $params = json_encode(["confirmationId" => $booking_pnr], true);
            $check_flights = (new Network)->fetch($url, $params, $headers);

            if(array_key_exists('cancelOffers', $check_flights)){
                
                if ($check_flights['cancelOffers'][0]['offerType'] === 'VOID') {

                    $url = $this->baseURL . '/v1/trip/orders/cancelBooking';
                    $params = (new MakeRequest)->makeCancelBookingRequest($booking_pnr, $check_flights['cancelOffers'][0]['offerItemId']);
                    $response = (new Network)->fetch($url, $params, $headers);

                    return $response;
                }else{
                    return ['status' => false, 'message' => 'Unable to Void Ticket'];
                }
            }
        }
    }

    public function refundTickets($tickets)
    {
        $url = $this->baseURL . '/v1/trip/orders/refundFlightTickets';
        $params = (new MakeRequest)->makeRefundTicketRequest($tickets);
        Log::info("Refund Ticket Request ", [$params]);
        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];
        $response = (new Network)->fetch($url, $params, $headers);

        Log::info("Void Ticket Response ", $response);
        return $response;
    }

    // NDC BOOKING
    public function createBookingNDC($data, $request){

        $revalidate = $this->repriceItenaryNDC($data['ndc_data']);

        if(!$revalidate['status'] || $revalidate['amount'] != $data['total_price']){
            return ['status' => false, 'message' => 'The selected fare is no longer available. Please search again to view the latest fares.'];
        }

        $url = $this->baseURL . '/v1/trip/orders/createBooking';
        $params = (new MakeRequest)->makeNDCCreatePNR($revalidate['offer'], $request);
    

        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];

        $response = (new Network)->fetch($url, $params, $headers);
        $booking_detail = $this->getBookingDetails($response['confirmationId']);
        return ['status' => true, 'data' => $this->sabreBooking($response['confirmationId'], $request, $data, $booking_detail, $response['booking']['bookingId']), 'message' => 'Booking Created Successfully'];
    }

    // NDC REPRICE
    private function repriceItenaryNDC($offerItem){

        $url = $this->baseURL . '/v1/offers/price';
        $params = (new MakeRequest)->makeRepriceItenaryRequest($offerItem);

        $headers = [
            'Content-Type: application/json',
            'Conversation-ID: 2021.01.DevStudio',
            'Authorization: Bearer ' . $this->access_token,
        ];
        
        $response = (new Network)->fetch($url, $params, $headers);

        // if(array_key_exists('messages', $response)){

        //     $message = [];

        //     foreach($response['messages'] as $key => $message){
        //         $message[] = $message['message'];
        //     }

        //     return [
        //         'status'  => false,
        //         'message' => $message
        //     ];

        // }

        $offer = $response['response']['offers'][0];
        return ['status' => true,'amount'=> $offer['totalPrice']['totalAmount']['amount'], 'offer' => offer_item($offer)];
    }

}
