<?php

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->company = Company::factory()->create([
        'name' => 'Security Test Corp',
        'email_domain' => 'securitytest.com',
    ]);

    $this->ownerRole = Role::withoutGlobalScopes()->create([
        'name' => 'Owner',
        'description' => 'Full access',
        'is_system_default' => true,
        'tenant_id' => $this->company->id,
    ]);
    $allPermissions = Permission::all();
    $this->ownerRole->permissions()->attach($allPermissions->pluck('id'));

    $this->user = User::withoutGlobalScopes()->create([
        'name' => 'Test User',
        'email' => 'test@securitytest.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);
    $this->user->roles()->attach($this->ownerRole->id, ['assigned_at' => now()]);
});

function makeAuthToken(User $user): string
{
    return JWTAuth::claims([
        'tenant_id' => $user->tenant_id,
        'role' => 'Owner',
    ])->fromUser($user);
}

// ─── Rate Limiting Tests ─────────────────────────────────────────────────────

it('returns 429 after exceeding auth rate limit (5 req/min)', function () {
    RateLimiter::clear('auth');

    // Make 5 requests (should all succeed or return non-429)
    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@securitytest.com',
            'password' => 'WrongPassword1!',
        ]);
        expect($response->status())->not->toBe(429);
    }

    // 6th request should be rate limited
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@securitytest.com',
        'password' => 'WrongPassword1!',
    ]);

    $response->assertStatus(429);
});

it('includes Retry-After header when rate limit exceeded', function () {
    RateLimiter::clear('auth');

    // Exhaust the auth rate limit
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@securitytest.com',
            'password' => 'WrongPassword1!',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@securitytest.com',
        'password' => 'WrongPassword1!',
    ]);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});

it('returns 429 after exceeding general API rate limit (60 req/min)', function () {
    RateLimiter::clear('api');

    $token = makeAuthToken($this->user);
    app(TenantContext::class)->setTenantId($this->company->id);

    // Make 60 requests
    for ($i = 0; $i < 60; $i++) {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/users');
        expect($response->status())->not->toBe(429);
    }

    // 61st request should be rate limited
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/users');

    $response->assertStatus(429);
});

it('rate limits registration endpoint', function () {
    RateLimiter::clear('auth');

    // Exhaust the auth rate limit with registration attempts
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/register', [
            'company_name' => "Test Corp {$i}",
            'company_email_domain' => "testcorp{$i}.com",
            'owner_name' => 'Owner',
            'owner_email' => "owner{$i}@testcorp{$i}.com",
            'owner_password' => 'SecurePass123!',
        ]);
    }

    $response = $this->postJson('/api/v1/register', [
        'company_name' => 'Test Corp Extra',
        'company_email_domain' => 'testcorpextra.com',
        'owner_name' => 'Owner',
        'owner_email' => 'owner@testcorpextra.com',
        'owner_password' => 'SecurePass123!',
    ]);

    $response->assertStatus(429);
});

// ─── Security Headers Tests ──────────────────────────────────────────────────

it('includes X-Content-Type-Options: nosniff on all responses', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'SomePassword123!',
    ]);

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('includes X-Frame-Options: DENY on all responses', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'SomePassword123!',
    ]);

    $response->assertHeader('X-Frame-Options', 'DENY');
});

it('includes Strict-Transport-Security header on all responses', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'SomePassword123!',
    ]);

    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('includes Content-Security-Policy header on all responses', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'SomePassword123!',
    ]);

    $response->assertHeader('Content-Security-Policy', "default-src 'self'");
});

it('includes all security headers on authenticated endpoints', function () {
    $token = makeAuthToken($this->user);
    app(TenantContext::class)->setTenantId($this->company->id);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/users');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $response->assertHeader('Content-Security-Policy', "default-src 'self'");
});

it('includes security headers on error responses', function () {
    // Unauthenticated request to protected endpoint
    $response = $this->getJson('/api/v1/users');

    $response->assertStatus(401);
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $response->assertHeader('Content-Security-Policy', "default-src 'self'");
});

// ─── CORS Tests ──────────────────────────────────────────────────────────────

it('includes CORS headers for allowlisted origins', function () {
    config(['cors.allowed_origins' => ['http://localhost:3000']]);

    $response = $this->withHeaders([
        'Origin' => 'http://localhost:3000',
    ])->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'SomePassword123!',
    ]);

    $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
});

it('does not include CORS allow-origin matching non-allowlisted origins', function () {
    // With multiple allowed origins, CorsService uses dynamic matching
    // and only sets Access-Control-Allow-Origin when the request origin matches
    config(['cors.allowed_origins' => ['http://localhost:3000', 'http://app.havenhr.com']]);

    $response = $this->withHeaders([
        'Origin' => 'http://evil-site.com',
    ])->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'SomePassword123!',
    ]);

    // The response should NOT have Access-Control-Allow-Origin set to the evil origin
    $allowOrigin = $response->headers->get('Access-Control-Allow-Origin');
    expect($allowOrigin)->not->toBe('http://evil-site.com');
});

it('handles CORS preflight OPTIONS requests for allowlisted origins', function () {
    config(['cors.allowed_origins' => ['http://localhost:3000']]);

    $response = $this->withHeaders([
        'Origin' => 'http://localhost:3000',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Content-Type, Authorization',
    ])->options('/api/v1/auth/login');

    $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
});

// ─── ForceHttps Tests ────────────────────────────────────────────────────────

it('does not redirect to HTTPS in testing environment', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'SomePassword123!',
    ]);

    // Should not be a redirect — testing env skips HTTPS enforcement
    expect($response->status())->not->toBe(301);
});
