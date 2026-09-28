<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /** @var list<array{name: string, label: string, group_name: string}> */
    public const DEFAULT_PERMISSIONS = [
        ['name' => 'users.view', 'label' => 'View users', 'group_name' => 'users'],
        ['name' => 'users.create', 'label' => 'Create users', 'group_name' => 'users'],
        ['name' => 'users.edit', 'label' => 'Edit users', 'group_name' => 'users'],
        ['name' => 'users.delete', 'label' => 'Delete users', 'group_name' => 'users'],
        ['name' => 'roles.view', 'label' => 'View roles', 'group_name' => 'roles'],
        ['name' => 'roles.create', 'label' => 'Create roles', 'group_name' => 'roles'],
        ['name' => 'roles.edit', 'label' => 'Edit roles', 'group_name' => 'roles'],
        ['name' => 'roles.delete', 'label' => 'Delete roles', 'group_name' => 'roles'],
        ['name' => 'departments.view', 'label' => 'View departments', 'group_name' => 'departments'],
        ['name' => 'departments.manage', 'label' => 'Manage departments', 'group_name' => 'departments'],
        ['name' => 'teams.view', 'label' => 'View teams', 'group_name' => 'teams'],
        ['name' => 'teams.manage', 'label' => 'Manage teams', 'group_name' => 'teams'],
        ['name' => 'projects.view', 'label' => 'View projects', 'group_name' => 'projects'],
        ['name' => 'projects.create', 'label' => 'Create projects', 'group_name' => 'projects'],
        ['name' => 'projects.edit', 'label' => 'Edit projects', 'group_name' => 'projects'],
        ['name' => 'projects.delete', 'label' => 'Delete projects', 'group_name' => 'projects'],
        ['name' => 'tasks.view', 'label' => 'View tasks', 'group_name' => 'tasks'],
        ['name' => 'tasks.create', 'label' => 'Create tasks', 'group_name' => 'tasks'],
        ['name' => 'tasks.edit', 'label' => 'Edit tasks', 'group_name' => 'tasks'],
        ['name' => 'tasks.delete', 'label' => 'Delete tasks', 'group_name' => 'tasks'],
        ['name' => 'tasks.assign', 'label' => 'Assign tasks', 'group_name' => 'tasks'],
        ['name' => 'tasks.change_status', 'label' => 'Change task status', 'group_name' => 'tasks'],
        ['name' => 'reports.view', 'label' => 'View reports', 'group_name' => 'reports'],
        ['name' => 'settings.manage', 'label' => 'Manage settings', 'group_name' => 'settings'],
        ['name' => 'integrations.manage', 'label' => 'Manage integrations', 'group_name' => 'integrations'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULT_PERMISSIONS as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission['name']],
                [...$permission, 'description' => null],
            );
        }
    }
}
