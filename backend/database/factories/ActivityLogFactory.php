<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'actor_id' => User::factory(),
            'subject_type' => Task::class,
            'subject_id' => fake()->numberBetween(1, 1000),
            'action' => 'task.updated',
            'properties' => ['field' => 'status'],
            'occurred_at' => now(),
        ];
    }
}
