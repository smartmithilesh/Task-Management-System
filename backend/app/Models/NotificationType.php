<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\NotificationTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'category', 'description', 'default_enabled'])]
class NotificationType extends Model
{
    /** @use HasFactory<NotificationTypeFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return ['default_enabled' => 'boolean'];
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }
}
