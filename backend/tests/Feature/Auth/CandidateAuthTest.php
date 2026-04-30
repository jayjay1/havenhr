<?php

use App\Events\CandidateLogin;
use App\Events\CandidateRegistered;
use App\Models\Candidate;
use App\Models\CandidateRefreshToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| Candidate Registration Tests
|--------------------------------------------------------------------------
*/

it('registers a new candidate and returns tokens', function () {
    Event::fake([CandidateRegistered::class]);

    $response = $this->postJson('/api/v1/candidate/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'candidate' => ['id', 'name', 'email'],
                'access_token',
                'refresh_token',
            ],
        ]);

    $data = $response->json('data');
    expect($data['candidate']['name'])->toBe('Jane Doe');
    expect($data['candidate']['email'])->toBe('jane@example.com');
    expect($data['access_token'])->not->toBeEmpty();
    expect($data['refresh_token'])->not->toBeEmpty();

    // Verify candidate was created in database
    $candidate = Candidate::where('email', 'jane@example.com')->first();
    expect($candidate)->not->toBeNull();
    expect($candidate->name)->toBe('Jane Doe');
    expect(Hash::check('SecurePass123!', $candidate->password_hash))->toBeTrue();
});

it('returns JWT with correct claims on registration (sub, role=candidate, no tenant_id)', function () {
    Event::fake([CandidateRegistered::class]);

    $response = $this->postJson('/api/v1/candidate/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(201);

    $token = $response->json('data.access_token');
    $payload = JWTAuth::setToken($token)->getPayload();

    $candidate = Candidate::where('email', 'jane@example.com')->first();

    expect($payload->get('sub'))->toBe($candidate->id);
    expect($payload->get('role'))->toBe('candidate');
    expect($payload->get('jti'))->not->toBeNull();

    // Candidate JWT should NOT have tenant_id
    expect($payload->toArray())->not->toHaveKey('tenant_id');
});

it('stores refresh token hash in candidate_refresh_tokens on registration', function () {
    Event::fake([CandidateRegistered::class]);

    $response = $this->postJson('/api/v1/candidate/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(201);

    $rawRefreshToken = $response->json('data.refresh_token');
    $expectedHash = hash('sha256', $rawRefreshToken);

    $candidate = Candidate::where('email', 'jane@example.com')->first();
    $storedToken = CandidateRefreshToken::where('candidate_id', $candidate->id)->first();

    expect($storedToken)->not->toBeNull();
    expect($storedToken->token_hash)->toBe($expectedHash);
    expect($storedToken->is_revoked)->toBeFalse();
    expect(now()->diffInDays($storedToken->expires_at, false))->toBeBetween(6, 7);
});

it('dispatches CandidateRegistered event on registration', function () {
    Event::fake([CandidateRegistered::class]);

    $this->postJson('/api/v1/candidate/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    Event::assertDispatched(CandidateRegistered::class, function (CandidateRegistered $event) {
        expect($event->event_type)->toBe('candidate.registered');
        expect($event->tenant_id)->toBe('platform');
        expect($event->data)->toHaveKeys(['name', 'email']);
        expect($event->data['name'])->toBe('Jane Doe');
        expect($event->data['email'])->toBe('jane@example.com');

        return true;
    });
});

it('rejects registration with duplicate email', function () {
    Candidate::factory()->create(['email' => 'jane@example.com']);

    $response = $this->postJson('/api/v1/candidate/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('VALIDATION_ERROR');
});

it('rejects registration with weak password', function () {
    $response = $this->postJson('/api/v1/candidate/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'short',
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('VALIDATION_ERROR');
});

it('rejects registration with missing required fields', function () {
    $response = $this->postJson('/api/v1/candidate/auth/register', []);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('VALIDATION_ERROR');
    expect($response->json('error.details.fields'))->toHaveKeys(['name', 'email', 'password']);
});

/*
|--------------------------------------------------------------------------
| Candidate Login Tests
|--------------------------------------------------------------------------
*/

it('logs in a candidate and returns tokens in response body', function () {
    Event::fake([CandidateLogin::class]);

    $candidate = Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
    ]);

    $response = $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'candidate' => ['id', 'name', 'email'],
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ],
        ]);

    $data = $response->json('data');
    expect($data['candidate']['id'])->toBe($candidate->id);
    expect($data['candidate']['email'])->toBe('jane@example.com');
    expect($data['access_token'])->not->toBeEmpty();
    expect($data['refresh_token'])->not->toBeEmpty();
    expect($data['token_type'])->toBe('bearer');
    expect($data['expires_in'])->toBe(900);
});

