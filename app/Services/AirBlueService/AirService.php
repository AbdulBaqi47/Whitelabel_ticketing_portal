<?php

namespace App\Services\AirBlueService;

use App\Services\AirBlueService\Parser;
use App\Services\AirBlueService\API as AirAPI;
use App\Services\AirBlueService\TransverseXML;
use App\Services\AirBlueService\AirLowFareSearchRequest;

class AirService extends AirAPI
{
    use TransverseXML, \App\Traits\AirblueBooking;

    protected $auth;
    protected $make_request;

    public function __construct()
    {
        $this->make_request = new AirLowFareSearchRequest();
        $connector = \App\Models\Connector::where('type', 'AIRBLUE_API')->first();
        if(!empty($connector)){
            $this->auth = $connector->getConnectorCreds();
        }else{
            $this->auth = null;
        }
    }

    public function searchFlights($request)
    {
        $params = $this->make_request->makeRequest($request, $this->auth);
        $response = parent::soapCall($params);
        if ($response) {
            $response = $this->transverseXML($response);

            $response = $response['Body']['AirLowFareSearchResponse']['AirLowFareSearchResult'];

            if (!empty($response['Errors'])) {
                return false;
            }

            if(!array_key_exists('PricedItinerary', $response['PricedItineraries'])){
                return false;
            }

            $groupedFlights = groupFlightsByItinerary($response['PricedItineraries']['PricedItinerary']);
                
            return (new Parser())->parseAirLowFareResponse($groupedFlights, $request);
        }
    }

    public function confirmBooking($data, $request)
    {
        $params = $this->make_request->confirmBookingRequest($data, $request, $this->auth);
        $response = parent::soapCall($params);
        \Illuminate\Support\Facades\Log::info(['airblue_confirmResult_log',$response]);
        $response = $this->transverseXML($response);

        if (!empty($response['Errors'])) {
            return ['status'=>false, 'message'=>$response['Errors']['Error']];
        }

        if(array_key_exists('Errors', $response['Body']['AirBookResponse']['AirBookResult']) &&
         !empty($response['Body']['AirBookResponse']['AirBookResult']['Errors'])){
            return ['status'=>false, 'message'=>$response['Body']['AirBookResponse']['AirBookResult']['Errors']['Error']];
        }
        return ['status'=>true, 'data' =>$this->airblueBooking($response,$request,$data)];
    }

    public function readBooking($booking_pnr){
        $response = parent::soapCall($this->make_request->readBooking($this->auth, $booking_pnr));

        $response = $this->transverseXML($response);

        if (!empty($response['Errors'])) {
            return ['status'=>false, 'message'=>$response['Errors']['Error']];
        }

        return $response;
    }

    public function isssueTkt($pnr_meta)
    {
        $response = $this->transverseXML(parent::soapCall($this->make_request->confirmTicket($this->auth, $pnr_meta)));
        $issueTicketRS = $response['Body']['AirDemandTicketResponse']['AirDemandTicketResult'];

        if (!empty($issueTicketRS['Errors'])) {
            return ['status'=>false, 'message'=>$issueTicketRS['Errors']['Error']];
        }

        $ticketItemList = arrayConversion($issueTicketRS['TicketItemInfo']);
        $tickets = [];
        foreach($ticketItemList as $ticket){
            array_push($tickets,[
                'FirstName'=>$ticket['PassengerName']['GivenName'],
                'LastName'=> $ticket['PassengerName']['Surname'],
                'DocumentNumber' => $ticket['TicketNumber'],
                'LocalIssueDateTime' => \Carbon\Carbon::now()->toDateTimeLocalString(),
            ]);
        }
        return $tickets;
    }
    
    public function tktRefund($booking_pnr){
        $response = $this->transverseXML(parent::soapCall($this->make_request->createAirTktRefundRequest($this->auth, $booking_pnr)));

        $refundBookingRS = $response['Body']['AirBookModifyResponse']['AirBookModifyResult'];

        if(!empty($refundBookingRS['Warnings'])){
            return ['status'=>false, 'message'=>$refundBookingRS['Warnings']['Warning']];
        }
        if (!empty($refundBookingRS ['Errors'])) {
            return ['status'=>false, 'message'=>$refundBookingRS['Errors']['Error']];
        }
        return ['status'=>true, 'response'=>$refundBookingRS];

    }

    public function cancelBooking($booking_pnr){

        $response = $this->transverseXML(parent::soapCall($this->make_request->cancelBooking($this->auth, $booking_pnr)));
        $cancelBookingRS = $response['Body']['CancelResponse']['CancelResult'];

        if(!empty($issueTicketRS['Warnings'])){
            return ['status'=>false, 'message'=>$cancelBookingRS['Warnings']['Warning']];
        }
        if (!empty($issueTicketRS['Errors'])) {
            return ['status'=>false, 'message'=>$cancelBookingRS['Errors']['Error']];
        }
        return ['status'=>true, 'response'=>$cancelBookingRS];
    }

    // public function airBookingModify(){
    //     $response = parent::soapCall($this->make_request->airBookingModify());
    // }

    public function fetchExtra($type,array $pnr_meta) {

        $params = $this->make_request->{$type}(pnr_meta: $pnr_meta, auth: $this->auth);
        $response = parent::soapCall($params);
        
        $response = $this->transverseXML($response);

        $parserMethod = $type . 'Parser';
        return (new Parser())->$parserMethod($response);
    }

    public function extras(array $pnr_meta, $booking){
        $booking_detail = $this->readBooking($pnr_meta['pnr_info'][0]['ID']);

        $seat    = $this->fetchExtra('seatMap', $pnr_meta);
        $ancillary    = $this->fetchExtra('ancillary', $pnr_meta);
    
        return [
            'travelers' => travelers_references($booking_detail, $booking),
            'seat'    => $seat,
            'ancillary'    => $ancillary,
        ];
    }

    public function extraAddOrUpdate($request, $pnr_meta){

        $params = $this->make_request->seatAddOrUpdateRequest($pnr_meta, $this->auth, $request['seats']);
        $response = parent::soapCall($params);
        $response = $this->transverseXML($response);

        if(array_key_exists('Errors', $response['Body']['AirBookModifyResponse']['AirBookModifyResult']) &&
        !empty($response['Body']['AirBookModifyResponse']['AirBookModifyResult']['Errors'])){
           return ['status'=>false, 'message'=> $response['Body']['AirBookModifyResponse']['AirBookModifyResult']['Errors']['Error']];
        }
        $this->addOrUpdateWheelChairAndBaggage($request, $pnr_meta);
        extra_selection($pnr_meta['pnr_info'][0]['ID'], $request['booking_id']);
        return ['status' => true, 'message' => 'Successfully Added'];
    }

    public function addOrUpdateWheelChairAndBaggage($request, $pnr_meta, $i = 0)
    {
        $baggage = $request['baggage'] ?? null;
        $wheel_chair = $request['wheel_chair'] ?? null;

        $params = null;
        if ($i === 0 && !is_null($baggage)) {
            $params = $this->make_request->ancillaryAddOrUpdateRequest($pnr_meta, $this->auth, $baggage);
            parent::soapCall($params);
            $this->addOrUpdateWheelChairAndBaggage($request, $pnr_meta, 1);
        }
        elseif ($i === 1 && !is_null($wheel_chair)) {
            $params = $this->make_request->ancillaryAddOrUpdateRequest($pnr_meta, $this->auth, $wheel_chair);
            parent::soapCall($params);
        }
    }

    
}
