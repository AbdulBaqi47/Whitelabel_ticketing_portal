<?php

namespace App\Services\FlyDubaiService;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait Network
{
    /**
     * This fucntion is used to call third parties api's
     *
     * @param [string] $url
     * @param [array] $params
     * @param [array] $headers
     * @return array
     */
    public function fetch($url, $params, $method = 'POST')
    {
        $curl   = curl_init();
        $access_token = \App\Models\AccessToken::where('type', 'jwt')->first();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer ". $access_token->token
        ];
        curl_setopt_array($curl, [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30000,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_COOKIE         => '',
        ]);

        $resp       = curl_exec($curl);
        if (curl_errno($curl)) {
            Log::info('Error in curl call');
            Log::info($resp);
            $error_msg = curl_error($curl);
            Log::error($error_msg);
        }
        $response   = json_decode($resp, true);

        curl_close($curl);
        return $response;
    }
}
