<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TaskStatusSeeder::class,
            TaskPrioritySeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            NotificationTypeSeeder::class,
            SystemSettingSeeder::class,
        ]);
    }
}
