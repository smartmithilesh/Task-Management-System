<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\IntegrationCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationCredential>
 */
class IntegrationCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'integration_id' => Integration::factory(),
            'key' => fake()->unique()->slug(2),
            'encrypted_value' => fake()->password(32),
            'rotated_at' => null,
        ];
    }
}
