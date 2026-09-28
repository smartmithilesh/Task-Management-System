<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSetting>
 */
class OrganizationSettingFactory extends Factory
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
            'key' => fake()->unique()->slug(2),
            'value' => ['enabled' => fake()->boolean()],
            'is_public' => false,
        ];
    }
}
