<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{

    /**
     * All system permissions: [name, resource, action, description].
     *
     * @var array<int, array{string, string, string, string}>
     */
    protected array $permissions = [
        ['users.create', 'users', 'create', 'Create new users within the tenant'],
        ['users.view', 'users', 'view', 'View individual user details'],
        ['users.list', 'users', 'list', 'List all users within the tenant'],
        ['users.update', 'users', 'update', 'Update user information'],
        ['users.delete', 'users', 'delete', 'Delete users from the tenant'],
        ['roles.list', 'roles', 'list', 'List all roles within the tenant'],
        ['roles.view', 'roles', 'view', 'View individual role details'],
        ['manage_roles', 'roles', 'manage', 'Manage role assignments for users'],
        ['audit_logs.view', 'audit_logs', 'view', 'View audit log entries'],
        ['tenant.update', 'tenant', 'update', 'Update tenant settings'],
        ['tenant.delete', 'tenant', 'delete', 'Delete the tenant'],
        ['jobs.create', 'jobs', 'create', 'Create new job postings'],
        ['jobs.view', 'jobs', 'view', 'View individual job posting details'],
        ['jobs.list', 'jobs', 'list', 'List all job postings'],
        ['jobs.update', 'jobs', 'update', 'Update job postings'],
        ['jobs.delete', 'jobs', 'delete', 'Delete job postings'],
        ['candidates.create', 'candidates', 'create', 'Create new candidate records'],
        ['candidates.view', 'candidates', 'view', 'View individual candidate details'],
        ['candidates.list', 'candidates', 'list', 'List all candidates'],
        ['candidates.update', 'candidates', 'update', 'Update candidate records'],
        ['candidates.delete', 'candidates', 'delete', 'Delete candidate records'],
        ['pipeline.manage', 'pipeline', 'manage', 'Manage pipeline stages and transitions'],
        ['reports.view', 'reports', 'view', 'View reports and analytics'],
        ['owner.assign', 'owner', 'assign', 'Assign or transfer the Owner role'],
        ['applications.view', 'applications', 'view', 'View job applications and talent pool'],
        ['applications.manage', 'applications', 'manage', 'Manage job applications'],
    ];

    /**
     * Seed the system permissions.
     */
    public function run(): void
    {
        foreach ($this->permissions as [$name, $resource, $action, $description]) {
            Permission::firstOrCreate(
                ['name' => $name],
                [
                    'resource' => $resource,
                    'action' => $action,
                    'description' => $description,
                ]
            );
        }
    }
}
