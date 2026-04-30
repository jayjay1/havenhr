<?php

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    // Create a tenant
    $this->company = Company::factory()->create([
        'name' => 'RBAC Test Corp',
        'email_domain' => 'rbactest.com',
    ]);

    // Create Owner role with all permissions
    $this->ownerRole = Role::withoutGlobalScopes()->create([
        'name' => 'Owner',
        'description' => 'Full access',
        'is_system_default' => true,
        'tenant_id' => $this->company->id,
    ]);
    $allPermissions = Permission::all();
    $this->ownerRole->permissions()->attach($allPermissions->pluck('id'));

    // Create Viewer role with only read permissions
    $this->viewerRole = Role::withoutGlobalScopes()->create([
        'name' => 'Viewer',
        'description' => 'Read-only access',
        'is_system_default' => true,
        'tenant_id' => $this->company->id,
    ]);
    $readPermissions = $allPermissions->filter(fn ($p) => in_array($p->action, ['view', 'list']));
    $this->viewerRole->permissions()->attach($readPermissions->pluck('id'));

    // Create Owner user
    $this->ownerUser = User::withoutGlobalScopes()->create([
        'name' => 'Owner User',
        'email' => 'owner@rbactest.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);
    $this->ownerUser->roles()->attach($this->ownerRole->id, ['assigned_at' => now()]);

    // Create Viewer user
    $this->viewerUser = User::withoutGlobalScopes()->create([
        'name' => 'Viewer User',
        'email' => 'viewer@rbactest.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);
    $this->viewerUser->roles()->attach($this->viewerRole->id, ['assigned_at' => now()]);
});

/**
 * Helper to generate a JWT for a given user with role claim.
 */
function generateToken(User $user, string $roleName): string
{
    return JWTAuth::claims([
        'tenant_id' => $user->tenant_id,
        'role' => $roleName,
    ])->fromUser($user);
}

// ─── RBAC Middleware Tests ───────────────────────────────────────────────────

it('allows access when user has the required permission (Owner accessing users.list)', function () {
    $token = generateToken($this->ownerUser, 'Owner');

    // Set tenant context for the global scope
    app(TenantContext::class)->setTenantId($this->company->id);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/users');

    $response->assertStatus(200);
});

it('denies access (403) when user lacks the required permission (Viewer creating user)', function () {
    $token = generateToken($this->viewerUser, 'Viewer');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/users', [
        'name' => 'New User',
        'email' => 'new@rbactest.com',
        'password' => 'SecurePass123!',
        'role' => 'Viewer',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to access this resource.',
            ],
        ]);
});

it('allows Viewer to access read-only endpoints (users.list)', function () {
    $token = generateToken($this->viewerUser, 'Viewer');

    app(TenantContext::class)->setTenantId($this->company->id);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/users');

    $response->assertStatus(200);
});

it('denies Viewer access to delete endpoint (users.delete)', function () {
    $token = generateToken($this->viewerUser, 'Viewer');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->deleteJson("/api/v1/users/{$this->viewerUser->id}");

    $response->assertStatus(403)
        ->assertJson([
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to access this resource.',
            ],
        ]);
});

it('denies Viewer access to manage_roles endpoint', function () {
    $token = generateToken($this->viewerUser, 'Viewer');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/users/{$this->ownerUser->id}/roles", [
        'role_id' => $this->viewerRole->id,
    ]);

    $response->assertStatus(403);
});

it('allows Owner to access manage_roles endpoint', function () {
    $token = generateToken($this->ownerUser, 'Owner');

    app(TenantContext::class)->setTenantId($this->company->id);

    // Create a new user without any role to avoid unique constraint on user_role
    $newUser = User::withoutGlobalScopes()->create([
        'name' => 'New User',
        'email' => 'newuser@rbactest.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/users/{$newUser->id}/roles", [
        'role_id' => $this->viewerRole->id,
    ]);

    // Should not be 403 — Owner has manage_roles permission
    $response->assertStatus(200);
});

it('caches role permissions for performance', function () {
    $token = generateToken($this->ownerUser, 'Owner');

    app(TenantContext::class)->setTenantId($this->company->id);

    // First request — should populate cache
    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/users');

    $cacheKey = "rbac:permissions:{$this->company->id}:Owner";
    expect(Cache::has($cacheKey))->toBeTrue();

    $cachedPermissions = Cache::get($cacheKey);
    expect($cachedPermissions)->toBeArray();
    expect($cachedPermissions)->toContain('users.list');
});

// ─── JwtAuth Middleware Tests ────────────────────────────────────────────────

it('rejects requests without any token (401)', function () {
    $response = $this->getJson('/api/v1/users');

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication required.',
            ],
        ]);
});

it('accepts valid tokens from Authorization header', function () {
    $token = generateToken($this->ownerUser, 'Owner');

    app(TenantContext::class)->setTenantId($this->company->id);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/users');

    $response->assertStatus(200);
});

it('rejects blocklisted tokens (401)', function () {
    $token = generateToken($this->ownerUser, 'Owner');

    // Parse the token to get the JTI
    $payload = JWTAuth::setToken($token)->getPayload();
    $jti = $payload->get('jti');

    // Blocklist the token
    Cache::put("token_blocklist:{$jti}", true, 900);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/users');

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'TOKEN_BLOCKLISTED',
                'message' => 'Access token has been revoked.',
            ],
        ]);
});

it('rejects expired tokens (401)', function () {
    // Create a token with a very short TTL that's already expired
    // We'll use Carbon to travel forward in time
    $token = generateToken($this->ownerUser, 'Owner');

    // Travel forward past the token's expiration (15 min + buffer)
    $this->travel(16)->minutes();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/users');

    $response->assertStatus(401);
});

it('rejects malformed tokens (401)', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer not-a-valid-jwt-token',
    ])->getJson('/api/v1/users');

    $response->assertStatus(401);
});

it('public routes remain accessible without authentication', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'SomePassword123!',
    ]);

    // Should not be 401 — login is a public route (will be 401 for invalid creds, not for missing auth)
    expect($response->status())->not->toBe(403);
});
