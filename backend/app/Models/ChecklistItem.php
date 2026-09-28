<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['checklist_id', 'content', 'position', 'is_completed', 'completed_by', 'completed_at'])]
class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_completed' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
