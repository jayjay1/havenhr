<?php

use App\Events\TenantCreated;
use App\Events\UserLogin;
use App\Events\UserLoginFailed;
use App\Events\UserRegistered;
use App\Events\RoleChanged;
use App\Listeners\AuditLogListener;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\AuditLoggerService;
use App\Services\TenantContext;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| AuditLoggerService Tests
|--------------------------------------------------------------------------
*/

it('creates an audit log record with all required fields', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $service = app(AuditLoggerService::class);

    $log = $service->log(
        tenantId: $company->id,
        userId: $user->id,
        action: 'user.login',
        resourceType: 'user',
        resourceId: $user->id,
        previousState: null,
        newState: ['last_login_at' => now()->toIso8601String()],
        ipAddress: '192.168.1.1',
        userAgent: 'Mozilla/5.0',
    );

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->id)->not->toBeNull()
        ->and(Str::isUuid($log->id))->toBeTrue()
        ->and($log->tenant_id)->toBe($company->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->action)->toBe('user.login')
        ->and($log->resource_type)->toBe('user')
        ->and($log->resource_id)->toBe($user->id)
        ->and($log->previous_state)->toBeNull()
        ->and($log->new_state)->toBeArray()
        ->and($log->ip_address)->toBe('192.168.1.1')
        ->and($log->user_agent)->toBe('Mozilla/5.0');

    // Refresh from DB to get the database-generated created_at
    $log->refresh();
    expect($log->created_at)->not->toBeNull();
});

it('creates an audit log with nullable fields', function () {
    $company = Company::factory()->create();

    $service = app(AuditLoggerService::class);

    $log = $service->log(
        tenantId: $company->id,
        userId: null,
        action: 'tenant.created',
        resourceType: 'tenant',
        resourceId: $company->id,
        previousState: null,
        newState: ['name' => $company->name],
        ipAddress: null,
        userAgent: null,
    );

    expect($log->user_id)->toBeNull()
        ->and($log->ip_address)->toBeNull()
        ->and($log->user_agent)->toBeNull()
        ->and($log->previous_state)->toBeNull();
});

it('creates audit logs for all supported action types', function () {
    $company = Company::factory()->create();
    $service = app(AuditLoggerService::class);

    foreach (AuditLoggerService::ACTION_TYPES as $actionType) {
        $resourceType = explode('.', $actionType)[0];

        $log = $service->log(
            tenantId: $company->id,
            userId: null,
            action: $actionType,
            resourceType: $resourceType,
        );

        expect($log->action)->toBe($actionType)
            ->and($log->resource_type)->toBe($resourceType);
    }

    $count = AuditLog::withoutGlobalScopes()->where('tenant_id', $company->id)->count();
    expect($count)->toBe(count(AuditLoggerService::ACTION_TYPES));
});

/*
|--------------------------------------------------------------------------
| AuditLogListener Tests
|--------------------------------------------------------------------------
*/

it('processes a domain event and creates an audit log', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $event = new UserLogin(
        tenant_id: $company->id,
        user_id: $user->id,
        data: [
            'resource_id' => $user->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'TestAgent/1.0',
        ],
    );

    $listener = app(AuditLogListener::class);
    $listener->handle($event);

    $log = AuditLog::withoutGlobalScopes()->where('tenant_id', $company->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('user.login')
        ->and($log->resource_type)->toBe('user')
        ->and($log->resource_id)->toBe($user->id)
        ->and($log->ip_address)->toBe('10.0.0.1')
        ->and($log->user_agent)->toBe('TestAgent/1.0');
});

