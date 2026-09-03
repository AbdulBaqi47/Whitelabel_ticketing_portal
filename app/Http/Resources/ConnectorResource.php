<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectorResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type,
            'api_key' => $this->api_key,
            'api_secret' => $this->api_secret,
            'connector_domain' => $this->connector_domain,
            'pcc' => $this->pcc,
            'printer' => $this->printer,
            'iata' => $this->iata,
            'client_ip' => $this->client_ip,
            'is_enable' => $this->is_enable,
            'supplier' => $this->whenLoaded('supplier'),
            'supplier_id'=>$this->supplier_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }
}
