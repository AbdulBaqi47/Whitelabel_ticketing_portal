<?php

namespace App\Interfaces;

interface RefundTktRepositoryInterface
{
    public function generateRequest(object $booking);
    public function agencyRefundRequests();
    public function organizationRefunds();
    public function showFareRule(string $booking_id);
}