<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'time_entries.view' => 'View time entries',
        'time_entries.manage' => 'Manage time entries',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name => $label) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['public_id' => (string) Str::uuid(), 'label' => $label, 'group_name' => 'time', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        $roleIds = DB::table('roles')->whereIn('scope_key', ['global:super-admin', 'global:admin'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
