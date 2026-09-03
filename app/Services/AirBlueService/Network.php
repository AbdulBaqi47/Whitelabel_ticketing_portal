<?php

namespace App\Services\AirBlueService;


trait Network
{
    /**
     * This fucntion is used to call third parties api's
     *
     * @param [string] $url
     * @param [array] $params
     * @param [array] $headers
     * @return void
     */
    public static function fetch($url, $params, $headers, $method = 'POST')
    {

        $curl   = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30000,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_COOKIE         => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLCERT        => config_path('certificates/cert_lima.pem'),
            CURLOPT_SSLKEY         => config_path('certificates/key_lima.pem'),
        ]);
        
        $response       = curl_exec($curl);
        if ($response === false) {
            dd('Curl error: ' . curl_error($curl));
        }
        curl_close($curl);
        return $response;
    }
}
