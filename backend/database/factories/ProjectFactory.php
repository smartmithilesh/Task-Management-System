<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Project;
use App\Models\TaskPriority;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'code' => strtoupper(fake()->unique()->bothify('PRJ-####')),
            'name' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'client_name' => fake()->optional()->company(),
            'department_id' => null,
            'manager_id' => null,
            'start_date' => fake()->optional()->date(),
            'due_date' => fake()->optional()->date(),
            'status' => 'planning',
            'priority_id' => TaskPriority::factory(),
            'progress' => 0,
            'budget' => fake()->optional()->randomFloat(2, 100, 100000),
            'budget_currency' => 'USD',
            'created_by' => User::factory(),
        ];
    }
}
