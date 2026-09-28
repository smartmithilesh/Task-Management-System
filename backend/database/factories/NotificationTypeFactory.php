<?php

namespace Database\Factories;

use App\Models\NotificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationType>
 */
class NotificationTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->sentence(3),
            'category' => 'tasks',
            'description' => fake()->optional()->sentence(),
            'default_enabled' => true,
        ];
    }
}
