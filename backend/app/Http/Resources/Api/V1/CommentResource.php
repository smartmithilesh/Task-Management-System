<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
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
            'body' => $this->body,
            'author' => $this->author === null ? null : [
                'id' => $this->author->public_id,
                'name' => $this->author->name,
            ],
            'mentions' => $this->whenLoaded('mentionedUsers', fn () => $this->mentionedUsers->map(fn ($user): array => [
                'id' => $user->public_id,
                'name' => $user->name,
            ])->all()),
            'edited_at' => $this->edited_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
