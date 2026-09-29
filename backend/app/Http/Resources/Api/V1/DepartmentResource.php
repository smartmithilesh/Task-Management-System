<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
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
            'description' => $this->description,
            'manager' => $this->whenLoaded('manager', fn (): ?array => $this->manager === null ? null : [
                'id' => $this->manager->public_id,
                'name' => $this->manager->name,
            ]),
            'users_count' => $this->whenCounted('users'),
            'teams_count' => $this->whenCounted('teams'),
        ];
    }
}
