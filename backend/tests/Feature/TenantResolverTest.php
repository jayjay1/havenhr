<?php

use App\Http\Middleware\TenantResolver;
use App\Models\Company;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Facades\JWTAuth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Register a test route that uses the tenant.resolve middleware
    Route::middleware(['tenant.resolve'])->get('/api/test/tenant', function () {
        $tenantContext = app(TenantContext::class);

        return response()->json([
            'tenant_id' => $tenantContext->getTenantId(),
            'has_tenant' => $tenantContext->hasTenant(),
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| TenantResolver: Sets TenantContext from valid JWT
|--------------------------------------------------------------------------
*/

it('sets TenantContext when valid JWT with tenant_id is present', function () {
    $company = Company::factory()->create();

    $user = User::withoutGlobalScopes()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password_hash' => bcrypt('Password123!'),
        'tenant_id' => $company->id,
    ]);

    $token = JWTAuth::fromUser($user);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->getJson('/api/test/tenant');

    $response->assertStatus(200);
    $response->assertJson([
        'tenant_id' => $company->id,
        'has_tenant' => true,
    ]);
});

/*
|--------------------------------------------------------------------------
| TenantResolver: Returns 401 when no JWT is present
|--------------------------------------------------------------------------
*/

it('returns 401 when no JWT is present', function () {
    $response = $this->getJson('/api/test/tenant');

    $response->assertStatus(401);
    $response->assertJson([
        'error' => [
            'code' => 'UNAUTHENTICATED',
            'message' => 'Authentication required.',
        ],
    ]);
});

/*
|--------------------------------------------------------------------------
| TenantResolver: Returns 401 when JWT has no tenant_id claim
|--------------------------------------------------------------------------
*/

it('returns 401 when JWT has no tenant_id claim', function () {
    $company = Company::factory()->create();

    $user = User::withoutGlobalScopes()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password_hash' => bcrypt('Password123!'),
        'tenant_id' => $company->id,
    ]);

    // Generate a token with custom claims that explicitly exclude tenant_id
    $token = JWTAuth::claims(['tenant_id' => null])->fromUser($user);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->getJson('/api/test/tenant');

    $response->assertStatus(401);
    $response->assertJson([
        'error' => [
            'code' => 'UNAUTHENTICATED',
            'message' => 'No tenant context found in token.',
        ],
    ]);
});

/*
|--------------------------------------------------------------------------
| TenantResolver: Returns 401 with invalid/expired JWT
|--------------------------------------------------------------------------
*/

it('returns 401 when JWT is invalid', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer invalid-token-string',
    ])->getJson('/api/test/tenant');

    $response->assertStatus(401);
    $response->assertJson([
        'error' => [
            'code' => 'UNAUTHENTICATED',
            'message' => 'Authentication required.',
        ],
    ]);
});

/*
|--------------------------------------------------------------------------
| TenantResolver: Tenant context is available to downstream handlers
|--------------------------------------------------------------------------
*/

it('makes tenant context available to downstream request handlers', function () {
    $company = Company::factory()->create();

    $user = User::withoutGlobalScopes()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password_hash' => bcrypt('Password123!'),
        'tenant_id' => $company->id,
    ]);

    $token = JWTAuth::fromUser($user);

    // The test route returns the TenantContext state
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->getJson('/api/test/tenant');

    $response->assertStatus(200);

    // Verify the TenantContext singleton has the correct tenant_id
    $tenantContext = app(TenantContext::class);
    expect($tenantContext->hasTenant())->toBeTrue();
    expect($tenantContext->getTenantId())->toBe($company->id);
});

/*
|--------------------------------------------------------------------------
| User model: getJWTCustomClaims includes tenant_id
|--------------------------------------------------------------------------
*/

it('User model includes tenant_id in JWT custom claims', function () {
    $company = Company::factory()->create();

    $user = User::withoutGlobalScopes()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password_hash' => bcrypt('Password123!'),
        'tenant_id' => $company->id,
    ]);

    $claims = $user->getJWTCustomClaims();

    expect($claims)->toHaveKey('tenant_id');
    expect($claims['tenant_id'])->toBe($company->id);
});

it('JWT token generated from User contains tenant_id claim', function () {
    $company = Company::factory()->create();

    $user = User::withoutGlobalScopes()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password_hash' => bcrypt('Password123!'),
        'tenant_id' => $company->id,
    ]);

    $token = JWTAuth::fromUser($user);
    $payload = JWTAuth::setToken($token)->getPayload();

    expect($payload->get('tenant_id'))->toBe($company->id);
    expect($payload->get('sub'))->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| Middleware registration
|--------------------------------------------------------------------------
*/

it('tenant.resolve middleware alias is registered and functional', function () {
    // The fact that our test routes using 'tenant.resolve' middleware work
    // proves the alias is registered. Let's verify by making a request
    // to the test route without auth — it should return 401 from TenantResolver.
    $response = $this->getJson('/api/test/tenant');

    $response->assertStatus(401);
    $response->assertJsonStructure([
        'error' => ['code', 'message'],
    ]);
});
