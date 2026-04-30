<?php

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleTemplateService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->service = new RoleTemplateService();
    $this->company = Company::factory()->create();
});

describe('RoleTemplateService', function () {

    it('creates 5 default roles for a company', function () {
        $roles = $this->service->createDefaultRoles($this->company);

        expect($roles)->toHaveCount(5);
        expect($roles->keys()->all())->toBe(['Owner', 'Admin', 'Recruiter', 'Hiring_Manager', 'Viewer']);
    });

    it('marks all created roles as system defaults', function () {
        $roles = $this->service->createDefaultRoles($this->company);

        foreach ($roles as $role) {
            expect($role->is_system_default)->toBeTrue();
        }
    });

    it('associates all roles with the given company tenant_id', function () {
        $roles = $this->service->createDefaultRoles($this->company);

        foreach ($roles as $role) {
            expect($role->tenant_id)->toBe($this->company->id);
        }
    });

    it('gives Owner role all 26 permissions', function () {
        $roles = $this->service->createDefaultRoles($this->company);
        $ownerRole = $roles->get('Owner');

        expect($ownerRole->permissions)->toHaveCount(26);
    });

    it('gives Admin role all permissions except tenant.delete and owner.assign', function () {
        $roles = $this->service->createDefaultRoles($this->company);
        $adminRole = $roles->get('Admin');
        $adminPermissionNames = $adminRole->permissions->pluck('name')->all();

        expect($adminRole->permissions)->toHaveCount(24);
        expect($adminPermissionNames)->not->toContain('tenant.delete');
        expect($adminPermissionNames)->not->toContain('owner.assign');
    });

    it('gives Recruiter role the correct 13 permissions', function () {
        $roles = $this->service->createDefaultRoles($this->company);
        $recruiterRole = $roles->get('Recruiter');
        $permissionNames = $recruiterRole->permissions->pluck('name')->sort()->values()->all();

        $expected = [
            'applications.manage', 'applications.view',
            'candidates.create', 'candidates.delete', 'candidates.list',
            'candidates.update', 'candidates.view',
            'jobs.create', 'jobs.delete', 'jobs.list', 'jobs.update', 'jobs.view',
            'pipeline.manage',
        ];

        expect($permissionNames)->toBe($expected);
    });

    it('gives Hiring_Manager role the correct 6 permissions', function () {
        $roles = $this->service->createDefaultRoles($this->company);
        $hiringManagerRole = $roles->get('Hiring_Manager');
        $permissionNames = $hiringManagerRole->permissions->pluck('name')->sort()->values()->all();

        $expected = [
            'applications.view',
            'candidates.list', 'candidates.view',
            'jobs.list', 'jobs.view',
            'reports.view',
        ];

        expect($permissionNames)->toBe($expected);
    });

    it('gives Viewer role the correct 10 read-only permissions', function () {
        $roles = $this->service->createDefaultRoles($this->company);
        $viewerRole = $roles->get('Viewer');
        $permissionNames = $viewerRole->permissions->pluck('name')->sort()->values()->all();

        $expected = [
            'audit_logs.view',
            'candidates.list', 'candidates.view',
            'jobs.list', 'jobs.view',
            'reports.view',
            'roles.list', 'roles.view',
            'users.list', 'users.view',
        ];

        expect($permissionNames)->toBe($expected);
    });

    it('ensures Viewer role only has view and list actions', function () {
        $roles = $this->service->createDefaultRoles($this->company);
        $viewerRole = $roles->get('Viewer');

        foreach ($viewerRole->permissions as $permission) {
            expect($permission->action)->toBeIn(['view', 'list']);
        }
    });

    it('creates separate roles for different companies', function () {
        $company2 = Company::factory()->create();

        $roles1 = $this->service->createDefaultRoles($this->company);
        $roles2 = $this->service->createDefaultRoles($company2);

        expect(Role::where('tenant_id', $this->company->id)->count())->toBe(5);
        expect(Role::where('tenant_id', $company2->id)->count())->toBe(5);

        // Roles should be different records
        expect($roles1->get('Owner')->id)->not->toBe($roles2->get('Owner')->id);
    });

    it('returns role names correctly', function () {
        expect($this->service->getRoleNames())->toBe([
            'Owner', 'Admin', 'Recruiter', 'Hiring_Manager', 'Viewer',
        ]);
    });
});
