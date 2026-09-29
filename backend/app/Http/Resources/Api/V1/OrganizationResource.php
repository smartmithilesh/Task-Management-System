<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'website' => $this->website,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'owner_id' => $this->owner?->public_id,
            'created_at' => $this->created_at,
        ];
    }
}
