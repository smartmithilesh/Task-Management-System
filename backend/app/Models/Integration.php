<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'provider', 'connection_name', 'status', 'configuration', 'connected_by', 'connected_at'])]
#[Hidden(['configuration'])]
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return ['configuration' => 'array', 'connected_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(IntegrationCredential::class);
    }
}
