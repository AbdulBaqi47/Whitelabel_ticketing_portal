<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        
        return [
            // 'id'                => $this->id,  
            'uuid'              => $this->uuid,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone_number'      => $this->phone_number,
            'status'            => $this->status,
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'role'              => $this->getRoleNames()[0],
            'permissions'       =>  $this->getPermissionNames(),
            'created_at'        => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updated_at->format('Y-m-d H:i:s'),
            'profile_image'     => $this->profile_image,
            'otp_pref'          => $this?->otp_pref,
            'branch_id'         => $this?->organization?->id,
            'org_id'            => $this->org_id,
        ];
    }
}
