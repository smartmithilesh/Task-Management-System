<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = array_column(PermissionSeeder::DEFAULT_PERMISSIONS, 'name');
        $roles = [
            'super-admin' => [
                'name' => 'Super Admin',
                'description' => 'Full system access.',
                'permissions' => $allPermissions,
            ],
            'admin' => [
                'name' => 'Admin',
                'description' => 'Organization administration access.',
                'permissions' => $allPermissions,
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Project and task coordination access.',
                'permissions' => ['departments.view', 'teams.view', 'projects.view', 'projects.create', 'projects.edit', 'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.assign', 'tasks.change_status', 'reports.view'],
            ],
            'team-member' => [
                'name' => 'Team Member',
                'description' => 'Task collaboration access.',
                'permissions' => ['projects.view', 'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.change_status'],
            ],
        ];

        foreach ($roles as $slug => $roleData) {
            $role = Role::query()->firstOrCreate(
                ['scope_key' => 'global:'.$slug],
                [
                    'organization_id' => null,
                    'name' => $roleData['name'],
                    'slug' => $slug,
                    'description' => $roleData['description'],
                    'is_system' => true,
                ],
            );

            $permissionIds = Permission::query()
                ->whereIn('name', $roleData['permissions'])
                ->pluck('id')
                ->all();

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
