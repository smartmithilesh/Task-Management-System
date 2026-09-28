<?php

namespace Database\Seeders;

use App\Models\TaskStatus;
use Illuminate\Database\Seeder;

class TaskStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Backlog', 'slug' => 'backlog', 'color' => '#64748b', 'position' => 10, 'is_default' => true, 'is_closed' => false],
            ['name' => 'To Do', 'slug' => 'to-do', 'color' => '#3b82f6', 'position' => 20, 'is_default' => false, 'is_closed' => false],
            ['name' => 'In Progress', 'slug' => 'in-progress', 'color' => '#8b5cf6', 'position' => 30, 'is_default' => false, 'is_closed' => false],
            ['name' => 'On Hold', 'slug' => 'on-hold', 'color' => '#f59e0b', 'position' => 40, 'is_default' => false, 'is_closed' => false],
            ['name' => 'Review', 'slug' => 'review', 'color' => '#06b6d4', 'position' => 50, 'is_default' => false, 'is_closed' => false],
            ['name' => 'Completed', 'slug' => 'completed', 'color' => '#22c55e', 'position' => 60, 'is_default' => false, 'is_closed' => true],
            ['name' => 'Cancelled', 'slug' => 'cancelled', 'color' => '#ef4444', 'position' => 70, 'is_default' => false, 'is_closed' => true],
        ];

        foreach ($statuses as $status) {
            TaskStatus::query()->firstOrCreate(
                ['scope_key' => 'default:'.$status['slug']],
                ['organization_id' => null, ...$status],
            );
        }
    }
}
