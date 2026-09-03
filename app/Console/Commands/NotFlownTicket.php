<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NotFlownTicket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:not-flown-ticket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if the ticket status is flown or not';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bookings = \App\Models\Booking::where([
            'status' => 'issued',
            'provider_name' => 'SABRE',
            'flown_status'=>'pending'
        ])
        ->where('departure_date_time', '<', \Carbon\Carbon::now()->subDays(2))
        ->get();

        if ($bookings) {
            foreach ($bookings as $booking) {
                $booking_detail =  (new \App\Services\SabreService\API())->getBookingDetails($booking->booking_pnr);
                if(array_key_exists('flightTickets', $booking_detail)){
                    $flown_status = $booking_detail['flightTickets'][0]['flightCoupons'][0]['couponStatus'];
                    $booking->flown_status = $flown_status == 'Flown' ? 'flown' : 'not_flown';
                    $booking->save();
                }
            }
        }
    }
}