it('processes TenantCreated event correctly', function () {
    $company = Company::factory()->create();

    $event = new TenantCreated(
        tenant_id: $company->id,
        user_id: null,
        data: [
            'resource_id' => $company->id,
            'new_state' => ['name' => $company->name],
        ],
    );

    $listener = app(AuditLogListener::class);
    $listener->handle($event);

    $log = AuditLog::withoutGlobalScopes()->where('tenant_id', $company->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('tenant.created')
        ->and($log->resource_type)->toBe('tenant')
        ->and($log->user_id)->toBeNull();
});

it('processes UserLoginFailed event with ip and user agent', function () {
    $company = Company::factory()->create();

    $event = new UserLoginFailed(
        tenant_id: $company->id,
        user_id: null,
        data: [
            'ip_address' => '192.168.0.100',
            'user_agent' => 'BadBot/1.0',
            'new_state' => ['email' => 'attacker@example.com'],
        ],
    );

    $listener = app(AuditLogListener::class);
    $listener->handle($event);

    $log = AuditLog::withoutGlobalScopes()->where('tenant_id', $company->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('user.login_failed')
        ->and($log->ip_address)->toBe('192.168.0.100')
        ->and($log->user_agent)->toBe('BadBot/1.0');
});

it('processes RoleChanged event with previous and new state', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $event = new RoleChanged(
        tenant_id: $company->id,
        user_id: $user->id,
        data: [
            'resource_id' => $user->id,
            'previous_state' => ['role' => 'viewer'],
            'new_state' => ['role' => 'admin'],
        ],
    );

    $listener = app(AuditLogListener::class);
    $listener->handle($event);

    $log = AuditLog::withoutGlobalScopes()->where('tenant_id', $company->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('role.changed')
        ->and($log->previous_state)->toBe(['role' => 'viewer'])
        ->and($log->new_state)->toBe(['role' => 'admin']);
});

/*
|--------------------------------------------------------------------------
| Audit Log API Endpoint Tests
|--------------------------------------------------------------------------
*/

it('returns paginated audit logs via API', function () {
    $company = Company::factory()->create();
    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($company->id);

    // Create an Owner user with permissions for authenticated requests
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $roleTemplateService = app(\App\Services\RoleTemplateService::class);
    $roles = $roleTemplateService->createDefaultRoles($company);

    $authUser = User::withoutGlobalScopes()->create([
        'name' => 'Auth Owner',
        'email' => 'authowner@audittest.com',
        'password_hash' => \Illuminate\Support\Facades\Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $company->id,
        'is_active' => true,
    ]);
    $authUser->roles()->attach($roles->get('Owner')->id, ['assigned_at' => now()]);

    $token = \Tymon\JWTAuth\Facades\JWTAuth::claims([
        'tenant_id' => $company->id,
        'role' => 'Owner',
    ])->fromUser($authUser);

    $service = app(AuditLoggerService::class);

    // Create multiple audit logs
    for ($i = 0; $i < 5; $i++) {
        $service->log(
            tenantId: $company->id,
            userId: null,
            action: 'user.login',
            resourceType: 'user',
        );
    }

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/audit-logs?per_page=2');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data');
});

it('filters audit logs by action type', function () {
    $company = Company::factory()->create();
    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($company->id);

    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $roleTemplateService = app(\App\Services\RoleTemplateService::class);
    $roles = $roleTemplateService->createDefaultRoles($company);

    $authUser = User::withoutGlobalScopes()->create([
        'name' => 'Auth Owner',
        'email' => 'authowner@audittest.com',
        'password_hash' => \Illuminate\Support\Facades\Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $company->id,
        'is_active' => true,
    ]);
    $authUser->roles()->attach($roles->get('Owner')->id, ['assigned_at' => now()]);

    $token = \Tymon\JWTAuth\Facades\JWTAuth::claims([
        'tenant_id' => $company->id,
        'role' => 'Owner',
    ])->fromUser($authUser);

    $service = app(AuditLoggerService::class);

    $service->log(tenantId: $company->id, userId: null, action: 'user.login', resourceType: 'user');
    $service->log(tenantId: $company->id, userId: null, action: 'user.login', resourceType: 'user');
    $service->log(tenantId: $company->id, userId: null, action: 'user.logout', resourceType: 'user');
    $service->log(tenantId: $company->id, userId: null, action: 'tenant.created', resourceType: 'tenant');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/audit-logs?action=user.login');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 2);
});

it('returns audit logs scoped to the current tenant only', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $service = app(AuditLoggerService::class);

    // Create logs for both tenants
    $service->log(tenantId: $companyA->id, userId: null, action: 'user.login', resourceType: 'user');
    $service->log(tenantId: $companyA->id, userId: null, action: 'user.logout', resourceType: 'user');
    $service->log(tenantId: $companyB->id, userId: null, action: 'user.login', resourceType: 'user');

    // Set tenant context to company A
    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($companyA->id);

    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $roleTemplateService = app(\App\Services\RoleTemplateService::class);
    $roles = $roleTemplateService->createDefaultRoles($companyA);

    $authUser = User::withoutGlobalScopes()->create([
        'name' => 'Auth Owner',
        'email' => 'authowner@audittest.com',
        'password_hash' => \Illuminate\Support\Facades\Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $companyA->id,
        'is_active' => true,
    ]);
    $authUser->roles()->attach($roles->get('Owner')->id, ['assigned_at' => now()]);

    $token = \Tymon\JWTAuth\Facades\JWTAuth::claims([
        'tenant_id' => $companyA->id,
        'role' => 'Owner',
    ])->fromUser($authUser);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/audit-logs');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 2);

    // Verify all returned logs belong to company A
    $data = $response->json('data');
    foreach ($data as $log) {
        expect($log['tenant_id'])->toBe($companyA->id);
    }
});

