<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskStatus>
 */
class TaskStatusFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'scope_key' => fake()->unique()->bothify('status:????:####'),
            'color' => '#64748b',
            'position' => fake()->numberBetween(1, 500),
            'is_default' => false,
            'is_closed' => false,
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
