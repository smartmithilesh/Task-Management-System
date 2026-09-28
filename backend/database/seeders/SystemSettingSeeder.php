<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'general.application_name' => [config('app.name'), true],
            'general.timezone' => [config('app.timezone'), true],
            'general.language' => [config('app.locale'), true],
            'general.date_format' => [config('app.date_format'), true],
            'general.time_format' => [config('app.time_format'), true],
            'tasks.allow_subtasks' => [true, false],
            'tasks.calculate_parent_progress' => [true, false],
            'notifications.email_enabled' => [true, false],
            'notifications.in_app_enabled' => [true, false],
        ];

        foreach ($settings as $key => [$value, $isPublic]) {
            SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => ['value' => $value], 'is_public' => $isPublic],
            );
        }
    }
}
