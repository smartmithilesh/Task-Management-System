<?php

namespace Database\Seeders;

use App\Models\TaskPriority;
use Illuminate\Database\Seeder;

class TaskPrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [
            ['name' => 'Low', 'slug' => 'low', 'color' => '#64748b', 'weight' => 10],
            ['name' => 'Medium', 'slug' => 'medium', 'color' => '#3b82f6', 'weight' => 20],
            ['name' => 'High', 'slug' => 'high', 'color' => '#f97316', 'weight' => 30],
            ['name' => 'Urgent', 'slug' => 'urgent', 'color' => '#ef4444', 'weight' => 40],
        ];

        foreach ($priorities as $priority) {
            TaskPriority::query()->firstOrCreate(
                ['scope_key' => 'default:'.$priority['slug']],
                ['organization_id' => null, ...$priority],
            );
        }
    }
}