it('returns JWT with correct candidate claims on login', function () {
    Event::fake([CandidateLogin::class]);

    $candidate = Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
    ]);

    $response = $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $token = $response->json('data.access_token');
    $payload = JWTAuth::setToken($token)->getPayload();

    expect($payload->get('sub'))->toBe($candidate->id);
    expect($payload->get('role'))->toBe('candidate');
    expect($payload->toArray())->not->toHaveKey('tenant_id');

    // Verify expiry is approximately 15 minutes (900 seconds)
    $exp = $payload->get('exp');
    $iat = $payload->get('iat');
    expect($exp - $iat)->toBe(900);
});

it('dispatches CandidateLogin event on successful login', function () {
    Event::fake([CandidateLogin::class]);

    Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
    ]);

    $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    Event::assertDispatched(CandidateLogin::class, function (CandidateLogin $event) {
        expect($event->event_type)->toBe('candidate.login');
        expect($event->tenant_id)->toBe('platform');
        expect($event->data)->toHaveKeys(['ip_address', 'user_agent']);

        return true;
    });
});

it('updates last_login_at on successful login', function () {
    Event::fake([CandidateLogin::class]);

    $candidate = Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'last_login_at' => null,
    ]);

    expect($candidate->last_login_at)->toBeNull();

    $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $updatedCandidate = Candidate::find($candidate->id);
    expect($updatedCandidate->last_login_at)->not->toBeNull();
});

it('returns 401 for wrong password on candidate login', function () {
    Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
    ]);

    $response = $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'WrongPassword123!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid credentials.',
            ],
        ]);
});

it('returns 401 for non-existent email on candidate login', function () {
    $response = $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'SomePassword123!',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid credentials.',
            ],
        ]);
});

/*
|--------------------------------------------------------------------------
| Candidate Token Refresh Tests
|--------------------------------------------------------------------------
*/

it('refreshes candidate tokens with token rotation', function () {
    Event::fake([CandidateLogin::class]);

    Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
    ]);

    // Login to get initial tokens
    $loginResponse = $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $refreshToken = $loginResponse->json('data.refresh_token');

    // Refresh tokens
    $refreshResponse = $this->postJson('/api/v1/candidate/auth/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    $refreshResponse->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'candidate' => ['id', 'name', 'email'],
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ],
        ]);

    // New tokens should be different from old ones
    $newRefreshToken = $refreshResponse->json('data.refresh_token');
    expect($newRefreshToken)->not->toBe($refreshToken);

    // Old refresh token should be revoked
    $oldTokenHash = hash('sha256', $refreshToken);
    $oldToken = CandidateRefreshToken::where('token_hash', $oldTokenHash)->first();
    expect($oldToken->is_revoked)->toBeTrue();
});

it('detects replay attack and revokes all tokens', function () {
    Event::fake([CandidateLogin::class]);

    Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
    ]);

    // Login to get initial tokens
    $loginResponse = $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $refreshToken = $loginResponse->json('data.refresh_token');

    // First refresh (legitimate)
    $this->postJson('/api/v1/candidate/auth/refresh', [
        'refresh_token' => $refreshToken,
    ])->assertStatus(200);

    // Second refresh with same token (replay attack)
    $replayResponse = $this->postJson('/api/v1/candidate/auth/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    $replayResponse->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'INVALID_REFRESH_TOKEN',
            ],
        ]);

    // All tokens for this candidate should be revoked
    $candidate = Candidate::where('email', 'jane@example.com')->first();
    $activeTokens = CandidateRefreshToken::where('candidate_id', $candidate->id)
        ->where('is_revoked', false)
        ->count();
    expect($activeTokens)->toBe(0);
});

