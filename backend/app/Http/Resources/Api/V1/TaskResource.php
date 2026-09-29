<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'task_number' => $this->task_number,
            'project_id' => $this->project?->public_id,
            'parent_task_id' => $this->parent?->public_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->whenLoaded('status', fn (): ?array => $this->status === null ? null : [
                'id' => $this->status->public_id,
                'name' => $this->status->name,
                'slug' => $this->status->slug,
                'color' => $this->status->color,
                'is_closed' => $this->status->is_closed,
            ]),
            'priority' => $this->whenLoaded('priority', fn (): ?array => $this->priority === null ? null : [
                'id' => $this->priority->public_id,
                'name' => $this->priority->name,
                'color' => $this->priority->color,
            ]),
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner === null ? null : [
                'id' => $this->owner->public_id,
                'name' => $this->owner->name,
            ]),
            'assignees' => UserResource::collection($this->whenLoaded('assignees')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'checklists' => ChecklistResource::collection($this->whenLoaded('checklists')),
            'starts_at' => $this->starts_at,
            'due_at' => $this->due_at,
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            'progress' => $this->progress,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
