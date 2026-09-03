<?php

namespace App\Services\PIAHITITService;

trait SoapCalls
{
    /**
     * This fucntion is used to call third parties api's
     *
     * @param [string] $url
     * @param [array] $params
     * @return array
     */
    public static function fetch($url, $params)
    {
        $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
        $response = $client->GetAvailability($params);
        $response = json_encode($response);
        $response = json_decode($response, TRUE);
        return $response;
    }

    public static function cancel($url, $params)
    {
        $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
        $response = $client->CancelBooking($params);
        $response = json_encode($response);
        $response = json_decode($response, TRUE);
        return $response;
    }
    public static function issue($url, $params)
    {
        $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
        $response = $client->TicketReservation($params);
        $response = json_encode($response);
        $response = json_decode($response, TRUE);
        return $response;
    }

    public static function void($url, $params)
    {
        $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
        $response = $client->VoidTicket($params);
        $response = json_encode($response);
        $response = json_decode($response, TRUE);
        return $response;
    }
    public static function ReadBooking($url, $params)
    {
        $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
        $response = $client->ReadBooking($params);
        $response = json_encode($response);
        $response = json_decode($response, TRUE);
        return $response;
    }
}
