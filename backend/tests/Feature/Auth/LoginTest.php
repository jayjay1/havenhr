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

it('returns user profile and sets cookies on successful login', function () {
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
        'password' => 'SecurePass123!',
    ]);

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

    // Verify cookies are set
    $cookies = collect($response->headers->getCookies());
    $accessCookie = $cookies->first(fn ($c) => $c->getName() === 'access_token');
    $refreshCookie = $cookies->first(fn ($c) => $c->getName() === 'refresh_token');

    expect($accessCookie)->not->toBeNull();
    expect($accessCookie->isHttpOnly())->toBeTrue();
    expect($accessCookie->isSecure())->toBeTrue();
    expect($accessCookie->getSameSite())->toBe('strict');
    expect($accessCookie->getPath())->toBe('/api');

    expect($refreshCookie)->not->toBeNull();
    expect($refreshCookie->isHttpOnly())->toBeTrue();
    expect($refreshCookie->isSecure())->toBeTrue();
    expect($refreshCookie->getSameSite())->toBe('strict');
    expect($refreshCookie->getPath())->toBe('/api/v1/auth/refresh');
});

it('returns 401 with generic error for wrong password', function () {
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
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

it('returns 401 with same generic error for non-existent email', function () {
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $response = $this->postJson('/api/v1/auth/login', [
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

it('JWT contains correct claims (user_id, tenant_id, role)', function () {
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(200);

    // Extract access token from cookie
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

it('stores refresh token as SHA-256 hash in database', function () {
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(200);

    // Extract refresh token from cookie
    $cookies = collect($response->headers->getCookies());
    $refreshCookie = $cookies->first(fn ($c) => $c->getName() === 'refresh_token');
    $rawRefreshToken = $refreshCookie->getValue();

    // Verify the SHA-256 hash is stored in the database
    $expectedHash = hash('sha256', $rawRefreshToken);
    $storedToken = RefreshToken::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->first();

    expect($storedToken)->not->toBeNull();
    expect($storedToken->token_hash)->toBe($expectedHash);
    expect($storedToken->tenant_id)->toBe($this->company->id);
    expect($storedToken->is_revoked)->toBeFalse();
    expect(now()->diffInDays($storedToken->expires_at, false))->toBeBetween(6, 7);
});

it('dispatches UserLogin event on successful login', function () {
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
        'password' => 'SecurePass123!',
    ]);

    Event::assertDispatched(UserLogin::class, function (UserLogin $event) {
        expect($event->event_type)->toBe('user.login');
        expect($event->tenant_id)->toBe($this->company->id);
        expect($event->user_id)->toBe($this->user->id);
        expect($event->data)->toHaveKeys(['ip_address', 'user_agent']);

        return true;
    });

    Event::assertNotDispatched(UserLoginFailed::class);
});

it('dispatches UserLoginFailed event on wrong password', function () {
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
        'password' => 'WrongPassword123!',
    ]);

    Event::assertDispatched(UserLoginFailed::class, function (UserLoginFailed $event) {
        expect($event->event_type)->toBe('user.login_failed');
        expect($event->tenant_id)->toBe($this->company->id);
        expect($event->user_id)->toBe($this->user->id);
        expect($event->data['email'])->toBe('john@testcorp.com');
        expect($event->data['reason'])->toBe('invalid_password');

        return true;
    });

    Event::assertNotDispatched(UserLogin::class);
});

it('dispatches UserLoginFailed event on non-existent email', function () {
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'SomePassword123!',
    ]);

    Event::assertDispatched(UserLoginFailed::class, function (UserLoginFailed $event) {
        expect($event->tenant_id)->toBe('system');
        expect($event->user_id)->toBeNull();
        expect($event->data['email'])->toBe('nonexistent@example.com');
        expect($event->data['reason'])->toBe('user_not_found');

        return true;
    });

    Event::assertNotDispatched(UserLogin::class);
});

it('updates user last_login_at on successful login', function () {
    Event::fake([UserLogin::class, UserLoginFailed::class]);

    // Verify last_login_at is initially null
    expect($this->user->last_login_at)->toBeNull();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
        'password' => 'SecurePass123!',
    ]);

    // Refresh user from DB
    $updatedUser = User::withoutGlobalScopes()->find($this->user->id);
    expect($updatedUser->last_login_at)->not->toBeNull();
});

it('returns 422 when email is missing', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'password' => 'SecurePass123!',
    ]);

    $response->assertStatus(422);
});

it('returns 422 when password is missing', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'john@testcorp.com',
    ]);

    $response->assertStatus(422);
});
