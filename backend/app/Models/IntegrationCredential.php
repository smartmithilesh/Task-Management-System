<?php

namespace App\Models;

use Database\Factories\IntegrationCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['integration_id', 'key', 'encrypted_value', 'rotated_at'])]
#[Hidden(['encrypted_value'])]
class IntegrationCredential extends Model
{
    /** @use HasFactory<IntegrationCredentialFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['encrypted_value' => 'encrypted', 'rotated_at' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
