<?php

namespace App\Http\Resources\Api\V1;

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
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'organization_id' => $this->organization?->public_id,
            'department_id' => $this->department?->public_id,
            'phone' => $this->phone,
            'employee_number' => $this->employee_number,
            'status' => $this->status,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}
