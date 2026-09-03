<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewAndAlertResource extends JsonResource
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
            'title'=>$this->title,
            'description'=>$this->description,
            'image'=>$this->image,
            'news_url' => $this->news_url,
            'is_feature'=>$this->is_feature
        ];
    }
}
