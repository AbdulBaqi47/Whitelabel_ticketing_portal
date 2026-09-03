<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagementTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'transaction_amount' => $this->transaction_amount,
            'entry_type' => $this->entry_type,
            'transaction_naration' => $this->transaction_naration,
            'created_at' => $this->created_at,
            'transaction_entries'   => TransactionEntryResource::collection($this->transaction_entries),
            'user' => $this->user?->name,
            'organization' => $this->organization?->name
        ];
    }
}
