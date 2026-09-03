<?php

namespace App\Repositories;

use App\Interfaces\BookingRepositoryInterface;
use App\Services\SabreService\API;
use \App\Traits\{SabreBookingImport, AirblueBookingImport, FlyDubaiBookingImport}; 

class BookingRepository implements BookingRepositoryInterface
{
    use SabreBookingImport, AirblueBookingImport, FlyDubaiBookingImport;

    public function bookingConfirm(array $data, array $request)
    {

        $api = $data['API'] ?? ($data[0]['API'] ?? null);
        switch ($api) {
            case 'SABRE':
                $response = (new API())->createPassengerNameRecord($data, $request);
                break;

            case 'SABRE_NDC':
                $response = (new API())->createBookingNDC($data, $request);
                break;

            case 'PIA_HITIT':
                $response = (new \App\Services\PIAHITITService\PiaHititService())->createPassengerNameRecord($data, $request);
                break;

            case 'ONEAPI':
                $response = (new \App\Services\FlyJinnahService\AirService())->createBooking($data, $request);
                break;

            case 'FLYJINNAH_API':
                $response = (new \App\Services\FlyJinnahService\AirService())->createBooking($data, $request);
                break;

            case 'FLYDUBAI_API':
                $response = (new \App\Services\FlyDubaiService\FlydubaiService())->createBooking($data, $request);
                break;

            case 'AIRBLUE_API':
                $response = (new \App\Services\AirBlueService\AirService())->confirmBooking($data, $request);
                break;

            default:
                $response = ['error' => 'Unsupported API'];
                break;
        }
        return $response;
    }

    public function getBooking(string $reservation_id)
    {
        $booking_detail = (new API())->getBookingDetails($reservation_id);
        // $booking_detail = (new \App\Services\AirBlueService\AirService())->readBooking($reservation_id);
        return $booking_detail;
    }

    public function cancelBooking(string $reservation_id, ?string $provider_name, ?array $pnr_meta)
    {
        if(in_array($provider_name ,['SABRE', 'SABRE_NDC'])){
            $booking_detail = (new API())->cancelBooking($reservation_id);
        }elseif($provider_name == 'PIA_HITIT'){
            $booking_detail = (new \App\Services\PIAHITITService\PiaHititService())->cancelPNR($pnr_meta);
        }else{
            $booking_detail = (new \App\Services\AirBlueService\AirService())->cancelBooking($reservation_id);
        }
        return $booking_detail;
    }

    public function issueTicket(string $reservation_id, ?string $provider_name, ?array $pnr_meta, ?string $commissionablePercentage)
    { 
        if(in_array($provider_name,['SABRE', 'SABRE_NDC'])){

            $response = (new API())->issueTicket($reservation_id, $commissionablePercentage, $provider_name);
            if ($provider_name == 'SABRE' && array_key_exists('AirTicketRS', $response)) {
                $AirTicketRS = $response['AirTicketRS'];
                if (array_key_exists('ApplicationResults', $AirTicketRS) && $AirTicketRS['ApplicationResults']['status'] === 'Complete') {
                    $AirTicketRS = $AirTicketRS['Summary'];
                    return $AirTicketRS;
                }
                return ['status'=>false, 'response'=>'Not Eligible for ticketing'];
            }else{
                if(array_key_exists('tickets',$response)){
                    $ticketItemList = arrayConversion($response['tickets']);
                    $tickets = [];
                    foreach($ticketItemList as $ticket){
                        if($ticket['ticketStatusName'] == 'Issued'){
                            array_push($tickets, [
                                'FirstName'         => normalize_given_name($ticket['travelerGivenName']),
                                'LastName'          => $ticket['travelerSurname'],
                                'DocumentNumber'    => $ticket['number'],
                            ]);
                        }
                    }
                    return $tickets;
                } else{
                    return ['status'=>false, 'response'=> 'Something went wrong!'];
                }
            }
            
            return ['status'=>false, 'response'=> 'Something went wrong'];
        }elseif($provider_name == 'PIA_HITIT'){
            $response = (new \App\Services\PIAHITITService\PiaHititService())->isssueTkt($pnr_meta);
        }elseif($provider_name == 'FLYDUBAI_API'){
            $response = (new \App\Services\FlyDubaiService\FlydubaiService())->isssueTkt($reservation_id);
        }else{
            $response = (new \App\Services\AirBlueService\AirService())->isssueTkt($pnr_meta);
        }
        return $response;
    }

