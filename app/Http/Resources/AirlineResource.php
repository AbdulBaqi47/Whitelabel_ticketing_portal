<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AirlineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'uuid'=>$this->uuid,
            'name'=>$this->name,
            'thumbnail'=>$this->thumbnail,
            'iata_code'=>$this->iata_code,
            'status'=> $this->status,
            'issuing_pcc'=> $this->issuing_pcc,
            'reserving_pcc'=>$this->reserving_pcc,
            'tour_code'=>$this->tour_code,
            'country'=>$this->whenLoaded('country'),
            'country_id'=>$this->country_id,
            'preferred_connector'=>$this->preferred_connector,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }
}
