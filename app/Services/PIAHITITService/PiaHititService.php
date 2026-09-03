<?php

namespace App\Services\PIAHITITService;

use App\Services\PIAHITITService\MakeRequest;
use Carbon\Carbon;

class PiaHititService extends MakeRequest
{
    use \App\Services\PIAHITITService\SoapCalls, \App\Traits\HititBooking;
    protected $baseURL = "https://app.crane.aero/craneota/CraneOTAService?wsdl";

    protected $auth;

    public function __construct()
    {
        $connector = \App\Models\Connector::where('type', 'PIA_HITIT')->first();
        if(!empty($connector)){
            $this->auth = $connector->getConnectorCreds();
        }else{
            $this->auth = null;
        }
    }

    public function searchFlights($request)
    {
        $payload = $this->makeLowFareRequest($request, $this->auth);
        $response = $this->fetch($this->baseURL, $payload);
        
        if(isset($response['faultstring'])) {
            \Illuminate\Support\Facades\Log::info(['faultstring', $response['faultstring']]);
            return false;
        }else {
            return (new \App\Services\PIAHITITService\Parser())->parseLFSResponse($response, $request);
        }
        
    }

    public function createPassengerNameRecord($data, $request)
    {
        $itinSegments = $this->segmentData($data, $request);
        $params = $this->makeCreatePassengerNameRecordRequest($request, $itinSegments, $this->auth);

        $client = new \SoapClient($this->baseURL, ['trace' => true, 'exceptions' => true]);
        $response = $client->__soapCall('CreateBooking', [$params]);
        $response = json_encode($response);
        $response = json_decode($response, TRUE);

        if (array_key_exists('AirBookingResponse', $response)) {
            $CreatePassengerNameRecordRS = $response['AirBookingResponse'];
            $ApplicationResults = $CreatePassengerNameRecordRS['airBookingList']['airReservation']['bookingReferenceIDList'];
            if (!isset($ApplicationResults['ID'])) {
                return ['status'=>false,'message'=>'Failed to create PNR, something went wrong'];
            }
            if (array_key_exists('airItinerary', $CreatePassengerNameRecordRS['airBookingList']['airReservation'])) {
                $ItineraryRef = $ApplicationResults['ID'];
                $ticket_time_limit = $CreatePassengerNameRecordRS['airBookingList']['airReservation']['ticketTimeLimit'];
                $pnr_meta= array_merge($ApplicationResults, $CreatePassengerNameRecordRS['airBookingList']['ticketInfo']['totalAmount']);
                return ['status' => true, 'data' => $this->hititBooking($ItineraryRef,$pnr_meta,$ticket_time_limit,$data, $request)];
            }
        } else {
            return ['status'=>false,'message'=>'Failed to create PNR, something went wrong'];
        }

    }
    public function cancelPNR($pnr_ref_list)
    {
        $param = $this->makeCancelPNRRequest($pnr_ref_list, $this->auth);
        $data = $this->cancel($this->baseURL, $param);
        if (array_key_exists('AirCancelBookingResponse', $data)) {
            $cancePNRRS = $data['AirCancelBookingResponse']['airBookingList'];
            if (!array_key_exists('ID', $cancePNRRS['airReservation']['bookingReferenceIDList'])) {
                return ['status' => true, 'message' => 'Failed to cancel PNR, something went wrong'];
            } else {
                return ['status' => true, 'response' => 'PNR has cancel Successfullyr'];
            }
        } else {
            return ['status' => false, 'message' => 'pnr reservation is not cancel please contact to service provider'];
        }
    }

    public function isssueTkt($pnr_ref)
    {
        $params = $this->makeIssueCreateRequest($pnr_ref, $this->auth);
        $data = $this->issue($this->baseURL, $params);
        if (array_key_exists('AirTicketReservationResponse', $data)) {
            $issueTicketRS = $data['AirTicketReservationResponse']['airBookingList'];
            if (!array_key_exists('ID', $issueTicketRS['airReservation']['bookingReferenceIDList'])) {
                return ['status'=>false, 'response'=>'Failed to issue Ticket, something went wrong'];
            } else {
                $ticketItemList = arrayConversion($issueTicketRS['ticketInfo']['ticketItemList']);
                $tickets = [];
                foreach($ticketItemList as $ticket){
                    array_push($tickets,[
                        'FirstName'=>$ticket['airTraveler']['personName']['givenName'],
                        'LastName'=> $ticket['airTraveler']['personName']['surname'],
                        'DocumentNumber' => $ticket['ticketDocumentNbr'],
                        'LocalIssueDateTime' => \Carbon\Carbon::now()->toDateTimeLocalString(),
                    ]);
                }
                return $tickets;
            }
        } else {
            return ['status'=>false, 'response'=>'Ticket is not issue successfully, please contact to service provider'];
        }
    }
    public function voidTKT($pnr_ref)
    {
        $param = $this->voidTKTRequest($pnr_ref, $this->auth);
        $data = $this->void($this->baseURL, $param);
        if (array_key_exists('AirBookingModifyResponse', $data)) {
            if ($data['AirBookingModifyResponse']['airBookingList']['ticketInfo']['pricingType'] == 'REFUND') {
                // $this->cancelPNR($pnr_ref);
                // $this->setData(array_merge($data['AirBookingModifyResponse']['airBookingList']['ticketInfo']['refundPaymentAmountList'], $data['AirBookingModifyResponse']['airBookingList']['ticketInfo']['totalAmount']));
                return ['status'=>true, 'response'=>'Tickets void successfully.'];
                // return $this->response();
            } else {
                return ['status'=>false, 'response'=>'Failed to void Ticket, something went wrong'];
            }
        } else {
            return ['status'=>false, 'response'=>'Failed to void Ticket, something went wrong'];
        }
    }
    public function bookingDetail($pnr)
    {
        $param = $this->bookingDetailRequest($pnr, $this->auth);
        $data = $this->ReadBooking($this->baseURL, $param);
        return $data;
    }
}