    public function voidTickets(array $tickets, ?string $provider_name, ?array $pnr_meta, ?string $booking_pnr)
    {
        if(in_array($provider_name,['SABRE', 'SABRE_NDC'])){
            return (new API())->voidTickets($tickets, $provider_name, $booking_pnr);
        }else{
            return (new \App\Services\PIAHITITService\PiaHititService())->voidTKT($pnr_meta);
        }
        
    }

    public function flyDubaiTicketVoid($booking_pnr){
        return (new \App\Services\FlyDubaiService\FlydubaiService())->cancelPNR($booking_pnr, 'void');
    }

    public function bookingList($request)
    {
        $per_page = $request->get('perPage') ?: config('app.per_page');
        $query = \App\Models\Booking::query()
            ->select([
                'id',
                'booking_id',
                'status',
                'booking_pnr',
                'provider_name',
                'pnr_expiry',
                'departure_date_time',
                'booking_brand',
                'airline_id',
                'is_refundable',
                'created_at',
                'booked_by',
                'org_id',
                'booking_type',
                'payment_status'
            ])
            ->with([
                'airline:id,iata_code,thumbnail',
                'passengers:id,title,first_name,last_name,booking_id',
                'booked_by_user:id,email,name',
                'organization:id,name,parent_id,name'
            ])
            ->orderBy('id', 'desc');

        if (
            $request->filled('q') ||
            $request->filled('booking_id') ||
            $request->filled('pnr') ||
            $request->filled('email') ||
            $request->filled('branch_id') || $request->filled('agency_id') ||
            $request->filled('status') ||
            ($request->filled('from') && $request->filled('to')) || 
            ($request->filled('travelFrom') && $request->filled('travelTo'))

        ) {
            if ($request->filled('q')) {
                $searchQuery = $request->q;
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('provider_name', 'like', "%{$searchQuery}%")
                        ->orWhere('booking_pnr', 'like', "%{$searchQuery}%")
                        ->orWhere('issued_by', 'like', "%{$searchQuery}%")
                        ->orWhere('booked_by', 'like', "%{$searchQuery}%")
                        ->orWhere('booking_class', 'like', "%{$searchQuery}%")
                        ->orWhere('booking_id', 'like', "%{$searchQuery}%");
                });
            }

            if ($request->filled('booking_id')) {

                $request->merge(['page' => 1]);
                $query->where('booking_id', $request->booking_id);
            }

            if ($request->filled('pnr')) {

                $request->merge(['page' => 1]);
                $query->where('booking_pnr', $request->pnr);
            }

            if ($request->filled('status')) {
                $statusEnum = \App\Enums\BookingStatusEnum::from($request->status);
                $query->where('status', $statusEnum->value);
            }

            if ($request->filled('from') && $request->filled('to')) {
                
                $from = \Carbon\Carbon::parse($request->from)->startOfDay()->toDateTimeString();
                $to = \Carbon\Carbon::parse($request->to)->endOfDay()->toDateTimeString();
                $query->whereBetween('created_at', [$from, $to]);
            }

            if ($request->filled('travelFrom') && $request->filled('travelTo')) {
                
                $from = \Carbon\Carbon::parse($request->travelFrom)->startOfDay()->toDateTimeString();
                $to = \Carbon\Carbon::parse($request->travelTo)->endOfDay()->toDateTimeString();
                $query->whereBetween('departure_date_time', [$from, $to]);
            }
            $user = \Illuminate\Support\Facades\Auth::user();

            if($request->filled('branch_id') && $request->filled('agency_id')){
                
                $query->whereIn('org_id', [$user->org_id, $request->agency_id]);

            } elseif($request->filled('branch_id')) {
                $baseOrgId = $user->org_id ?? $request->branch_id;

                $orgIds = \App\Models\Organization::whereParentId($baseOrgId)
                    ->pluck('id')
                    ->toArray();

                $query->whereIn('org_id', array_merge([$baseOrgId], $orgIds));
            }elseif($request->filled('agency_id')){
                $query->where('org_id', $request->agency_id);
            }

        }

        $data = $query->orgFilter()->paginate($per_page);

        $data->getCollection()->transform(function ($item) {
            $item->sector = booking_sectors($item->booked_child_segements);
            unset($item->booked_child_segements);
            return $item;
        });
        
        return $data;
    }

    public function bookingimport($request)
    {
        $response = "";
        switch ($request['connector_type']) {
            case 'SABRE':
                $response = $this->importBooking($request);
                break;
            case 'AIRBLUE_API':
                $response =$this->importAirblueBooking($request);
                break;
            case 'FLYDUBAI_API':
                $response = $this->importFlyDubaiBooking($request);
                break;
        }
        return $response;
    }
}