it('returns audit logs ordered by created_at descending', function () {
    $company = Company::factory()->create();
    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($company->id);

    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $roleTemplateService = app(\App\Services\RoleTemplateService::class);
    $roles = $roleTemplateService->createDefaultRoles($company);

    $authUser = User::withoutGlobalScopes()->create([
        'name' => 'Auth Owner',
        'email' => 'authowner@audittest.com',
        'password_hash' => \Illuminate\Support\Facades\Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $company->id,
        'is_active' => true,
    ]);
    $authUser->roles()->attach($roles->get('Owner')->id, ['assigned_at' => now()]);

    $token = \Tymon\JWTAuth\Facades\JWTAuth::claims([
        'tenant_id' => $company->id,
        'role' => 'Owner',
    ])->fromUser($authUser);

    $service = app(AuditLoggerService::class);

    $service->log(tenantId: $company->id, userId: null, action: 'user.login', resourceType: 'user');
    // Small delay to ensure different timestamps
    usleep(10000);
    $service->log(tenantId: $company->id, userId: null, action: 'user.logout', resourceType: 'user');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/audit-logs');

    $response->assertStatus(200);
    $data = $response->json('data');

    expect(count($data))->toBe(2)
        ->and($data[0]['action'])->toBe('user.logout')
        ->and($data[1]['action'])->toBe('user.login');
});

it('limits per_page to a maximum of 100', function () {
    $company = Company::factory()->create();
    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($company->id);

    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $roleTemplateService = app(\App\Services\RoleTemplateService::class);
    $roles = $roleTemplateService->createDefaultRoles($company);

    $authUser = User::withoutGlobalScopes()->create([
        'name' => 'Auth Owner',
        'email' => 'authowner@audittest.com',
        'password_hash' => \Illuminate\Support\Facades\Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $company->id,
        'is_active' => true,
    ]);
    $authUser->roles()->attach($roles->get('Owner')->id, ['assigned_at' => now()]);

    $token = \Tymon\JWTAuth\Facades\JWTAuth::claims([
        'tenant_id' => $company->id,
        'role' => 'Owner',
    ])->fromUser($authUser);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/audit-logs?per_page=500');

    $response->assertStatus(200)
        ->assertJsonPath('meta.per_page', 100);
});
