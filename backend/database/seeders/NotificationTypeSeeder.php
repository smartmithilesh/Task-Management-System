<?php

namespace Database\Seeders;

use App\Models\NotificationType;
use Illuminate\Database\Seeder;

class NotificationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'task.assigned', 'name' => 'Task assigned', 'category' => 'tasks'],
            ['slug' => 'task.reassigned', 'name' => 'Task reassigned', 'category' => 'tasks'],
            ['slug' => 'task.commented', 'name' => 'Task comment', 'category' => 'tasks'],
            ['slug' => 'task.mentioned', 'name' => 'Mention', 'category' => 'collaboration'],
            ['slug' => 'task.status_changed', 'name' => 'Task status changed', 'category' => 'tasks'],
            ['slug' => 'task.priority_changed', 'name' => 'Task priority changed', 'category' => 'tasks'],
            ['slug' => 'task.due_soon', 'name' => 'Task due soon', 'category' => 'tasks'],
            ['slug' => 'task.overdue', 'name' => 'Overdue task', 'category' => 'tasks'],
            ['slug' => 'project.updated', 'name' => 'Project update', 'category' => 'projects'],
        ];

        foreach ($types as $type) {
            NotificationType::query()->firstOrCreate(
                ['slug' => $type['slug']],
                [...$type, 'default_enabled' => true, 'description' => null],
            );
        }
    }
}
