<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\TaskPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskPriority>
 */
class TaskPriorityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(1),
            'scope_key' => fake()->unique()->bothify('priority:????:####'),
            'color' => '#64748b',
            'weight' => fake()->numberBetween(1, 100),
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $organization->id,
            'scope_key' => 'organization:'.$organization->public_id.':'.$attributes['slug'],
        ]);
    }
}
