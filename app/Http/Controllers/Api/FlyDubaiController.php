<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class FlyDubaiController extends Controller
{
    private $flight_prefix = 'flight_search';
    
    public function anciSelection(Request $request){

        $validated = $request->validate([
            'booking_ids' => ['required', 'array'],
            'booking_ids.*' => ['required', 'string', 'size:11'],
        ]);
        
        $flights = [];

        foreach($validated['booking_ids'] as $booking_id){

            $sector = get_data($booking_id, $this->flight_prefix)['leg'];

            $flights[] = [
                "lfID"=> $sector['LFID'],
                "flightNum"=>$sector['FlightNum'],
                "depDate"=> $sector['DepartureDate'],
                "origin"=> $sector['Origin'],
                "dest"=> $sector['Destination'],
                "category"=> null,
                "services"=> null,
                "currency"=> "PKR",
                "UTCOffset"=> 0,
                "operatingCarrierCode"=> 'FZ',
                "marketingCarrierCode"=> 'FZ',
                "channel"=> "TPAPI"
            ];
        }

        $response = (new \App\Services\FlyDubaiService\FlydubaiService())->anciSelection(json_encode(['flights' =>$flights]));

        if(!$response['status']){

            return Response::successResponse(400, $response['message']);
        }

        return Response::successResponse(200, 'Baggage Details', $response['data']);
    }

    public function mealSelection(Request $request){

        $validated = $request->validate([
            'booking_id' => ['required', 'string', 'size:11'],
        ]);

        $response = (new \App\Services\FlyJinnahService\AirService())->meal_details($validated['pre_pricing_ids']);

        if(!$response['status']){

            return Response::successResponse(400, $response['message']);
        }

        return Response::successResponse(200, 'Meals', $response['data']);

    }   

    public function seatSelection(Request $request){

        $validated = $request->validate([
            'booking_id' => ['required', 'string', 'size:11'],
        ]);

        $response = (new \App\Services\FlyJinnahService\AirService())->seat_mapping($validated['pre_pricing_ids']);

        if(!$response['status']){

            return Response::successResponse(400, $response['message']);
        }

        return Response::successResponse(200, 'Seats Selection', $response['data']);
    }
}
