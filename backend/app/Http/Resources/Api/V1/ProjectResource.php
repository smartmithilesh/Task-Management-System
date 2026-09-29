<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'client_name' => $this->client_name,
            'department_id' => $this->department?->public_id,
            'manager' => $this->whenLoaded('manager', fn (): ?array => $this->manager === null ? null : [
                'id' => $this->manager->public_id,
                'name' => $this->manager->name,
            ]),
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'priority' => $this->whenLoaded('priority', fn (): ?array => $this->priority === null ? null : [
                'id' => $this->priority->public_id,
                'name' => $this->priority->name,
                'color' => $this->priority->color,
            ]),
            'progress' => $this->progress,
            'budget' => $this->budget,
            'budget_currency' => $this->budget_currency,
            'tasks_count' => $this->whenCounted('tasks'),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
