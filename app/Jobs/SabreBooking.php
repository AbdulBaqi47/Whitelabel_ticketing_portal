<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SabreBooking implements ShouldQueue
{
    use Queueable;
    protected $request;
    protected $pnr;
    /**
     * Create a new job instance.
     */
    public function __construct($request, $pnr)
    {
        $this->request = $request;
        $this->pnr = $pnr;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
       $booking = (new \App\Services\SabreService\API())->getBookingDetails($this->pnr);
        // \Illuminate\Support\Facades\Log::info
    }
}
