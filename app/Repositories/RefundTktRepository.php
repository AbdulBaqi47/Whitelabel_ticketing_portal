<?php

namespace App\Repositories;

use App\Interfaces\RefundTktRepositoryInterface;
use App\Models\RefundRequest;

class RefundTktRepository implements RefundTktRepositoryInterface
{
    public function __construct(protected RefundRequest $model)
    {}

    public function generateRequest(object $booking){
        
        $current_user = \Illuminate\Support\Facades\Auth::user();

        $this->model::create([
            'org_id' => $current_user->org_id,
            'requested_by_id' => $current_user->id,
            'status' => 'processing',
            'booking_id' => $booking->id,
        ]);

        return true;
    }

    public function agencyRefundRequests(){}

    public function organizationRefunds(){}

    public function showFareRule(string $booking_id){}
}
