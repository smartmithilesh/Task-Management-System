<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['task_id', 'title', 'position', 'created_by'])]
class Checklist extends Model
{
    /** @use HasFactory<ChecklistFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position');
    }
}
