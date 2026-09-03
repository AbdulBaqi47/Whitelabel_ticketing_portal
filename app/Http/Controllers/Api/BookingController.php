<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingDetailResource;
use App\Interfaces\BookingRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FAQRCode\Google2FA;
class BookingController extends Controller
{
    private $flight_prefix = 'flight_search';
    private $confirmation_prefix = 'confirmation_prefix';

    public function __construct(protected BookingRepositoryInterface $bRI) {}

    public function initiate(Request $request)
    {
        $bookingId = $request->input('booking_id');

        if (is_array($bookingId)) {
            $request->validate([
                'booking_id' => 'required|array',
                'booking_id.*' => 'string',
            ]);
        } else {
            $request->validate([
                'booking_id' => 'required|string',
            ]);
        }
        $booking_data =[];

        if(is_array($bookingId)){
            foreach($bookingId as $bookingId){
                array_push($booking_data, get_data($bookingId, $this->flight_prefix));
            }
        }else{
            $booking_data = get_data($request->booking_id, $this->flight_prefix);
        }

        if (!empty($booking_data)) {
            $confirmation_id = random_id('BC');
            set_data($confirmation_id, $this->confirmation_prefix, (60 * 60), $booking_data);

            return Response::successResponse(200, 'Flight Initate Confirmation ID', $confirmation_id);
        } else {
            return Response::errorResponse(404, 'Your flight search session has expired. Please initiate a new search and try again. Thank you.');
        }
    }

