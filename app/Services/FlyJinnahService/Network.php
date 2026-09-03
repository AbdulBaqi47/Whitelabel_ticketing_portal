<?php

namespace App\Services\FlyJinnahService;


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
    public static function fetch($url, $params, $method = 'POST', $jsessionId = null)
    {
        $headers = [
            "Content-Type: text/xml;charset=UTF-8",
            "Accept-Encoding: gzip,deflate",
            "Cache-Control: no-cache",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246",
            "Content-length: " . strlen($params),
        ];

        if (!is_null($jsessionId)) {
            $headers[] = "Cookie: JSESSIONID={$jsessionId}";
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($curl);

        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        
        $extractedJsessionId = null;
        if (preg_match('/Set-Cookie:\s*JSESSIONID=([^;]+)/i', $header, $matches)) {
            $extractedJsessionId = $matches[1];
        }

        curl_close($curl);

        return [
            'body' => $body,
            'jsessionId' => $extractedJsessionId,
        ];
    }
}
