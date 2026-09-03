<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'account_number' => $this->account_number,
            'account_holder_name'=>$this->account_holder_name,
            'bank_name' => $this->bank_name,
            'status' => $this->status,
            'bank_address'=>$this->bank_address,
            'contact_number'=>$this->contact_number,
            'iban'=>$this->iban,
            'bank_logo' => $this->bank_logo,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }
}
