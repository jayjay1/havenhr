<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'james.jayson@gmail.com')->first();
        if (!$owner) {
            echo "Owner not found!\n";
            return;
        }

        $tenantId = $owner->tenant_id;

        $testUsers = [
            ['name' => 'Admin User', 'email' => 'admin@test.com', 'role' => 'admin'],
            ['name' => 'Recruiter User', 'email' => 'recruiter@test.com', 'role' => 'recruiter'],
            ['name' => 'Hiring Manager', 'email' => 'hiring@test.com', 'role' => 'hiring_manager'],
            ['name' => 'Viewer User', 'email' => 'viewer@test.com', 'role' => 'viewer'],
        ];

        foreach ($testUsers as $data) {
            $existing = User::withoutGlobalScopes()->where('email', $data['email'])->first();
            if ($existing) {
                echo "{$data['email']} already exists\n";
                continue;
            }

            $user = User::withoutGlobalScopes()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => Hash::make('Password123!', ['rounds' => 12]),
                'tenant_id' => $tenantId,
                'is_active' => true,
            ]);

            $role = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('name', $data['role'])
                ->first();

            if ($role) {
                $user->roles()->attach($role->id, [
                    'assigned_by' => $owner->id,
                    'assigned_at' => now(),
                ]);
            }

            echo "Created {$data['email']} as {$data['role']}\n";
        }

        echo "Done!\n";
    }
}
