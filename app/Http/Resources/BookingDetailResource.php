<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
    */
    public function toArray(Request $request): array
    {
       return [
            'passengers'                    => $this->passengers,
            'booking_id'                    => $this->booking_id,
            'booking_pnr'                   => $this->booking_pnr,
            'pnr_expiry'                    => $this->pnr_expiry,
            'airline'                       => $this->airline,
            'base_fare'                     => $this->base_fare,
            'tax'                           => $this->tax,
            'gross_fare'                    => $this->gross_fare,
            'discount'                      => $this->discount,
            'total_amount'                  => $this->total_amount,
            'fare_break_down'               => $this->fare_break_down,
            'booking_brand'                 => $this->booking_brand,
            'status'                        => $this->status,
            'payment_status'                => $this->payment_status,
            'booked_segments'               => $this->booked_segments,
            'baggage'                       => $this->baggage,
            'issued_at'                     => $this->issued_at,
            'provider_name'                 => $this->provider_name,
            'refund_status'                 => $this->refund_status,
            'refund_request'                => $this->refund_request,
            'applied_discount'              => $this->applied_discount,
            'applied_discount_percent'      => $this->applied_discount_percent,
            'applied_discount_percent_type' => $this->applied_discount_percent_type,
            'organization'                  => $this->organization,
            'pia_fees'                      => $this->pia_fees,
            'booking_type'                  => $this->booking_type,
            'departure_date_time'           => $this->departure_date_time,
            'flydubai_bsp_fee'              => $this->flydubai_bsp_fee,
            'is_seat_selected'              => $this->is_seat_selected,
            'extra_selection'               => $this->extra_selection,
            'extra_amount'                  => $this->extra_amount,
            'return_bundle_amount'          => $this?->return_bundle_amount,
            'booked_by_agency'              => booked_by_agency($this->booked_by),
        ];
    }
}
