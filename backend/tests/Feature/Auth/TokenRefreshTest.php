<?php

use App\Events\UserLogin;
use App\Events\UserLoginFailed;
use App\Models\Company;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    // Create a tenant with an owner user
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
 * Helper: login and return the raw refresh token value from the cookie.
 */
function loginAndGetRefreshToken($test): string
{
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $response = $test->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(200);

    $cookies = collect($response->headers->getCookies());
    $refreshCookie = $cookies->first(fn ($c) => $c->getName() === 'refresh_token');

    return $refreshCookie->getValue();
}

/**
 * Helper: make a refresh request with the given refresh token cookie.
 */
function callRefresh($test, string $rawRefreshToken)
{
    return $test
        ->withCredentials()
        ->withUnencryptedCookies(['refresh_token' => $rawRefreshToken])
        ->postJson('/api/v1/auth/refresh');
}

it('issues new token pair on valid refresh token', function () {
    $rawRefreshToken = loginAndGetRefreshToken($this);

    $response = callRefresh($this, $rawRefreshToken);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'role'],
                'token_type',
                'expires_in',
            ],
        ]);

    $data = $response->json('data');
    expect($data['user']['id'])->toBe($this->user->id);
    expect($data['user']['name'])->toBe('John Doe');
    expect($data['user']['email'])->toBe('john@testcorp.com');
    expect($data['user']['role'])->toBe('Owner');
    expect($data['token_type'])->toBe('bearer');
    expect($data['expires_in'])->toBe(900);

    // Verify new cookies are set
    $cookies = collect($response->headers->getCookies());
    $accessCookie = $cookies->first(fn ($c) => $c->getName() === 'access_token');
    $refreshCookie = $cookies->first(fn ($c) => $c->getName() === 'refresh_token');

    expect($accessCookie)->not->toBeNull();
    expect($accessCookie->isHttpOnly())->toBeTrue();
    expect($accessCookie->isSecure())->toBeTrue();

    expect($refreshCookie)->not->toBeNull();
    expect($refreshCookie->isHttpOnly())->toBeTrue();
    expect($refreshCookie->isSecure())->toBeTrue();
});

it('revokes old refresh token after successful refresh', function () {
    $rawRefreshToken = loginAndGetRefreshToken($this);
    $oldTokenHash = hash('sha256', $rawRefreshToken);

    callRefresh($this, $rawRefreshToken);

    // Verify old token is revoked
    $oldToken = RefreshToken::withoutGlobalScopes()
        ->where('token_hash', $oldTokenHash)
        ->first();

    expect($oldToken->is_revoked)->toBeTrue();

    // Verify a new token exists and is not revoked
    $newToken = RefreshToken::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->where('is_revoked', false)
        ->first();

    expect($newToken)->not->toBeNull();
    expect($newToken->token_hash)->not->toBe($oldTokenHash);
});

it('returns 401 for expired refresh token', function () {
    $rawRefreshToken = loginAndGetRefreshToken($this);

    // Manually expire the token in the database
    $tokenHash = hash('sha256', $rawRefreshToken);
    RefreshToken::withoutGlobalScopes()
        ->where('token_hash', $tokenHash)
        ->update(['expires_at' => now()->subDay()]);

    $response = callRefresh($this, $rawRefreshToken);

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'TOKEN_EXPIRED',
                'message' => 'Refresh token has expired.',
            ],
        ]);
});

it('triggers full revocation when a revoked/reused refresh token is submitted', function () {
    $rawRefreshToken = loginAndGetRefreshToken($this);

    // First refresh — should succeed and revoke the old token
    $firstRefreshResponse = callRefresh($this, $rawRefreshToken);
    $firstRefreshResponse->assertStatus(200);

    // Create a second valid token for the same user (simulating another session)
    $secondRawToken = bin2hex(random_bytes(64));
    RefreshToken::withoutGlobalScopes()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->company->id,
        'token_hash' => hash('sha256', $secondRawToken),
        'expires_at' => now()->addDays(7),
        'is_revoked' => false,
    ]);

    // Replay the OLD (now revoked) refresh token — should trigger full revocation
    $replayResponse = callRefresh($this, $rawRefreshToken);

    $replayResponse->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'INVALID_REFRESH_TOKEN',
                'message' => 'Invalid refresh token.',
            ],
        ]);

    // Verify ALL refresh tokens for this user are now revoked
    $activeTokens = RefreshToken::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->where('is_revoked', false)
        ->count();

    expect($activeTokens)->toBe(0);
});

it('returns 401 when refresh token cookie is missing', function () {
    $response = $this->postJson('/api/v1/auth/refresh');

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'INVALID_REFRESH_TOKEN',
                'message' => 'Refresh token is missing.',
            ],
        ]);
});

it('returns 401 for a completely invalid/unknown refresh token', function () {
    $response = callRefresh($this, 'totally-invalid-token-value');

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'INVALID_REFRESH_TOKEN',
                'message' => 'Invalid refresh token.',
            ],
        ]);
});

it('new access token has correct JWT claims after refresh', function () {
    $rawRefreshToken = loginAndGetRefreshToken($this);

    $response = callRefresh($this, $rawRefreshToken);
    $response->assertStatus(200);

    // Extract new access token from cookie
    $cookies = collect($response->headers->getCookies());
    $accessCookie = $cookies->first(fn ($c) => $c->getName() === 'access_token');
    $token = $accessCookie->getValue();

    // Decode the JWT and verify claims
    $payload = JWTAuth::setToken($token)->getPayload();

    expect($payload->get('sub'))->toBe($this->user->id);
    expect($payload->get('tenant_id'))->toBe($this->company->id);
    expect($payload->get('role'))->toBe('Owner');
    expect($payload->get('jti'))->not->toBeNull();

    // Verify expiry is approximately 15 minutes (900 seconds)
    $exp = $payload->get('exp');
    $iat = $payload->get('iat');
    expect($exp - $iat)->toBe(900);
});

it('new refresh token has 7-day expiry stored in database', function () {
    $rawRefreshToken = loginAndGetRefreshToken($this);

    $response = callRefresh($this, $rawRefreshToken);
    $response->assertStatus(200);

    // Get the new (non-revoked) refresh token from DB
    $newToken = RefreshToken::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->where('is_revoked', false)
        ->first();

    expect($newToken)->not->toBeNull();
    expect(now()->diffInDays($newToken->expires_at, false))->toBeBetween(6, 7);
});
