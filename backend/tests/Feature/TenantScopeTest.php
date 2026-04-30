<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\RefreshToken;
use App\Models\AuditLog;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantContext = app(TenantContext::class);
});

/*
|--------------------------------------------------------------------------
| TenantScope: WHERE clause behavior
|--------------------------------------------------------------------------
*/

it('adds WHERE tenant_id clause when tenant context is set', function () {
    $company = Company::create([
        'name' => 'Test Company',
        'email_domain' => 'test.com',
        'subscription_status' => 'trial',
    ]);

    $this->tenantContext->setTenantId($company->id);

    // Build a query and check the SQL contains the tenant_id WHERE clause
    $query = User::query()->toSql();

    expect($query)->toContain('tenant_id');
});

it('does not add WHERE tenant_id clause when no tenant context is set', function () {
    $query = User::query()->toSql();

    expect($query)->not->toContain('tenant_id');
});

/*
|--------------------------------------------------------------------------
| TenantScope: Query filtering
|--------------------------------------------------------------------------
*/

it('filters User queries by tenant_id when context is set', function () {
    $companyA = Company::create([
        'name' => 'Company A',
        'email_domain' => 'a.com',
        'subscription_status' => 'trial',
    ]);

    $companyB = Company::create([
        'name' => 'Company B',
        'email_domain' => 'b.com',
        'subscription_status' => 'trial',
    ]);

    // Create users without tenant scope (directly set tenant_id)
    User::withoutGlobalScopes()->create([
        'name' => 'User A',
        'email' => 'user@a.com',
        'password_hash' => bcrypt('password'),
        'tenant_id' => $companyA->id,
    ]);

    User::withoutGlobalScopes()->create([
        'name' => 'User B',
        'email' => 'user@b.com',
        'password_hash' => bcrypt('password'),
        'tenant_id' => $companyB->id,
    ]);

    // Set tenant context to Company A
    $this->tenantContext->setTenantId($companyA->id);

    $users = User::all();

    expect($users)->toHaveCount(1);
    expect($users->first()->name)->toBe('User A');
    expect($users->first()->tenant_id)->toBe($companyA->id);
});

it('filters Role queries by tenant_id when context is set', function () {
    $companyA = Company::create([
        'name' => 'Company A',
        'email_domain' => 'a.com',
        'subscription_status' => 'trial',
    ]);

    $companyB = Company::create([
        'name' => 'Company B',
        'email_domain' => 'b.com',
        'subscription_status' => 'trial',
    ]);

    Role::withoutGlobalScopes()->create([
        'name' => 'Admin',
        'description' => 'Admin role',
        'is_system_default' => true,
        'tenant_id' => $companyA->id,
    ]);

    Role::withoutGlobalScopes()->create([
        'name' => 'Admin',
        'description' => 'Admin role',
        'is_system_default' => true,
        'tenant_id' => $companyB->id,
    ]);

    $this->tenantContext->setTenantId($companyA->id);

    $roles = Role::all();

    expect($roles)->toHaveCount(1);
    expect($roles->first()->tenant_id)->toBe($companyA->id);
});

it('returns all records when no tenant context is set', function () {
    $companyA = Company::create([
        'name' => 'Company A',
        'email_domain' => 'a.com',
        'subscription_status' => 'trial',
    ]);

    $companyB = Company::create([
        'name' => 'Company B',
        'email_domain' => 'b.com',
        'subscription_status' => 'trial',
    ]);

    User::withoutGlobalScopes()->create([
        'name' => 'User A',
        'email' => 'user@a.com',
        'password_hash' => bcrypt('password'),
        'tenant_id' => $companyA->id,
    ]);

    User::withoutGlobalScopes()->create([
        'name' => 'User B',
        'email' => 'user@b.com',
        'password_hash' => bcrypt('password'),
        'tenant_id' => $companyB->id,
    ]);

    // No tenant context set — should return all users
    $users = User::all();

    expect($users)->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| BelongsToTenant: Auto-set tenant_id on creating
|--------------------------------------------------------------------------
*/

it('auto-sets tenant_id on User when creating with tenant context', function () {
    $company = Company::create([
        'name' => 'Test Company',
        'email_domain' => 'test.com',
        'subscription_status' => 'trial',
    ]);

    $this->tenantContext->setTenantId($company->id);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@test.com',
        'password_hash' => bcrypt('password'),
    ]);

    expect($user->tenant_id)->toBe($company->id);
});

