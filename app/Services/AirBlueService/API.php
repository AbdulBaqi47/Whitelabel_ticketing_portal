<?php

namespace App\Services\AirBlueService;

use App\Services\AirBlueService\Network;

class API
{
    use  Network;
    /**
     * The base URL for the API requests.
     *
     * @var string
     */
    // protected $baseURL = "https://otatest3.zapways.com/v3.0/OTAAPI.asmx";

    // protected $baseURL = "https://otatest4.zapways.com/v2.0/OTAAPI.asmx";

    // protected $baseURL = "https://otatest.zapways.com/v3.0/OTAAPI.asmx";

    // protected $baseURL = "https://ota3.zapways.com/v3.0/OTAAPI.asmx";

     protected $baseURL = "https://ota4.zapways.com/v3.0/OTAAPI.asmx";


    public function soapCall($request)
    {
        $url = $this->baseURL;
        $header = array(
            'Content-Type:  text/xml; charset=utf-8',
        );
        return $this->fetch($url, $request, $header);
    }
}
