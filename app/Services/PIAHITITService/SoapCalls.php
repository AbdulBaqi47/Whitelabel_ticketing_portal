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
        try {
            $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
            $response = $client->GetAvailability($params);
            $response = json_encode($response);
            return json_decode($response, TRUE);
        } catch (\SoapFault $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit GetAvailability SoapFault: ' . $e->getMessage(), [
                'faultcode' => $e->faultcode ?? null,
                'faultstring' => $e->faultstring ?? null,
            ]);
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit GetAvailability Error: ' . $e->getMessage());
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        }
    }

    public static function cancel($url, $params)
    {
        try {
            $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
            $response = $client->CancelBooking($params);
            $response = json_encode($response);
            return json_decode($response, TRUE);
        } catch (\SoapFault $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit CancelBooking SoapFault: ' . $e->getMessage());
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit CancelBooking Error: ' . $e->getMessage());
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        }
    }

    public static function issue($url, $params)
    {
        try {
            $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
            $response = $client->TicketReservation($params);
            $response = json_encode($response);
            return json_decode($response, TRUE);
        } catch (\SoapFault $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit TicketReservation SoapFault: ' . $e->getMessage());
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit TicketReservation Error: ' . $e->getMessage());
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        }
    }

    public static function void($url, $params)
    {
        try {
            $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
            $response = $client->VoidTicket($params);
            $response = json_encode($response);
            return json_decode($response, TRUE);
        } catch (\SoapFault $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit VoidTicket SoapFault: ' . $e->getMessage());
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit VoidTicket Error: ' . $e->getMessage());
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        }
    }

    public static function ReadBooking($url, $params)
    {
        try {
            $client = new \SoapClient($url, array('trace' => TRUE, 'exceptions' => 1));
            $response = $client->ReadBooking($params);
            $response = json_encode($response);
            return json_decode($response, TRUE);
        } catch (\SoapFault $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit ReadBooking SoapFault: ' . $e->getMessage());
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PIA Hitit ReadBooking Error: ' . $e->getMessage());
            return ['faultstring' => $e->getMessage(), 'status' => false, 'error' => $e->getMessage()];
        }
    }
}
