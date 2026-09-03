<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'airline' => $this->whenLoaded('airline'),
            'baggage'=>$this->baggage,
            'arrival_airport' => $this->arrival_airport,
            'arrival_date_time' => $this->arrival_date_time,
            'base_fare'=>$this->base_fare,
            'booked_segment'=>$this->whenLoaded('booked_segment'),
            'iban'=>$this->iban,
            'bank_logo' => $this->bank_logo,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }
}
