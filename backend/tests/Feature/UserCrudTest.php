<?php

use App\Events\UserRegistered;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleTemplateService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    // Create a tenant with default roles
    $this->company = Company::factory()->create();
    $roleTemplateService = app(RoleTemplateService::class);
    $this->roles = $roleTemplateService->createDefaultRoles($this->company);

    // Set tenant context so global scopes work
    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($this->company->id);

    // Create an Owner user for authenticated requests
    $this->authUser = User::withoutGlobalScopes()->create([
        'name' => 'Auth Owner',
        'email' => 'authowner@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);
    $ownerRole = $this->roles->get('Owner');
    $this->authUser->roles()->attach($ownerRole->id, ['assigned_at' => now()]);

    // Generate JWT token for authenticated requests
    $this->authToken = JWTAuth::claims([
        'tenant_id' => $this->company->id,
        'role' => 'Owner',
    ])->fromUser($this->authUser);
});

function validUserPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'John Smith',
        'email' => 'john@example.com',
        'password' => 'SecurePass123!',
        'role' => 'Admin',
    ], $overrides);
}

// --- User Creation ---

it('creates a user with role assignment', function () {
    Event::fake([UserRegistered::class]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/users', validUserPayload());

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at'],
        ]);

    $data = $response->json('data');
    expect($data['name'])->toBe('John Smith');
    expect($data['email'])->toBe('john@example.com');
    expect($data['role'])->toBe('Admin');
    expect($data['is_active'])->toBeTrue();

    // Verify user exists in DB with correct tenant
    $user = User::withoutGlobalScopes()->where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->tenant_id)->toBe($this->company->id);

    // Verify role assignment
    $adminRole = $this->roles->get('Admin');
    expect($user->roles()->where('role_id', $adminRole->id)->exists())->toBeTrue();
});

it('hashes password with bcrypt cost 12 on user creation', function () {
    Event::fake([UserRegistered::class]);

    $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/users', validUserPayload([
        'password' => 'MyStrongPass99!',
    ]));

    $user = User::withoutGlobalScopes()->where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('MyStrongPass99!', $user->password_hash))->toBeTrue();
    expect($user->password_hash)->toMatch('/^\$2[yb]\$/');
});

it('dispatches UserRegistered event on user creation', function () {
    Event::fake([UserRegistered::class]);

    $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/users', validUserPayload());

    Event::assertDispatched(UserRegistered::class, function (UserRegistered $event) {
        expect($event->event_type)->toBe('user.registered');
        expect($event->tenant_id)->toBe($this->company->id);
        expect($event->data['name'])->toBe('John Smith');
        expect($event->data['email'])->toBe('john@example.com');
        expect($event->data['role'])->toBe('Admin');

        return true;
    });
});

// --- Email Uniqueness ---

it('rejects duplicate email within the same tenant', function () {
    Event::fake([UserRegistered::class]);

    // Create first user
    $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/users', validUserPayload())->assertStatus(201);

    // Attempt to create second user with same email
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/users', validUserPayload([
        'name' => 'Jane Smith',
    ]));

    $response->assertStatus(409)
        ->assertJson([
            'error' => [
                'code' => 'EMAIL_ALREADY_EXISTS',
                'message' => 'A user with this email already exists in this workspace.',
            ],
        ]);
});

it('allows same email across different tenants', function () {
    Event::fake([UserRegistered::class]);

    // Create user in first tenant
    $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/users', validUserPayload())->assertStatus(201);

    // Create second tenant with roles
    $company2 = Company::factory()->create();
    $roleTemplateService = app(RoleTemplateService::class);
    $roleTemplateService->createDefaultRoles($company2);

    // Create an auth user in the second tenant
    $authUser2 = User::withoutGlobalScopes()->create([
        'name' => 'Auth Owner 2',
        'email' => 'authowner2@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $company2->id,
        'is_active' => true,
    ]);
    $ownerRole2 = Role::withoutGlobalScopes()->where('tenant_id', $company2->id)->where('name', 'Owner')->first();
    $authUser2->roles()->attach($ownerRole2->id, ['assigned_at' => now()]);

    $token2 = JWTAuth::claims([
        'tenant_id' => $company2->id,
        'role' => 'Owner',
    ])->fromUser($authUser2);

    // Switch tenant context
    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($company2->id);

    // Create user with same email in second tenant
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token2}",
    ])->postJson('/api/v1/users', validUserPayload());
    $response->assertStatus(201);

    // Verify both users exist
    $users = User::withoutGlobalScopes()->where('email', 'john@example.com')->get();
    expect($users)->toHaveCount(2);
    expect($users->pluck('tenant_id')->unique()->count())->toBe(2);
});

