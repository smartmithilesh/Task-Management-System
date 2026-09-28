<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
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
            'provider' => fake()->unique()->slug(1),
            'connection_name' => 'primary',
            'status' => 'disconnected',
            'configuration' => null,
            'connected_by' => null,
            'connected_at' => null,
        ];
    }
}
