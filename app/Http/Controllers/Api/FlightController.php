<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlightSearchRequest;
use Illuminate\Support\Facades\Response;

class FlightController extends Controller
{
    public function search(FlightSearchRequest $request)
    {
        if ($request->uid === 'mock') {
            $connector = \App\Models\Connector::where('type', 'MOCK')->where('is_enable', true)->first();
        } else {
            $connector = \App\Models\Connector::where('uuid', $request->uid)->first();
        }

        $services = [
            'SABRE'         => [\App\Services\SabreService\API::class, 'bargainFinderMax'],
            'SABRE_NDC'     => [\App\Services\SabreService\API::class, 'bargainFinderMax'],
            'PIA_HITIT'     => [\App\Services\PIAHITITService\PiaHititService::class, 'searchFlights'],
            'AIRBLUE_API'   => [\App\Services\AirBlueService\AirService::class, 'searchFlights'],
            'FLYDUBAI_API'  => [\App\Services\FlyDubaiService\FlydubaiService::class, 'searchFlights'],
            'ONEAPI'        => [\App\Services\FlyJinnahService\AirService::class, 'searchFlights'],
            'MOCK'          => [\App\Services\MockFlightService::class, 'searchFlights'],
        ];

        $isMock = false;
        $flights = [];

        // ONLY execute search if connector exists and its status is active (is_enable == true)
        if (!is_null($connector) && $connector->is_enable) {
            if ($connector->type === 'MOCK') {
                $flights = (new \App\Services\MockFlightService)->searchFlights($request->all());
                $isMock  = true;
            } elseif (isset($services[$connector->type])) {
                try {
                    [$class, $method] = $services[$connector->type];
                    $result = (new $class())->$method($request->all());

                    // Real connectors NEVER fall back to mock data!
                    // If real provider returns empty or fails, return empty list []
                    $flights = (is_array($result) && !empty($result)) ? $result : [];
                    $isMock  = false;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Connector [{$connector->type}] search failed: " . $e->getMessage());
                    $flights = [];
                    $isMock  = false;
                }
            }
        }

        // For mock flights, store each fare option in cache so booking/initiating works
        if ($isMock && is_array($flights)) {
            foreach ($flights as &$flight) {
                if (!empty($flight['fare_option'])) {
                    foreach ($flight['fare_option'] as &$fare) {
                        $bookingId = 'MOCK_' . strtoupper(uniqid());
                        $fare['booking_id'] = $bookingId;
                        set_data($bookingId, 'flight_search', 3600, array_merge($flight, [
                            'selected_fare' => $fare,
                            'is_mock'       => true,
                            'request'       => $request->all(),  // store original search request
                        ]));
                    }
                }
            }
        }

        $result = [];
        $result['departure_date']   = manage_request($request, 'departure_date');
        $result['destination']      = \App\Models\Airport::where('iata_code', manage_request($request, 'destination'))
            ->select(['name', 'country', 'iata_code', 'municipality'])->first();
        $result['origin']           = \App\Models\Airport::where('iata_code', manage_request($request, 'origin'))
            ->select(['name', 'country', 'iata_code', 'municipality'])->first();
        $result['flight_options']   = $flights;
        $result['is_mock']          = $isMock;

        return response()->json([
            'status'      => true,
            'code'        => 200,
            'journey_legs'=> $result,
        ]);
    }
}
