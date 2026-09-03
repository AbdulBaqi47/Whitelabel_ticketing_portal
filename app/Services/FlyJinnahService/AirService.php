<?php

namespace App\Services\FlyJinnahService;

use App\Services\FlyJinnahService\{Network, TransverseXML, API as AirAPI, MakeRequest};
use App\Traits\FlyJinnahBooking;

class AirService extends AirAPI
{
    use TransverseXML, Network, FlyJinnahBooking;
    private $flyjinnah_prefix = 'flyjinnah_price';
    private $confirmation_prefix = 'confirmation_prefix';

    /**
     * Search for flights.
     *
     * This method makes a request to a specific URL and returns the parsed response.
     *
     * @param mixed $request The request object.
     * @return mixed The parsed response of the flight search.
     */
    public function searchFlights($request)
    {
        $params = (new MakeRequest())->getAirAvailRequest($request);
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization'         =>  'Bearer ' . $this->access_token,
            'X-AERO-SALES-CHANNEL'  => 'OTA',
            'X-AERO-JOURNEY-TYPE'   => 'ONEWAY, RETURN',
            'X-AERO-USERID'         => $this->auth['username'],
            'X-AERO-AGENT-CODE'     => $this->auth['agent_code'],
        ])->post($this->restbaseURL, $params);

        $sectors = normalize_jinnah_sectors(json_decode($response, true));

        $flights = [];

        foreach ($sectors as $key => $sector) {
            $price_params = (new MakeRequest())->priceRequest($request, $this->auth, $sector);

            $prices = $this->fetch($this->baseURL, $price_params);

            if($prices['body'] == ""){
                continue;
            }
            $data = $this->transverseXML($prices['body']);

            if($data && !empty($data['Body']['OTA_AirPriceRS']['Errors'])) {
                continue;
            }
            $flights[] = (new \App\Services\FlyJinnahService\Parser)->searchFlightParser($data, $request, $prices['jsessionId']);
        }

        return $flights;
    }

    public function extras(array $confirmation_data)
    {
        $confirmation_data = arrayConversion($confirmation_data);
        $price_request = (new MakeRequest())->finalizePriceRequest($confirmation_data, $this->auth);

        $price_response = $this->fetch($this->baseURL, $price_request, 'POST', $confirmation_data[0]['jsessionId']);

        $transverse_price = $this->transverseXML($price_response['body']);
        
        if ($transverse_price && !empty($transverse_price['Body']['OTA_AirPriceRS']['Errors'])) {
            return [
                'status' => false,
                'message' => $transverse_price['Body']['OTA_AirPriceRS']['Errors']['Error']['ShortText'] ?? 'Unknown pricing error'
            ];
        }

        $parser = new \App\Services\FlyJinnahService\Parser();
        $baggage = $this->fetchExtra('baggage', 'AA_OTA_AirBaggageDetailsRS', $confirmation_data, $parser);
        $meal    = $this->fetchExtra('meal', 'AA_OTA_AirMealDetailsRS', $confirmation_data, $parser);
        $seat    = $this->fetchExtra('seat', 'OTA_AirSeatMapRS', $confirmation_data, $parser);

        return [
            'baggage' => $baggage,
            'meal'    => $meal,
            'seat'    => $seat,
        ];
    }
    /**
     * Generic handler for baggage/meal/seat requests
     */
    private function fetchExtra(string $type, string $responseKey, array $confirmation_data, $parser)
    {
        $makeRequest = new MakeRequest();

        $params = $makeRequest->{$type}($confirmation_data, $this->auth);
        // send request
        $response = $this->fetch($this->baseURL, $params, 'POST', $confirmation_data[0]['jsessionId']);
        $parsedXml = $this->transverseXML($response['body']);

        // handle error
        if ($parsedXml && !empty($parsedXml['Body'][$responseKey]['Errors'])) {
            throw new \Exception($parsedXml['Body'][$responseKey]['Errors']['Error']['ShortText'] ?? 'Unknown ' . $type . ' error');
        }

        $bundle_services = $type == 'baggage' ? array_column($confirmation_data, 'bundledService') : bundle_service_manipulation($confirmation_data);

        $parserMethod = $type . 'Parser';
        return $parser->$parserMethod($parsedXml, $bundle_services);
    }

    public function addBundleServices($confirmation_data, $extra)
    {
        $price_request = (new MakeRequest())->addBundleServices($this->auth, $confirmation_data, $extra);

        $price_response = $this->fetch($this->baseURL, $price_request, 'POST', $confirmation_data[0]['jsessionId']);

        $extras_response = $this->transverseXML($price_response['body']);

        if ($extras_response && empty($extras_response['Body']['OTA_AirPriceRS']['Errors'])) {
            return ['status' => true, 'data' => $extras_response];
        } else {
            return ['status' => false, 'message' => $extras_response['Body']['OTA_AirPriceRS']['Errors']['Error']['ShortText']];
        }
    }

    public function createBooking($confirmation, $request)
    {

        $booking_res = $this->retreivePnr('61FFBO');

        $extras = get_data($request['confirmation_id'], 'addons_selection');

        extra_included_item_collection($booking_res, $extras, request()->all());

        $confirmation = arrayConversion($confirmation);

        $this->addBundleServices($confirmation, request()->all());

        $reqCB = (new MakeRequest())->createBookingRequest($this->auth, $confirmation, $request);

        $responseCB = $this->fetch($this->baseURL, $reqCB, 'POST', $confirmation[0]['jsessionId']);
        $booking_response = $this->transverseXML($responseCB['body']);

        \Illuminate\Support\Facades\Log::info(json_encode($booking_response));
        if ($booking_response && !empty($booking_response['Body']['OTA_AirBookRS']['Errors'])) {
            return ['status' => false, 'message' => $booking_response['Body']['OTA_AirBookRS']['Errors']['Error']['ShortText']];
        }

        return ['status'=>true, 'data' => $this->create_booking($booking_response, $request, $confirmation)];
    }

    public function retreivePnr($confirmation_id)
    {
        $price_request = (new MakeRequest())->retrievePNRRequest($confirmation_id, $this->auth);
        $price_response = $this->fetch($this->baseURL, $price_request);

        $retreivePnr = $this->transverseXML($price_response['body']);

        return json_encode($retreivePnr);
    }

    public function issueTicket($confirmation_id)
    {
        $price_request = (new MakeRequest())->issueTicket($this->auth, $confirmation_id, '98107.54', 'TID$1751755876789237071-app-2155');
        $price_response = $this->fetch($this->baseURL, $price_request);


        $price_response = $this->transverseXML($price_response['body']);
        dd($price_response);
    }
}
