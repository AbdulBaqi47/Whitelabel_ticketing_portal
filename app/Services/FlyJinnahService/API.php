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
    protected $environment = "UAT";

    public function __construct()
    {
        $connector = \App\Models\Connector::where('type', 'ONEAPI')->first();
        if(!empty($connector)){
            $this->auth = $connector->getConnectorCreds();
            $this->environment = $connector->environment ?? 'UAT';
            if ($connector->environment === 'PROD') {
                $this->restbaseURL = "https://aero-search-api-service-airarabia.isaaviation.net/aerosearch.FlightService/findOndWiseFlightCombinations";
            } else {
                $this->restbaseURL = "https://aero-search-api-service-stage4-airarabia.isaaviation.net/aerosearch.FlightService/findOndWiseFlightCombinations";
            }
        }else{
            $this->auth = null;
        }

        $access_token = \App\Models\AccessToken::where('type', 'jinnah_token')->first();
        if ($access_token) {
            $this->access_token = $access_token->token;
        }
    }

    public function getAccessToken(): string
    {
        if (!empty($this->access_token)) {
            return $this->access_token;
        }

        if (empty($this->auth) || empty($this->auth['username'])) {
            return "";
        }

        try {
            $authURL = ($this->environment === 'PROD')
                ? "https://aero-suite-prod-airarabia.accelaero.com/api/auth/authenticate"
                : "https://aero-suite-stage4-airarabia.isaaviation.net/api/auth/authenticate";

            $payload = [
                "login" => $this->auth['username'],
                "password" => $this->auth['password'] ?? '',
            ];

            $response = \Illuminate\Support\Facades\Http::timeout(10)->post($authURL, $payload);
            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['tokenPair']['accessToken'])) {
                    $this->access_token = $data['tokenPair']['accessToken'];
                    \App\Models\AccessToken::updateOrCreate(['type' => 'jinnah_token'], [
                        'type' => 'jinnah_token',
                        'token' => $this->access_token,
                        'expiry_date' => now()->addHours(12)
                    ]);
                    return $this->access_token;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('FlyJinnah getAccessToken error: ' . $e->getMessage());
        }

        return $this->access_token ?? "";
    }
}
