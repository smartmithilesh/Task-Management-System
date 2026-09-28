<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().'.'.fake()->word(),
            'label' => fake()->sentence(3),
            'group_name' => fake()->word(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
