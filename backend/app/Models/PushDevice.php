<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'device_id', 'platform', 'token_hash', 'encrypted_token', 'last_seen_at'])]
#[Hidden(['token_hash', 'encrypted_token'])]
class PushDevice extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return ['encrypted_token' => 'encrypted', 'last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
