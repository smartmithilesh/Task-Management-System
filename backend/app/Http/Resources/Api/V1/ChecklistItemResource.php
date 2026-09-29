<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistItemResource extends JsonResource
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
            'content' => $this->content,
            'position' => $this->position,
            'is_completed' => $this->is_completed,
            'completed_at' => $this->completed_at,
            'completed_by' => $this->completedBy?->name,
        ];
    }
}
