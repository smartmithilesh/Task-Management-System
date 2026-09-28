<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\TaskStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'slug', 'scope_key', 'color', 'position', 'is_default', 'is_closed'])]
class TaskStatus extends Model
{
    /** @use HasFactory<TaskStatusFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_default' => 'boolean', 'is_closed' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'status_id');
    }
}
