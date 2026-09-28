<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notification_type_id' => NotificationType::factory(),
            'channel' => 'in_app',
            'enabled' => true,
        ];
    }
}
