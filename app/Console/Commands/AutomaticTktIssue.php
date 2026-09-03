<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutomaticTktIssue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:automatic-tkt-issue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatic Issue ticket when PNR is Ticketed on backend';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bookings = \App\Models\Booking::where('status', 'confirmed')->whereIn('provider_name',['SABRE', 'SABRE_NDC'])->get();

        if(count($bookings)>0){
            foreach($bookings as $booking){
                $hold_booking = (new \App\Services\SabreService\API())->getBookingDetails($booking->booking_pnr);

                if(!array_key_exists('isTicketed', $hold_booking)){
                    continue;
                }

                if($hold_booking['isTicketed']){
                    $flight_tickets = $hold_booking['flightTickets'];

                    foreach($hold_booking['travelers'] as $key => $traveler){
                       
                        if($flight_tickets[$key]['ticketStatusName'] == 'Issued'){
                            $ticket_number = $flight_tickets[$key]['number'];
                            $document_number = $traveler['identityDocuments'][0]['documentNumber'];

                            $passenger = \App\Models\Passenger::where(['d_number'=>$document_number, 'booking_id'=>$booking->id])->firstOrFail();

                            $passenger->ticket_number = $ticket_number;

                            $passenger->save();
                            $booking->status = 'issued';
                            $booking->save();

                        }
                    }
                }
             }
        }
    }
}
