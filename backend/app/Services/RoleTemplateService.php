<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

class RoleTemplateService
{
    /**
     * Role templates with their permission mappings.
     *
     * Each key is the role name, and the value is either:
     * - '*' for all permissions
     * - An array of permission names to include
     *
     * @var array<string, string|array{include?: list<string>, exclude?: list<string>}>
     */
    protected array $roleTemplates = [
        'Owner' => [
            'description' => 'Full access to all tenant resources and settings',
            'permissions' => '*',
        ],
        'Admin' => [
            'description' => 'Administrative access excluding tenant deletion and owner assignment',
            'permissions' => [
                'exclude' => ['tenant.delete', 'owner.assign'],
            ],
        ],
        'Recruiter' => [
            'description' => 'Manages job postings, candidates, and pipeline stages',
            'permissions' => [
                'include' => [
                    'jobs.create', 'jobs.view', 'jobs.list', 'jobs.update', 'jobs.delete',
                    'candidates.create', 'candidates.view', 'candidates.list', 'candidates.update', 'candidates.delete',
                    'pipeline.manage',
                    'applications.view', 'applications.manage',
                ],
            ],
        ],
        'Hiring_Manager' => [
            'description' => 'Reviews job postings, candidates, and reports',
            'permissions' => [
                'include' => [
                    'jobs.view', 'jobs.list',
                    'candidates.view', 'candidates.list',
                    'reports.view',
                    'applications.view',
                ],
            ],
        ],
        'Viewer' => [
            'description' => 'Read-only access to tenant resources',
            'permissions' => [
                'include' => [
                    'users.view', 'users.list',
                    'roles.view', 'roles.list',
                    'audit_logs.view',
                    'jobs.view', 'jobs.list',
                    'candidates.view', 'candidates.list',
                    'reports.view',
                ],
            ],
        ],
    ];

    /**
     * Create the default roles for a given company (tenant) and attach permissions.
     *
     * @param  Company  $company
     * @return Collection<string, Role> Keyed by role name
     */
    public function createDefaultRoles(Company $company): Collection
    {
        $allPermissions = Permission::all();
        $roles = collect();

        foreach ($this->roleTemplates as $roleName => $template) {
            $role = Role::create([
                'name' => $roleName,
                'description' => $template['description'],
                'is_system_default' => true,
                'tenant_id' => $company->id,
            ]);

            $permissionIds = $this->resolvePermissionIds($template['permissions'], $allPermissions);
            $role->permissions()->attach($permissionIds);

            $roles->put($roleName, $role);
        }

        return $roles;
    }

    /**
     * Resolve permission IDs based on the template definition.
     *
     * @param  string|array  $definition  '*' for all, or array with 'include'/'exclude' keys
     * @param  Collection<int, Permission>  $allPermissions
     * @return array<int, string>
     */
    protected function resolvePermissionIds(string|array $definition, Collection $allPermissions): array
    {
        if ($definition === '*') {
            return $allPermissions->pluck('id')->all();
        }

        if (isset($definition['exclude'])) {
            return $allPermissions
                ->whereNotIn('name', $definition['exclude'])
                ->pluck('id')
                ->all();
        }

        if (isset($definition['include'])) {
            return $allPermissions
                ->whereIn('name', $definition['include'])
                ->pluck('id')
                ->all();
        }

        return [];
    }

    /**
     * Get the role template definitions.
     *
     * @return array<string, array>
     */
    public function getTemplates(): array
    {
        return $this->roleTemplates;
    }

    /**
     * Get the list of default role names.
     *
     * @return list<string>
     */
    public function getRoleNames(): array
    {
        return array_keys($this->roleTemplates);
    }
}
