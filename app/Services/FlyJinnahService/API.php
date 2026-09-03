<?php

namespace App\Services\FlyJinnahService;

class API
{
    /**
     * The base URL for the API requests.
     *
     * @var string
     */

    protected $restbaseURL = "https://aero-search-api-service-stage4-airarabia.isaaviation.net/aerosearch.FlightService/findOndWiseFlightCombinations";
        
    protected $baseURL = "https://reservations.flyjinnah.com/webservices/services/AAResWebServices";

    /**
     * The endpoint for searching flights.
     *
     * @var string
     */
    protected $auth;
    protected $access_token = "";

    public function __construct()
    {
        $connector = \App\Models\Connector::where('type', 'ONEAPI')->first();
        if(!empty($connector)){
            $this->auth = $connector->getConnectorCreds();
        }else{
            $this->auth = null;
        }

        $access_token = \App\Models\AccessToken::where('type', 'jinnah_token')->first();
        if ($access_token) {
            $this->access_token = $access_token->token;
        }
    }
}