it('returns 401 when refresh token is missing', function () {
    $response = $this->postJson('/api/v1/candidate/auth/refresh', []);

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'INVALID_REFRESH_TOKEN',
                'message' => 'Refresh token is missing.',
            ],
        ]);
});

/*
|--------------------------------------------------------------------------
| Candidate Logout Tests
|--------------------------------------------------------------------------
*/

it('logs out a candidate and invalidates tokens', function () {
    Event::fake([CandidateLogin::class]);

    Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
    ]);

    // Login to get tokens
    $loginResponse = $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $accessToken = $loginResponse->json('data.access_token');
    $refreshToken = $loginResponse->json('data.refresh_token');

    // Logout
    $logoutResponse = $this->withHeaders([
        'Authorization' => "Bearer {$accessToken}",
    ])->postJson('/api/v1/candidate/auth/logout', [
        'refresh_token' => $refreshToken,
    ]);

    $logoutResponse->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'Successfully logged out.',
            ],
        ]);

    // Verify access token JTI is blocklisted
    $payload = JWTAuth::setToken($accessToken)->getPayload();
    $jti = $payload->get('jti');
    expect(Cache::has("token_blocklist:{$jti}"))->toBeTrue();

    // Verify refresh token is revoked
    $refreshTokenHash = hash('sha256', $refreshToken);
    $storedToken = CandidateRefreshToken::where('token_hash', $refreshTokenHash)->first();
    expect($storedToken->is_revoked)->toBeTrue();
});

it('rejects access to protected routes after logout', function () {
    Event::fake([CandidateLogin::class]);

    Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
    ]);

    // Login
    $loginResponse = $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $accessToken = $loginResponse->json('data.access_token');
    $refreshToken = $loginResponse->json('data.refresh_token');

    // Logout
    $this->withHeaders([
        'Authorization' => "Bearer {$accessToken}",
    ])->postJson('/api/v1/candidate/auth/logout', [
        'refresh_token' => $refreshToken,
    ]);

    // Try to access /me with the same token
    $meResponse = $this->withHeaders([
        'Authorization' => "Bearer {$accessToken}",
    ])->getJson('/api/v1/candidate/auth/me');

    $meResponse->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Candidate /me Endpoint Tests
|--------------------------------------------------------------------------
*/

it('returns candidate profile on /me endpoint', function () {
    Event::fake([CandidateLogin::class]);

    $candidate = Candidate::factory()->create([
        'email' => 'jane@example.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'phone' => '555-1234',
        'location' => 'New York',
    ]);

    // Login to get token
    $loginResponse = $this->postJson('/api/v1/candidate/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
    ]);

    $accessToken = $loginResponse->json('data.access_token');

    // Access /me
    $meResponse = $this->withHeaders([
        'Authorization' => "Bearer {$accessToken}",
    ])->getJson('/api/v1/candidate/auth/me');

    $meResponse->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'email', 'phone', 'location',
                'linkedin_url', 'portfolio_url', 'is_active',
                'created_at', 'updated_at',
            ],
        ]);

    expect($meResponse->json('data.id'))->toBe($candidate->id);
    expect($meResponse->json('data.email'))->toBe('jane@example.com');
    expect($meResponse->json('data.phone'))->toBe('555-1234');
    expect($meResponse->json('data.location'))->toBe('New York');
});

it('returns 401 on /me without authentication', function () {
    $response = $this->getJson('/api/v1/candidate/auth/me');

    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| CandidateAuth Middleware Tests
|--------------------------------------------------------------------------
*/

it('rejects tenant user tokens on candidate routes', function () {
    // Create a JWT with role=Owner (tenant user token)
    $candidate = Candidate::factory()->create();

    $customClaims = [
        'role' => 'Owner',
        'tenant_id' => 'some-tenant-id',
    ];
    $token = JWTAuth::claims($customClaims)->fromUser($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/candidate/auth/me');

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'message' => 'Invalid token for candidate routes.',
            ],
        ]);
});

it('rejects requests without Authorization header on protected routes', function () {
    $response = $this->getJson('/api/v1/candidate/auth/me');

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication required.',
            ],
        ]);
});