it('auto-sets tenant_id on Role when creating with tenant context', function () {
    $company = Company::create([
        'name' => 'Test Company',
        'email_domain' => 'test.com',
        'subscription_status' => 'trial',
    ]);

    $this->tenantContext->setTenantId($company->id);

    $role = Role::create([
        'name' => 'Custom Role',
        'description' => 'A custom role',
        'is_system_default' => false,
    ]);

    expect($role->tenant_id)->toBe($company->id);
});

it('does not override tenant_id if already set on model', function () {
    $companyA = Company::create([
        'name' => 'Company A',
        'email_domain' => 'a.com',
        'subscription_status' => 'trial',
    ]);

    $companyB = Company::create([
        'name' => 'Company B',
        'email_domain' => 'b.com',
        'subscription_status' => 'trial',
    ]);

    // Set context to Company A
    $this->tenantContext->setTenantId($companyA->id);

    // But explicitly set tenant_id to Company B
    $user = User::withoutGlobalScopes()->create([
        'name' => 'Test User',
        'email' => 'test@b.com',
        'password_hash' => bcrypt('password'),
        'tenant_id' => $companyB->id,
    ]);

    expect($user->tenant_id)->toBe($companyB->id);
});

it('does not set tenant_id when no tenant context is active', function () {
    $user = new User([
        'name' => 'Test User',
        'email' => 'test@test.com',
        'password_hash' => bcrypt('password'),
    ]);

    // tenant_id should be null since no context is set and none was provided
    expect($user->tenant_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| TenantContext service
|--------------------------------------------------------------------------
*/

it('TenantContext is registered as a singleton', function () {
    $instance1 = app(TenantContext::class);
    $instance2 = app(TenantContext::class);

    expect($instance1)->toBe($instance2);
});

it('TenantContext starts with no tenant', function () {
    $context = app(TenantContext::class);

    expect($context->hasTenant())->toBeFalse();
    expect($context->getTenantId())->toBeNull();
});

it('TenantContext can set and get tenant_id', function () {
    $context = app(TenantContext::class);
    $context->setTenantId('test-uuid-123');

    expect($context->hasTenant())->toBeTrue();
    expect($context->getTenantId())->toBe('test-uuid-123');
});

/*
|--------------------------------------------------------------------------
| Models have BelongsToTenant trait applied
|--------------------------------------------------------------------------
*/

it('User model has TenantScope applied', function () {
    $scopes = User::query()->removedScopes();
    // Check that the model has the global scope by verifying the query includes tenant filtering
    $company = Company::create([
        'name' => 'Test',
        'email_domain' => 'scope-test.com',
        'subscription_status' => 'trial',
    ]);

    $this->tenantContext->setTenantId($company->id);

    $sql = User::query()->toSql();
    expect($sql)->toContain('tenant_id');
});

it('Role model has TenantScope applied', function () {
    $company = Company::create([
        'name' => 'Test',
        'email_domain' => 'scope-test.com',
        'subscription_status' => 'trial',
    ]);

    $this->tenantContext->setTenantId($company->id);

    $sql = Role::query()->toSql();
    expect($sql)->toContain('tenant_id');
});

it('RefreshToken model has TenantScope applied', function () {
    $company = Company::create([
        'name' => 'Test',
        'email_domain' => 'scope-test.com',
        'subscription_status' => 'trial',
    ]);

    $this->tenantContext->setTenantId($company->id);

    $sql = RefreshToken::query()->toSql();
    expect($sql)->toContain('tenant_id');
});

it('AuditLog model has TenantScope applied', function () {
    $company = Company::create([
        'name' => 'Test',
        'email_domain' => 'scope-test.com',
        'subscription_status' => 'trial',
    ]);

    $this->tenantContext->setTenantId($company->id);

    $sql = AuditLog::query()->toSql();
    expect($sql)->toContain('tenant_id');
});

it('Company model does NOT have TenantScope applied', function () {
    $sql = Company::query()->toSql();
    expect($sql)->not->toContain('tenant_id');
});

it('Permission model does NOT have TenantScope applied', function () {
    $sql = \App\Models\Permission::query()->toSql();
    expect($sql)->not->toContain('tenant_id');
});
