<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'started_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'ended_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'duration_seconds' => fake()->numberBetween(60, 28800),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
