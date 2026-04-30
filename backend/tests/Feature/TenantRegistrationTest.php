<?php

use App\Events\TenantCreated;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Acme Corp',
        'company_email_domain' => 'acme.com',
        'owner_name' => 'Jane Doe',
        'owner_email' => 'jane@acme.com',
        'owner_password' => 'SecurePass123!',
    ], $overrides);
}

it('creates company, user, roles, and role assignment on successful registration', function () {
    Event::fake([TenantCreated::class]);

    $response = $this->postJson('/api/v1/register', validRegistrationPayload());

    $response->assertStatus(201);

    // Verify company was created
    $company = Company::where('email_domain', 'acme.com')->first();
    expect($company)->not->toBeNull();
    expect($company->name)->toBe('Acme Corp');
    expect($company->subscription_status)->toBe('trial');

    // Verify user was created and linked to company
    $user = User::withoutGlobalScopes()->where('email', 'jane@acme.com')->first();
    expect($user)->not->toBeNull();
    expect($user->tenant_id)->toBe($company->id);
    expect($user->name)->toBe('Jane Doe');

    // Verify 5 default roles were created for the tenant
    $roles = Role::withoutGlobalScopes()->where('tenant_id', $company->id)->get();
    expect($roles)->toHaveCount(5);
    expect($roles->pluck('name')->sort()->values()->all())->toBe([
        'Admin', 'Hiring_Manager', 'Owner', 'Recruiter', 'Viewer',
    ]);

    // Verify Owner role is assigned to the user
    $ownerRole = $roles->firstWhere('name', 'Owner');
    expect($user->roles()->where('role_id', $ownerRole->id)->exists())->toBeTrue();
});

it('returns correct response format with 201 status', function () {
    Event::fake([TenantCreated::class]);

    $response = $this->postJson('/api/v1/register', validRegistrationPayload());

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'tenant' => ['id', 'name', 'email_domain'],
                'user' => ['id', 'name', 'email', 'role'],
            ],
        ]);

    $data = $response->json('data');
    expect($data['tenant']['name'])->toBe('Acme Corp');
    expect($data['tenant']['email_domain'])->toBe('acme.com');
    expect($data['user']['name'])->toBe('Jane Doe');
    expect($data['user']['email'])->toBe('jane@acme.com');
    expect($data['user']['role'])->toBe('owner');
});

it('returns 409 when domain already exists', function () {
    Event::fake([TenantCreated::class]);

    // Register first tenant
    $this->postJson('/api/v1/register', validRegistrationPayload());

    // Attempt duplicate registration
    $response = $this->postJson('/api/v1/register', validRegistrationPayload([
        'company_name' => 'Another Corp',
        'owner_name' => 'John Smith',
        'owner_email' => 'john@acme.com',
        'owner_password' => 'AnotherPass123!',
    ]));

    $response->assertStatus(409)
        ->assertJson([
            'error' => [
                'code' => 'DOMAIN_ALREADY_EXISTS',
                'message' => 'The company email domain is already registered.',
            ],
        ]);

    // Verify no additional company was created
    expect(Company::where('email_domain', 'acme.com')->count())->toBe(1);
});

it('returns 422 when validation fails with missing fields', function () {
    $response = $this->postJson('/api/v1/register', []);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'error' => [
                'code',
                'message',
                'details' => [
                    'fields',
                ],
            ],
        ]);

    $fields = $response->json('error.details.fields');
    expect($fields)->toHaveKeys([
        'company_name',
        'company_email_domain',
        'owner_name',
        'owner_email',
        'owner_password',
    ]);
});

it('returns 422 for invalid email format', function () {
    $response = $this->postJson('/api/v1/register', validRegistrationPayload([
        'owner_email' => 'not-an-email',
    ]));

    $response->assertStatus(422);
    $fields = $response->json('error.details.fields');
    expect($fields)->toHaveKey('owner_email');
});

it('returns 422 for weak password', function () {
    $response = $this->postJson('/api/v1/register', validRegistrationPayload([
        'owner_password' => 'short',
    ]));

    $response->assertStatus(422);
    $fields = $response->json('error.details.fields');
    expect($fields)->toHaveKey('owner_password');
});

it('dispatches TenantCreated event on successful registration', function () {
    Event::fake([TenantCreated::class]);

    $this->postJson('/api/v1/register', validRegistrationPayload());

    Event::assertDispatched(TenantCreated::class, function (TenantCreated $event) {
        expect($event->event_type)->toBe('tenant.created');
        expect($event->tenant_id)->not->toBeEmpty();
        expect($event->user_id)->not->toBeEmpty();
        expect($event->data)->toHaveKeys([
            'company_name',
            'company_email_domain',
            'owner_name',
            'owner_email',
        ]);

        return true;
    });
});

it('hashes the password with bcrypt', function () {
    Event::fake([TenantCreated::class]);

    $this->postJson('/api/v1/register', validRegistrationPayload([
        'owner_password' => 'MySecurePass123!',
    ]));

    $user = User::withoutGlobalScopes()->where('email', 'jane@acme.com')->first();
    expect($user)->not->toBeNull();

    // Verify the stored hash is valid bcrypt and the password verifies
    expect(Hash::check('MySecurePass123!', $user->password_hash))->toBeTrue();

    // Verify it's a bcrypt hash (starts with $2y$ or $2b$)
    expect($user->password_hash)->toMatch('/^\$2[yb]\$/');
});

it('creates 5 default roles for the tenant', function () {
    Event::fake([TenantCreated::class]);

    $response = $this->postJson('/api/v1/register', validRegistrationPayload());
    $response->assertStatus(201);

    $tenantId = $response->json('data.tenant.id');
    $roles = Role::withoutGlobalScopes()->where('tenant_id', $tenantId)->get();

    expect($roles)->toHaveCount(5);
    expect($roles->where('is_system_default', true))->toHaveCount(5);
});

it('assigns Owner role to the registered user', function () {
    Event::fake([TenantCreated::class]);

    $response = $this->postJson('/api/v1/register', validRegistrationPayload());
    $response->assertStatus(201);

    $userId = $response->json('data.user.id');
    $tenantId = $response->json('data.tenant.id');

    $user = User::withoutGlobalScopes()->find($userId);
    $ownerRole = Role::withoutGlobalScopes()
        ->where('tenant_id', $tenantId)
        ->where('name', 'Owner')
        ->first();

    expect($user->roles->pluck('id')->contains($ownerRole->id))->toBeTrue();
});

it('rejects unknown fields with 422', function () {
    $response = $this->postJson('/api/v1/register', validRegistrationPayload([
        'unknown_field' => 'some value',
    ]));

    $response->assertStatus(422);
    $fields = $response->json('error.details.fields');
    expect($fields)->toHaveKey('unknown_field');
});