// --- Paginated User List ---

it('returns paginated user list', function () {
    // Create multiple users in the tenant
    for ($i = 1; $i <= 5; $i++) {
        User::withoutGlobalScopes()->create([
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'password_hash' => Hash::make('Password123!'),
            'tenant_id' => $this->company->id,
        ]);
    }

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson('/api/v1/users?page=1&per_page=3');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    $meta = $response->json('meta');
    expect($meta['current_page'])->toBe(1);
    expect($meta['per_page'])->toBe(3);
    // 5 created users + 1 auth user = 6 total
    expect($meta['total'])->toBe(6);
    expect($meta['last_page'])->toBe(2);
    expect($response->json('data'))->toHaveCount(3);
});

it('defaults to 20 per page and caps at 100', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson('/api/v1/users');
    $meta = $response->json('meta');
    expect($meta['per_page'])->toBe(20);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson('/api/v1/users?per_page=200');
    $meta = $response->json('meta');
    expect($meta['per_page'])->toBe(100);
});

// --- Single User Retrieval ---

it('retrieves a single user by ID', function () {
    $user = User::withoutGlobalScopes()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password_hash' => Hash::make('Password123!'),
        'tenant_id' => $this->company->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/users/{$user->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at'],
        ]);

    expect($response->json('data.id'))->toBe($user->id);
    expect($response->json('data.name'))->toBe('Test User');
    expect($response->json('data.email'))->toBe('test@example.com');
});

it('returns 404 for non-existent user', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson('/api/v1/users/non-existent-id');

    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'USER_NOT_FOUND',
            ],
        ]);
});

// --- User Update ---

it('updates a user', function () {
    $user = User::withoutGlobalScopes()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'password_hash' => Hash::make('Password123!'),
        'tenant_id' => $this->company->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->putJson("/api/v1/users/{$user->id}", [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('Updated Name');
    expect($response->json('data.email'))->toBe('updated@example.com');

    // Verify in DB
    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->email)->toBe('updated@example.com');
});

it('returns 404 when updating non-existent user', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->putJson('/api/v1/users/non-existent-id', [
        'name' => 'Updated Name',
    ]);

    $response->assertStatus(404);
});

// --- User Deletion ---

it('deletes a user', function () {
    $user = User::withoutGlobalScopes()->create([
        'name' => 'To Delete',
        'email' => 'delete@example.com',
        'password_hash' => Hash::make('Password123!'),
        'tenant_id' => $this->company->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->deleteJson("/api/v1/users/{$user->id}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'User deleted successfully.',
            ],
        ]);

    // Verify user is deleted
    expect(User::withoutGlobalScopes()->find($user->id))->toBeNull();
});

it('returns 404 when deleting non-existent user', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->deleteJson('/api/v1/users/non-existent-id');

    $response->assertStatus(404);
});

// --- Tenant Scoping ---

it('only returns users from the current tenant', function () {
    // Create user in current tenant
    User::withoutGlobalScopes()->create([
        'name' => 'Tenant A User',
        'email' => 'tenanta@example.com',
        'password_hash' => Hash::make('Password123!'),
        'tenant_id' => $this->company->id,
    ]);

    // Create user in different tenant
    $otherCompany = Company::factory()->create();
    User::withoutGlobalScopes()->create([
        'name' => 'Tenant B User',
        'email' => 'tenantb@example.com',
        'password_hash' => Hash::make('Password123!'),
        'tenant_id' => $otherCompany->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson('/api/v1/users');

    $response->assertStatus(200);
    $data = $response->json('data');
    // 2 users: the auth owner user + the Tenant A User created above
    expect($data)->toHaveCount(2);
    $names = collect($data)->pluck('name')->toArray();
    expect($names)->toContain('Tenant A User');
    // Verify no Tenant B users are returned
    expect($names)->not->toContain('Tenant B User');
});