    public function confirmAvailability(Request $request)
    {
        $request->validate([
            'confirmation_id' => 'required|string',
        ]);
    
        $confirmationData = get_data($request->confirmation_id, $this->confirmation_prefix);
    
       
        if (empty($confirmationData)) {
            return Response::errorResponse(404, 'Allowed Time for Booking is Expired. Please initiate a new search and try again. Thank you.');
        }
    
        $checkData = is_array($confirmationData) && array_key_exists(0, $confirmationData)
            ? $confirmationData[0]
            : $confirmationData;

        // Handle mock data — return a demo confirmation without hitting real APIs
        if (($checkData['API'] ?? '') === 'MOCK' || ($checkData['is_mock'] ?? false)) {
            $fare    = $checkData['selected_fare'] ?? $checkData['fare_option'][0] ?? [];
            $request = $checkData['request'] ?? [];

            // Ensure traveler_count is always present
            if (!isset($request['traveler_count'])) {
                $request['traveler_count'] = [
                    'adult_count'  => $request['adult_count']  ?? 1,
                    'child_count'  => $request['child_count']  ?? 0,
                    'infant_count' => $request['infant_count'] ?? 0,
                ];
            }

            return Response::successResponse(200, 'Flight availability confirmed (demo mode).', [
                'API'          => 'MOCK',
                'is_mock'      => true,
                'request'      => $request,
                'segment_data' => $checkData['legs'] ?? [],
                'price'        => $fare['price'] ?? $checkData['price'] ?? [],
                'airline'      => $checkData['airline'] ?? [],
                'fare_option'  => [$fare],
                'fare_break_down' => $fare['fare_break_down'] ?? [],
                'baggage'      => $fare['baggage'] ?? [],
            ]);
        }
    
        if ($checkData['API'] === 'AIRBLUE_API') {
            if (array_key_exists(0, $confirmationData)) {
                $newData = [
                    'segment_data' => array_column($confirmationData, 'segment_data'),
                    'request'      => $confirmationData[0]['request'],
                    'API'          => $confirmationData[0]['API'],
                    'price'        => summarize_prices($confirmationData),
                    'commission'   =>$confirmationData[0]['commission'],
                    'commission_type' => $confirmationData[0]['commission_type']
                ];
            } else {
                $newData = $confirmationData;
                $newData['price'] = $confirmationData['fare_info_list']['price'] ?? null;
                $newData['segment_data'] = [$confirmationData['segment_data']];
                unset($newData['fare_info_list'], $newData['fare_info'], $newData['segments']);
            }
    
            $confirmationData = $newData;
        }

        if ($checkData['API'] === 'FLYDUBAI_API') {
            if (array_key_exists(0, $confirmationData)) {
                $newData = [
                    'segment_data' => array_column($confirmationData, 'segments'),
                    'request'      => $confirmationData[0]['request'],
                    'API'          => 'FLYDUBAI_API',
                    'price'        => summarize_prices_fz(array_column($confirmationData, 'price')),
                    'commission'   =>$confirmationData[0]['commission'],
                    'commission_type' => $confirmationData[0]['commission_type']
                ];
            } else {
                $newData = [
                    'segment_data' => [$confirmationData['segments']],
                    'request'      => $confirmationData['request'],
                    'API'          => 'FLYDUBAI_API',
                    'price'        => $confirmationData['price']['price'],
                    'commission'   =>$confirmationData['commission'],
                    'commission_type' => $confirmationData['commission_type']
                ];
            }
    
            $confirmationData = $newData;
        }

        if ($checkData['API'] === 'PIA_HITIT') {
            if (array_key_exists(0, $confirmationData)) {
                $newData = [
                    'segment_data' => array_column($confirmationData, 'segments'),
                    'request'      => $confirmationData[0]['request'],
                    'API'          => 'PIA_HITIT',
                    'price'        => summarize_prices_fz(array_column($confirmationData, 'fare_info')),
                    'commission'   =>$confirmationData[0]['commission'],
                    'commission_type' => $confirmationData[0]['commission_type']
                ];
            } else {
                $newData = [
                    'segment_data' => [$confirmationData['segments']],
                    'request'      => $confirmationData['request'],
                    'API'          => 'PIA_HITIT',
                    'price'        => $confirmationData['fare_info']['price'],
                    'commission'   =>$confirmationData['commission'],
                    'commission_type' => $confirmationData['commission_type']
                ];
            }
    
            $confirmationData = $newData;
        }

        if($checkData['API'] == 'ONEAPI'){

            $extras = get_data($request['confirmation_id'], 'addons_selection');

            if(is_null($extras)){
                $extras = (new \App\Services\FlyJinnahService\AirService)->extras($confirmationData);
                set_data($request->confirmation_id, 'addons_selection', 1320, $extras);
            }

            $is_index = array_key_exists(0, $confirmationData); 
            $confirmationData = [
                'segment_data'      => $is_index ? array_column($confirmationData[0]['legs'], 'segments') : array_column($confirmationData['legs'], 'segments'),
                'request'           => $is_index ? $confirmationData[0]['request'] : $confirmationData['request'],
                'API'               => $is_index?  $confirmationData[0]['API'] : $confirmationData['API'],
                'price'             => summarize_prices(array_column(arrayConversion($confirmationData), "price")),
                'extras'            => $extras,
                'traveler_reference'=> $is_index ? $confirmationData[0]['traveler_reference'] : $confirmationData['traveler_reference'],
                // 'commission'      => $is_index ? $confirmationData[0]['commission'] : ,
                // 'commission_type' =>  $is_index ? $confirmationData[0]['commission_type'] : 
            ];
          
        }
    
        return Response::successResponse(200, 'Flight Detail', $confirmationData);
    }

    public function bookingConfirm(BookingRequest $request)
    {
        $reqData = $request->validated();
    
        try {
            $confirmation_data = get_data($reqData['confirmation_id'], $this->confirmation_prefix);
    
            if (empty($confirmation_data)) {
                throw new \Exception('Allowed Time for Booking is Expired. Please initiate a new search and try again. Thank you.');
            }
    
            $response = $this->bRI->bookingConfirm($confirmation_data, $reqData);
    
            if (array_key_exists('status', $response) && !$response['status']) {
                throw new \Exception($response['message']);
            }
    
            return Response::successResponse(201, 'Booking has been Created Successfully.', $response['data']);
    
        } catch (\Exception $e) {
            return Response::errorResponse(400, $e->getMessage());
        } catch (\Throwable $t) {
            return Response::errorResponse(500, 'Something went wrong on our end. Please try again later.');
        }
    }

