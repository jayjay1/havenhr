<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionAuditSeeder extends Seeder
{
    public function run(): void
    {
        // Show all roles and their permissions
        $roles = Role::withoutGlobalScopes()->with('permissions')->get();

        foreach ($roles as $role) {
            $perms = $role->permissions->pluck('name')->sort()->values()->toArray();
            echo "{$role->name}: " . (empty($perms) ? '(none)' : implode(', ', $perms)) . "\n";
        }

        echo "\n--- All permissions in DB ---\n";
        $allPerms = Permission::withoutGlobalScopes()->pluck('name')->sort()->values()->toArray();
        echo implode(', ', $allPerms) . "\n";
    }
}
