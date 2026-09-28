<?php

namespace Database\Factories;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_id' => Checklist::factory(),
            'content' => fake()->sentence(),
            'position' => 0,
            'is_completed' => false,
            'completed_by' => null,
            'completed_at' => null,
        ];
    }
}
