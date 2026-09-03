<?php

namespace App\Interfaces;

interface BookingRepositoryInterface
{
    public function bookingConfirm(array $data,array $request);
    public function getBooking(string $reservation_id);
    public function cancelBooking(string $reservation, ?string $provider_name, ?array $pnr_meta);
    public function issueTicket(string $reservation, ?string $provider_name, ?array $pnr_meta, ?string $commissionablePercentage);
    public function voidTickets(array $tickets, ?string $provider_name, ?array $pnr_meta, ?string $booking_pnr);
    public function bookingList($request);
    public function bookingimport($request);
    public function flyDubaiTicketVoid($booking_pnr);
}
