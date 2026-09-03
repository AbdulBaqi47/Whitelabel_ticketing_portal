<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class cancelAllBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cancel-all-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $today = \Carbon\Carbon::today();
        $bookings = \App\Models\Booking::where(['status'=>'confirmed', 'provider_name' => 'SABRE'])->whereDate('created_at', '<', $today)->get();

        foreach ($bookings as $booking) {

                $hold_booking = (new \App\Services\SabreService\API())->getBookingDetails($booking->booking_pnr);
                if(array_key_exists('isTicketed', $hold_booking) && $hold_booking['isTicketed']){
                    $flight_tickets = $hold_booking['flightTickets'];

                    foreach($hold_booking['travelers'] as $key => $traveler){
                    
                        if($flight_tickets[$key]['ticketStatusName'] == 'Issued'){
                            $ticket_number = $flight_tickets[$key]['number'];
                            $document_number = $traveler['identityDocuments'][0]['documentNumber'];

                            $passenger = \App\Models\Passenger::where(['d_number'=>$document_number, 'booking_id'=>$booking->id])->first();

                            if($passenger){
                                $passenger->ticket_number = $ticket_number;
                                $passenger->save();
                            }

                            $booking->status = 'issued';
                            $booking->save();
                        }
                    }
                }else{
                    (new \App\Services\SabreService\API())->cancelBooking($booking->booking_pnr);
                    $booking->status = 'expired';
                    $booking->save();
                }
        }
    }
}
