<?php

use App\Events\UserLogin;
use App\Events\UserLoginFailed;
use App\Events\UserLogout;
use App\Models\Company;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->company = Company::factory()->create([
        'name' => 'Test Corp',
        'email_domain' => 'testcorp.com',
    ]);

    $this->ownerRole = Role::withoutGlobalScopes()->create([
        'name' => 'Owner',
        'description' => 'Full access',
        'is_system_default' => true,
        'tenant_id' => $this->company->id,
    ]);

    $this->user = User::withoutGlobalScopes()->create([
        'name' => 'John Doe',
        'email' => 'john@testcorp.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $this->user->roles()->attach($this->ownerRole->id, [
        'assigned_at' => now(),
    ]);
});

/**
 * Helper: login and return both access and refresh token values.
 */
function loginAndGetTokens($test): array
{
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $response = $test->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(200);

    $cookies = collect($response->headers->getCookies());
    $accessCookie = $cookies->first(fn ($c) => $c->getName() === 'access_token');
    $refreshCookie = $cookies->first(fn ($c) => $c->getName() === 'refresh_token');

    return [
        'access_token' => $accessCookie->getValue(),
        'refresh_token' => $refreshCookie->getValue(),
    ];
}

/**
 * Helper: make a logout request with the given tokens as cookies.
 */
function callLogout($test, string $accessToken, string $refreshToken)
{
    return $test
        ->withCredentials()
        ->withUnencryptedCookies([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ])
        ->postJson('/api/v1/auth/logout');
}

it('returns success response and clears cookies on logout', function () {
    $tokens = loginAndGetTokens($this);
    Event::fake([UserLogout::class]);

    $response = callLogout($this, $tokens['access_token'], $tokens['refresh_token']);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'Successfully logged out.',
            ],
        ]);

    // Verify cookies are cleared (expired)
    $cookies = collect($response->headers->getCookies());
    $accessCookie = $cookies->first(fn ($c) => $c->getName() === 'access_token');
    $refreshCookie = $cookies->first(fn ($c) => $c->getName() === 'refresh_token');

    expect($accessCookie)->not->toBeNull();
    expect($accessCookie->getValue())->toBe('');
    expect($accessCookie->getExpiresTime())->toBeLessThan(now()->timestamp);

    expect($refreshCookie)->not->toBeNull();
    expect($refreshCookie->getValue())->toBe('');
    expect($refreshCookie->getExpiresTime())->toBeLessThan(now()->timestamp);
});

it('adds access token JTI to Redis blocklist on logout', function () {
    $tokens = loginAndGetTokens($this);
    Event::fake([UserLogout::class]);

    // Extract JTI from the access token before logout
    $payload = JWTAuth::setToken($tokens['access_token'])->getPayload();
    $jti = $payload->get('jti');

    $response = callLogout($this, $tokens['access_token'], $tokens['refresh_token']);
    $response->assertStatus(200);

    // Verify JTI is in the cache blocklist
    expect(Cache::has("token_blocklist:{$jti}"))->toBeTrue();
});

it('revokes refresh token in database on logout', function () {
    $tokens = loginAndGetTokens($this);
    Event::fake([UserLogout::class]);

    $refreshTokenHash = hash('sha256', $tokens['refresh_token']);

    // Verify token is not revoked before logout
    $tokenBefore = RefreshToken::withoutGlobalScopes()
        ->where('token_hash', $refreshTokenHash)
        ->first();
    expect($tokenBefore->is_revoked)->toBeFalse();

    $response = callLogout($this, $tokens['access_token'], $tokens['refresh_token']);
    $response->assertStatus(200);

    // Verify token is revoked after logout
    $tokenAfter = RefreshToken::withoutGlobalScopes()
        ->where('token_hash', $refreshTokenHash)
        ->first();
    expect($tokenAfter->is_revoked)->toBeTrue();
});

it('dispatches UserLogout event on successful logout', function () {
    $tokens = loginAndGetTokens($this);
    Event::fake([UserLogout::class]);

    callLogout($this, $tokens['access_token'], $tokens['refresh_token']);

    Event::assertDispatched(UserLogout::class, function (UserLogout $event) {
        expect($event->event_type)->toBe('user.logout');
        expect($event->tenant_id)->toBe($this->company->id);
        expect($event->user_id)->toBe($this->user->id);
        expect($event->data)->toHaveKeys(['ip_address', 'user_agent']);

        return true;
    });
});

it('isTokenBlocklisted returns true for blocklisted JTI', function () {
    $tokens = loginAndGetTokens($this);
    Event::fake([UserLogout::class]);

    $payload = JWTAuth::setToken($tokens['access_token'])->getPayload();
    $jti = $payload->get('jti');

    /** @var AuthService $authService */
    $authService = app(AuthService::class);

    // Before logout, JTI should not be blocklisted
    expect($authService->isTokenBlocklisted($jti))->toBeFalse();

    // Perform logout
    callLogout($this, $tokens['access_token'], $tokens['refresh_token']);

    // After logout, JTI should be blocklisted
    expect($authService->isTokenBlocklisted($jti))->toBeTrue();
});

it('isTokenBlocklisted returns false for non-blocklisted JTI', function () {
    /** @var AuthService $authService */
    $authService = app(AuthService::class);

    expect($authService->isTokenBlocklisted('random-non-existent-jti'))->toBeFalse();
});

it('returns 401 when access token is missing from logout request', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'message' => 'Access token is missing.',
            ],
        ]);
});

it('accepts access token from Authorization header for logout', function () {
    $tokens = loginAndGetTokens($this);
    Event::fake([UserLogout::class]);

    $response = $this
        ->withCredentials()
        ->withHeader('Authorization', 'Bearer ' . $tokens['access_token'])
        ->withUnencryptedCookies([
            'refresh_token' => $tokens['refresh_token'],
        ])
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'Successfully logged out.',
            ],
        ]);

    // Verify JTI is blocklisted
    $payload = JWTAuth::setToken($tokens['access_token'])->getPayload();
    $jti = $payload->get('jti');
    expect(Cache::has("token_blocklist:{$jti}"))->toBeTrue();
});
