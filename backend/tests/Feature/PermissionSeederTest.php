<?php

use App\Models\Permission;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PermissionSeeder', function () {

    it('seeds all 26 system permissions', function () {
        $this->seed(PermissionSeeder::class);

        expect(Permission::count())->toBe(26);
    });

    it('seeds permissions with correct name, resource, and action', function () {
        $this->seed(PermissionSeeder::class);

        $expectedPermissions = [
            ['name' => 'users.create', 'resource' => 'users', 'action' => 'create'],
            ['name' => 'users.view', 'resource' => 'users', 'action' => 'view'],
            ['name' => 'users.list', 'resource' => 'users', 'action' => 'list'],
            ['name' => 'users.update', 'resource' => 'users', 'action' => 'update'],
            ['name' => 'users.delete', 'resource' => 'users', 'action' => 'delete'],
            ['name' => 'roles.list', 'resource' => 'roles', 'action' => 'list'],
            ['name' => 'roles.view', 'resource' => 'roles', 'action' => 'view'],
            ['name' => 'manage_roles', 'resource' => 'roles', 'action' => 'manage'],
            ['name' => 'audit_logs.view', 'resource' => 'audit_logs', 'action' => 'view'],
            ['name' => 'tenant.update', 'resource' => 'tenant', 'action' => 'update'],
            ['name' => 'tenant.delete', 'resource' => 'tenant', 'action' => 'delete'],
            ['name' => 'jobs.create', 'resource' => 'jobs', 'action' => 'create'],
            ['name' => 'jobs.view', 'resource' => 'jobs', 'action' => 'view'],
            ['name' => 'jobs.list', 'resource' => 'jobs', 'action' => 'list'],
            ['name' => 'jobs.update', 'resource' => 'jobs', 'action' => 'update'],
            ['name' => 'jobs.delete', 'resource' => 'jobs', 'action' => 'delete'],
            ['name' => 'candidates.create', 'resource' => 'candidates', 'action' => 'create'],
            ['name' => 'candidates.view', 'resource' => 'candidates', 'action' => 'view'],
            ['name' => 'candidates.list', 'resource' => 'candidates', 'action' => 'list'],
            ['name' => 'candidates.update', 'resource' => 'candidates', 'action' => 'update'],
            ['name' => 'candidates.delete', 'resource' => 'candidates', 'action' => 'delete'],
            ['name' => 'pipeline.manage', 'resource' => 'pipeline', 'action' => 'manage'],
            ['name' => 'reports.view', 'resource' => 'reports', 'action' => 'view'],
            ['name' => 'owner.assign', 'resource' => 'owner', 'action' => 'assign'],
            ['name' => 'applications.view', 'resource' => 'applications', 'action' => 'view'],
            ['name' => 'applications.manage', 'resource' => 'applications', 'action' => 'manage'],
        ];

        foreach ($expectedPermissions as $expected) {
            $permission = Permission::where('name', $expected['name'])->first();
            expect($permission)->not->toBeNull("Permission '{$expected['name']}' should exist");
            expect($permission->resource)->toBe($expected['resource']);
            expect($permission->action)->toBe($expected['action']);
        }
    });

    it('is idempotent when run multiple times', function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        expect(Permission::count())->toBe(26);
    });

    it('seeds permissions with descriptions', function () {
        $this->seed(PermissionSeeder::class);

        $permissions = Permission::all();
        foreach ($permissions as $permission) {
            expect($permission->description)->not->toBeNull()
                ->and($permission->description)->not->toBeEmpty();
        }
    });
});
