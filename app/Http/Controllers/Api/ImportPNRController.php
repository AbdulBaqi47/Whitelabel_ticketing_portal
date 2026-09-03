<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Services\SabreService\API;

class ImportPNRController extends Controller
{
    use \App\Traits\SabreBookingImport, \App\Traits\AirblueBookingImport;

    public function importPnr(Request $request)
    {
        $reqData = $request->validate([
            'connector_type' => 'required|string|in:SABRE,PIA_HITIT,ONEAPI,FLYDUBAI_API,AIRBLUE_API',
            'pnr' => 'required|string|min:6'
        ]);

        if (\App\Models\Booking::where('booking_pnr', $reqData['pnr'])->exists()) {
            return Response::errorResponse(404, 'This PNR is Already exists in system');
        }

        if ($reqData['connector_type'] == 'AIRBLUE_API') {
            $flights = $this->handleAirblueApi($reqData['pnr']);   
        }

        if ($reqData['connector_type'] == 'SABRE') {

            $booking_detail = (new API())->getBookingDetails($reqData['pnr']);
            if (!array_key_exists('fares', $booking_detail)) {
                return Response::errorResponse(400, 'Fare Qoute Not Store in this PNR');
            }
            if (array_key_exists('errors', $booking_detail) && $booking_detail['errors'][0]['category'] != 'WARNING') {
                return Response::errorResponse(404, $booking_detail['errors']);
            }

            if (!array_key_exists('flights', $booking_detail) || count($booking_detail['flights']) == 0) {
                return Response::errorResponse(404, 'Flight is not exists in this pnr');
            }

            if (array_key_exists('fareOffers', $booking_detail)) {
                unset($booking_detail['fareOffers'][0]['travelerIndices'], $booking_detail['fareOffers'][0]['flights'], $booking_detail['fareOffers'][0]['checkedBaggageCharges']);
            }

            $flights = $this->handleSabre($booking_detail);
        }
        return Response::successResponse(200, 'Booking Details Fetched!', $flights);
    }

}
