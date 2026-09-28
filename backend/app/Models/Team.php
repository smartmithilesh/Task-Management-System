<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['organization_id', 'department_id', 'name', 'slug', 'description', 'lead_id'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_users')->withPivot('added_by')->withTimestamps();
    }
}
