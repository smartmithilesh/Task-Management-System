<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\TaskPriorityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'slug', 'scope_key', 'color', 'weight'])]
class TaskPriority extends Model
{
    /** @use HasFactory<TaskPriorityFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return ['weight' => 'integer'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'priority_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'priority_id');
    }
}
