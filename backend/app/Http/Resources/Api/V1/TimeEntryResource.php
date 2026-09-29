<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
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
            'task' => $this->task === null ? null : [
                'id' => $this->task->public_id,
                'task_number' => $this->task->task_number,
                'title' => $this->task->title,
            ],
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->public_id,
                'name' => $this->user->name,
            ]),
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'duration_seconds' => $this->duration_seconds,
            'is_running' => $this->ended_at === null,
            'description' => $this->description,
        ];
    }
}
