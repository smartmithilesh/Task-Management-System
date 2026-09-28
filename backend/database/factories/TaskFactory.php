<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'project_id' => Project::factory(),
            'task_number' => strtoupper(fake()->unique()->bothify('TSK-########')),
            'title' => fake()->sentence(5),
            'description' => fake()->optional()->paragraph(),
            'parent_task_id' => null,
            'created_by' => User::factory(),
            'owner_id' => null,
            'status_id' => TaskStatus::factory(),
            'priority_id' => TaskPriority::factory(),
            'category_id' => null,
            'starts_at' => null,
            'due_at' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'estimated_hours' => fake()->optional()->randomFloat(2, 0.25, 80),
            'actual_hours' => 0,
            'progress' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Task $task): void {
            $projectOrganizationId = Project::query()->whereKey($task->project_id)->value('organization_id');
            if ($projectOrganizationId !== null) {
                $task->organization_id = $projectOrganizationId;
            }
        });
    }
}