    public function bookingGetById($id)
    {
        try {
            $booking = \App\Models\Booking::query()->where('booking_id', $id)->with([
                'refund_request:booking_id,refunded_amount','booked_segments.childs',
                'booked_segments.operating_airline:name,thumbnail,iata_code','booked_segments.marketing_airline:name,thumbnail,iata_code',
                'booked_segments.d_airport:name,iata_code,municipality,country','booked_segments.a_airport:name,iata_code,municipality',
                'passengers', 'airline:id,name,iata_code', 'organization:id,name'
            ])->orgFilter()->firstOrFail();

            if(is_null($booking) || empty($booking)){
                return Response::errorResponse(404, 'Booking Not Found!');
            }

            return Response::successResponse(200, 'Booking List', new BookingDetailResource($booking));
        }catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function getBooking(Request $request)
    {
        dd(json_encode($this->bRI->getBooking($request->reservation_id)));
    }

    public function cancelBooking(Request $request)
    {
        try{
            $booking = \App\Models\Booking::where(['booking_pnr'=>$request->reservation_id, 'status'=> 'confirmed'])->first();            
            if($booking){
                $booking_cancel = $this->bRI->cancelBooking($request->reservation_id, $booking->provider_name, $booking->pnr_meta);
                if($booking_cancel['status']){
                    $booking->status = 'cancelled';
                    $booking->save();
                    return Response::successResponse(200, 'Booking Successfully Cancel...');
                }else{
                    return Response::errorResponse(400, $booking['message']);
                }
            }else{
                return Response::errorResponse(400, 'Booking Already Cancel');
            }
            
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
        
    }

    public function issueTicket(Request $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = \Illuminate\Support\Facades\Auth::user();
            
            $booking = \App\Models\Booking::with('airline:id,iata_code')->where([
                'booking_pnr' => $request->reservation_id,
                'status' => 'confirmed'
            ])->first();

            if (!$booking) {
                return Response::errorResponse(400, 'Something went wrong, Booking is not in Confirmed State');
            }

            if(!$booking->payment_status){
                return Response::errorResponse(400, 'Make Payment Before Issuance!');
            }

            if ($user->otp_pref == 'google_authenticator' && $user->google_auth_verified) {                
                $google2fa = new Google2FA();
                $valid = $google2fa->verifyKey(
                    $user->google2fa_secret,
                    $request->otp
                );

                if (!$valid) {
                    return Response::errorResponse(400, 'Invalid Google Authenticator OTP.');
                }

            }else{
                if($user->issuance_otp != $request->otp){
                    return Response::errorResponse(400, 'Provided OTP is not valid, Please Provide a correct OTP and then Issue your Ticket, Thanks.');
                }

                $user->issuance_otp = null;
                $user->save();
            }
        
            $commissionablePercentage = "";

            if(in_array($booking['airline']['iata_code'], ['CZ', 'OD', 'CA', 'UL']) && ($request->has('commission') && $request->commission != "")){
                $commissionablePercentage = $request->commission;
            }

            $tickets = $this->bRI->issueTicket($request->reservation_id, $booking->provider_name, $booking->pnr_meta, $commissionablePercentage);

            if(array_key_exists('status', $tickets) && $tickets['status'] == false){
                return Response::errorResponse(400, $tickets['message']);
            }

            $booking->issued_at = \Carbon\Carbon::now()->toDateTimeString();
            $booking->status = 'issued';
            $booking->save();

            if($booking->provider_name == 'FLYDUBAI_API'){
                $booking->reservation_balance = $tickets['reservation_balance'];
                $booking->flydubai_bsp_fee = $tickets['flydubai_bsp_fee'];
                $booking->issued_amount_tkt_time = $tickets['issued_amount_tkt_time'];
                $booking->passengers()->update(['ticket_number' => $request->reservation_id.'-issued']);
            }else{

                foreach ($tickets as $ticket) {
                    $first_name = in_array($booking->provider_name, ['SABRE_NDC', 'SABRE']) ? remove_last_word($ticket['FirstName']) : $ticket['FirstName'];
                    $passenger = $booking->passengers()->where(['first_name'=> $first_name, 'last_name' => $ticket['LastName']])->first();                    
                    if ($passenger) {
                        $passenger->ticket_number = $ticket['DocumentNumber'];
                        $passenger->save();
                    }
                }
            }
            $booking->save();

            return Response::successResponse(200, 'Ticket Successfully Generated...');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function voidTickets(Request $request)
    {
        try {
            
            $booking = \App\Models\Booking::where([
                'booking_pnr' => $request->reservation_id,
                'status' => 'issued'
            ])->first();

            if (!$booking) {
                return Response::errorResponse(400, 'Something went wrong, Booking is not in Issued State');
            }
            
            $validTickets = [];
            foreach ($request->tickets as $ticket) {
                $passenger = $booking->passengers()
                    ->where('ticket_number', $ticket)
                    ->first();
                if ($passenger) {
                    $validTickets[] = $ticket;
                }
            }
            if (empty($validTickets)) {
                return Response::errorResponse(400, 'No matching ticket numbers found in passengers.');
            }

            if(!is_tkt_voidable($booking->issued_at)){
                return Response::errorResponse(400, 'This ticket can no longer be voided. It must be voided on the same day it was issued.');
            }
            
            $void = $this->bRI->voidTickets($validTickets, $booking->provider_name, $booking->pnr_meta, $booking->booking_pnr);
            if(array_key_exists('status', $void)){
                return Response::errorResponse(400, $void['message']);
            }

            if($booking->provider_name == 'PIA_HITIT'){
                $booking->status = 'cancelled';
            }elseif($request->action == 'void_ticket'){
                $booking->status = 'confirmed';
            }else if($booking->provider_name == 'SABRE_NDC'){
                $booking->status = 'voided';
            }else{
                $booking->status = 'voided';
            }
            
            $booking->save();
            foreach ($validTickets as $ticket) {
                $passenger = $booking->passengers()->where('ticket_number', $ticket)
                    ->first();
                if ($passenger) {
                    $passenger->ticket_number = null;
                    $passenger->save();
                }
            }
            return Response::successResponse(200, 'Ticket Successfully Generated...');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
        
    }

    public function voidBookingFlyDubai(Request $request){

        $request->validate([
            'confirmation_id' => 'required|string|size:6',
        ]);

        try {
            
            $booking = \App\Models\Booking::where([
                'booking_pnr' => $request->reservation_id,
                'status' => 'issued'
            ])->first();

            if (!$booking) {
                return Response::errorResponse(400, 'Something went wrong, Booking is not in Issued State');
            }
       
            if(!is_tkt_voidable_flydubai($booking->issued_at)){
                return Response::errorResponse(400, 'This ticket can no longer be voided. It must be voided in 30 minutes.');
            }
            
            $this->bRI->flyDubaiTicketVoid($booking->booking_pnr);

            $booking->status = 'voided';
            
            $booking->save();

            $booking->passengers()->update(['ticket_number'=> null]);
            return Response::successResponse(200, 'Ticket Successfully Generated...');
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function bookingSearch(Request $request){
        try{

            $validator = Validator::make($request->all(), [
                'search' => 'required|string|min:3',
            ]);

            if ($validator->fails()) {
                return Response::errorResponse(422, $validator->errors()->first());
            }

            $search = $request->search;
            $booking = \App\Models\Booking::where('booking_pnr',$search)->orWhere('booking_id', $search)
                ->orWhereHas('passengers', function($subQuery) use ($search){
                    $subQuery->where('ticket_number', 'like', '%'.$search.'%');
                })->orgFilter()->first();

                if(!empty($booking)){
                    return Response::successResponse(200, 'Booking Found', $booking->booking_id);
                }else{
                    return Response::errorResponse(404, 'Booking Not Found');
                }
        }
        catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function emailBooking(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                'email_type' => 'required|string|in:send_booking_email,send_ticket_email',
                'email' => 'required|email',
                'booking_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return Response::errorResponse(422, $validator->errors()->first());
            }
            $request = $validator->validate();
            $booking = \App\Models\Booking::whereBookingId($request['booking_id'])->with(['booked_segments.childs', 'booked_segments.operating_airline:name,thumbnail,iata_code','booked_segments.marketing_airline:name,thumbnail,iata_code','booked_segments.d_airport:name,iata_code,municipality','booked_segments.a_airport:name,iata_code,municipality' ,'passengers', 'airline:id,name,iata_code'])->first();
            if(!empty($booking)){
                $pdf_url = booking_pdf($request['booking_id'],$request['email_type']);
                \Illuminate\Support\Facades\Mail::to($request['email'])->send(new \App\Mail\SendTicket($booking->toArray(), $pdf_url));
                return Response::successResponse(200, 'Booking Found');
            }else{
                return Response::errorResponse(404, 'Booking Not Found');
            }
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function ticketOtpEmail(){
        try{
            /** @var \App\Models\User $user */
            $user = \Illuminate\Support\Facades\Auth::user();
            $otpCode = mt_rand(100000, 999999);
            $user->issuance_otp = $otpCode;
            $user->save();
            $user->notify(new \App\Notifications\sendTicketOtpNotification($otpCode));
            return Response::successResponse(200, 'Ticket Issuance Otp Sent to your email. Thanks');
        }catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }
    public function verifyOtp(Request $request){
        $request->validate([
            'otp' => 'required|numeric|digits:6',
        ]);
         /** @var \App\Models\User $user */
         $user = \Illuminate\Support\Facades\Auth::user();
         if($user->issuance_otp == $request->otp){
            $user->ticket_otp_verifed = 1;
            $user->save();
            return Response::successResponse(200, 'OTP has been Verifed Successfully, Please Proceed to Issue Your Ticket. Thanks');
         }else{
            return Response::errorResponse(400, 'OTP Not Valid, Please Provide a Valid OTP');
        }
    }

    public function downloadBooking(Request $request){
        $reqData = $request->validate([
            'booking_id' => 'required|string',
            'download_type' => 'required|string|in:booking_download,ticket_download',
        ]);
        $pdf_url = booking_pdf($reqData['booking_id'], $reqData['download_type']); 
        return Response::successResponse(200, 'Download Booking', url('/storage/booking-pdf/'.$pdf_url));
    }

    public function bookingList(Request $request){

    $booking_list = $this->bRI->bookingList($request);
        return Response::successResponse(200, 'Booking List', $booking_list);
    }

    public function refundBooking(Request $request){
        $refund_booking_response = (new \App\Services\AirBlueService\AirService())->tktRefund($request->reservation_id);
        dd($refund_booking_response);
        return $refund_booking_response;
    }   

    public function importBooking(Request $request)
    {
        $reqData = $request->validate([
            'pnr' => 'required|string|min:6|max:6',
            'connector_type' => 'required|string|in:SABRE,PIA_HITIT,ONEAPI,FLYDUBAI_API,AIRBLUE_API',
        ]);
        try{
            $exist = \App\Models\Booking::where('booking_pnr', $reqData)->exists();
            if($exist){
                return Response::errorResponse(400, 'Booking Already Exist!');
            }
            $response = $this->bRI->bookingimport($reqData);
            if ($response['status']) {
                return Response::successResponse(201, 'Booking has been Imported Successfully.', $response['data']);
            } else {
                return Response::errorResponse(400, $response['message']);
            }

        }
        catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function updateBookingPassengers(Request $request, $booking_id)
    {
        $request->validate([
            'passengers' => 'required|array',
            'passengers.*.id' => 'required|integer|exists:passengers,id',
            'passengers.*.first_name' => 'required|string',
            'passengers.*.last_name' => 'required|string',
            'passengers.*.passenger_type' => 'required|string|in:ADT,CNN,INF',
            'passengers.*.title' => 'required|string',
            'passengers.*.gender' => 'required|string|in:F,M,O',
            'passengers.*.date_of_birth' => 'required|date',
            'passengers.*.d_type' => 'nullable|string',
            'passengers.*.d_number' => 'nullable|string',
            'passengers.*.d_expiry' => 'nullable|date',
            'passengers.*.ticket_number' => 'nullable|string',
        ]);

        try {
            $booking = \App\Models\Booking::where('booking_id', $booking_id)->with('passengers')->firstOrFail();
            foreach ($request->passengers as $passengerData) {
                $passenger = $booking->passengers()->where('id', $passengerData['id'])->first();
                if ($passenger) {
                    $passenger->update([
                        'first_name' => $passengerData['first_name'],
                        'last_name' => $passengerData['last_name'],
                        'passenger_type' => $passengerData['passenger_type'],
                        'title' => $passengerData['title'],
                        'gender' => $passengerData['gender'],
                        'date_of_birth' => $passengerData['date_of_birth'],
                        'd_type' => $passengerData['d_type'] ?? null,
                        'd_number' => $passengerData['d_number'] ?? null,
                        'd_expiry' => $passengerData['d_expiry'] ?? null,
                        'ticket_number' => $passengerData['ticket_number'] ?? null,
                    ]);
                }
            }
            return Response::successResponse(200, 'Passengers updated successfully.', []);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function updateBookingFareDetails(Request $request, $booking_id)
    {
        $request->validate([
            'base_fare' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'gross_fare' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
            'total_amount' => 'nullable|numeric',
            'fare_break_down' => 'nullable|array',
            'fare_rules' => 'nullable|array',
            'applied_discount' => 'nullable|numeric',
            'applied_discount_percent' => 'nullable|numeric',
        ]);
        try {
            $booking = \App\Models\Booking::where('booking_id', $booking_id)->firstOrFail();
            $booking->update($request->only([
                'base_fare', 'tax', 'gross_fare', 'discount', 'total_amount', 'fare_break_down', 'fare_rules', 'applied_discount', 'applied_discount_percent'
            ]));
            return Response::successResponse(200, 'Fare details updated successfully.', $booking);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function updateBookingBaggage(Request $request, $booking_id)
    {
        $request->validate([
            'baggage' => 'required|array',
        ]);
        try {
            $booking = \App\Models\Booking::where('booking_id', $booking_id)->firstOrFail();
            $booking->update(['baggage' => $request->baggage]);
            return Response::successResponse(200, 'Baggage information updated successfully.', $booking);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function updateBookingFlightDetails(Request $request, $booking_id)
    {
        $request->validate([
            'departure_airport' => 'nullable|string',
            'departure_date_time' => 'nullable|date',
            'arrival_airport' => 'nullable|string',
            'arrival_date_time' => 'nullable|date',
            'r_departure_airport' => 'nullable|string',
            'r_departure_date_time' => 'nullable|date',
            'r_arrival_airport' => 'nullable|string',
            'r_arrival_date_time' => 'nullable|date',
            'booking_brand' => 'nullable|string',
            'booking_class' => 'nullable|string',
            'is_multi_city' => 'nullable|boolean',
        ]);
        try {
            $booking = \App\Models\Booking::where('booking_id', $booking_id)->firstOrFail();
            $booking->update($request->only([
                'departure_airport', 'departure_date_time', 'arrival_airport', 'arrival_date_time',
                'r_departure_airport', 'r_departure_date_time', 'r_arrival_airport', 'r_arrival_date_time',
                'booking_brand', 'booking_class', 'is_multi_city'
            ]));
            return Response::successResponse(200, 'Flight details updated successfully.', $booking);
        } catch (\Exception $e) {
            return Response::errorResponse(500, $e->getMessage());
        }
    }

    public function manaulTicketIssuance(Request $request){

        $reqData = $request->validate([
            'booking_id' => 'required|string|size:11|exists:bookings,booking_id',
            'pnr' => 'required|string|max:10|min:6',
            'tickets' => 'required|array|min:1',
            'tickets.*.passenger_id' => 'required|exists:passengers,id',
            'tickets.*.ticket_number' => 'required|string|max:50',
        
            'fare_break_down' => 'required|array',
            'fare_break_down.*.tax' => 'nullable|string',
            'fare_break_down.*.currency' => 'nullable|string|max:3',
            'fare_break_down.*.quantity' => 'nullable|integer',
            'fare_break_down.*.base_fare' => 'nullable|string',
            'fare_break_down.*.discount_psf' => 'nullable|string',
            'fare_break_down.*.gross_amount' => 'nullable|string',
            'fare_break_down.*.total_amount' => 'nullable|string',
        
            'total_amount' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0',
            'gross_fare' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
            'base_fare' => 'required|numeric|min:0',
        ]);

        try{

            \App\Models\Booking::where('booking_id', $reqData['booking_id'])->update([
                'status' => 'issued',
                'booking_pnr' => $reqData['pnr'],
                'base_fare' => $reqData['base_fare'],
                'tax' => $reqData['tax'],
                'gross_fare' => $reqData['gross_fare'],
                'discount' => $reqData['discount'],
                'total_amount' => $reqData['total_amount'],
                'fare_break_down' => $reqData['fare_break_down']
            ]);

            foreach($reqData['tickets'] as $value){
                $passenger = \App\Models\Passenger::find($value['passenger_id']);
                $passenger->ticket_number = $value['ticket_number'];
                $passenger->save();
            }
            
            return Response::successResponse(200, 'Updated Successfully!');
        }
        catch(\Exception $e){
            return Response::errorResponse(500, $e->getMessage());
        }

    }
}
