<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GetPnrExpiryFromConfirmedBooking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-pnr-expiry-from-confirmed-booking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get expiry date from sabre booking details and save in database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bookings = \App\Models\Booking::whereStatus('confirmed')->where(['provider_name' => 'SABRE', 'pnr_expiry' => null])->get();
        if ($bookings) {
            foreach ($bookings as $booking) {
                $booking_detail =  (new \App\Services\SabreService\API())->getBookingDetails($booking->booking_pnr);
                $booking->pnr_expiry = pnrExpiry($booking_detail);
                $booking->save();
            }
        }
    }
}
