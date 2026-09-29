<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
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
            'actor' => $this->actor?->name,
            'action' => $this->action,
            'properties' => $this->properties,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
