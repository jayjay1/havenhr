<?php

use App\Events\UserPasswordReset;
use App\Models\Company;
use App\Models\PasswordReset;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

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

// ── Forgot Password Tests ──────────────────────────────────────────────

it('forgot password returns same success response for registered email', function () {
    $response = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'john@testcorp.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'If an account exists with that email, a password reset link has been sent.',
            ],
        ]);
});

it('forgot password returns same success response for unregistered email', function () {
    $response = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'If an account exists with that email, a password reset link has been sent.',
            ],
        ]);
});

it('forgot password creates reset token with 60-minute expiry for registered email', function () {
    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'john@testcorp.com',
    ]);

    $resetRecord = PasswordReset::where('user_id', $this->user->id)->first();

    expect($resetRecord)->not->toBeNull();
    expect($resetRecord->is_used)->toBeFalse();
    expect($resetRecord->token_hash)->not->toBeEmpty();

    // Verify expiry is approximately 60 minutes from now
    $minutesUntilExpiry = now()->diffInMinutes($resetRecord->expires_at, false);
    expect($minutesUntilExpiry)->toBeBetween(59, 60);
});

it('forgot password does not create token for unregistered email', function () {
    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'nonexistent@example.com',
    ]);

    $resetCount = PasswordReset::count();
    expect($resetCount)->toBe(0);
});

// ── Reset Password Tests ───────────────────────────────────────────────

it('valid reset token updates password successfully', function () {
    Event::fake([UserPasswordReset::class]);

    // Generate a reset token
    $rawToken = bin2hex(random_bytes(64));
    $tokenHash = hash('sha256', $rawToken);

    PasswordReset::create([
        'user_id' => $this->user->id,
        'token_hash' => $tokenHash,
        'expires_at' => now()->addMinutes(60),
        'is_used' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/password/reset', [
        'token' => $rawToken,
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'Password has been reset successfully.',
            ],
        ]);

    // Verify password was actually updated
    $updatedUser = User::withoutGlobalScopes()->find($this->user->id);
    expect(Hash::check('NewSecurePass456!', $updatedUser->password_hash))->toBeTrue();
    expect(Hash::check('SecurePass123!', $updatedUser->password_hash))->toBeFalse();
});

it('reset marks token as used', function () {
    Event::fake([UserPasswordReset::class]);

    $rawToken = bin2hex(random_bytes(64));
    $tokenHash = hash('sha256', $rawToken);

    $resetRecord = PasswordReset::create([
        'user_id' => $this->user->id,
        'token_hash' => $tokenHash,
        'expires_at' => now()->addMinutes(60),
        'is_used' => false,
    ]);

    $this->postJson('/api/v1/auth/password/reset', [
        'token' => $rawToken,
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ]);

    $resetRecord->refresh();
    expect($resetRecord->is_used)->toBeTrue();
});

it('reset revokes all refresh tokens for the user', function () {
    Event::fake([UserPasswordReset::class]);

    // Create some refresh tokens for the user
    RefreshToken::withoutGlobalScopes()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->company->id,
        'token_hash' => hash('sha256', 'token1'),
        'expires_at' => now()->addDays(7),
        'is_revoked' => false,
    ]);

    RefreshToken::withoutGlobalScopes()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->company->id,
        'token_hash' => hash('sha256', 'token2'),
        'expires_at' => now()->addDays(7),
        'is_revoked' => false,
    ]);

    $rawToken = bin2hex(random_bytes(64));
    $tokenHash = hash('sha256', $rawToken);

    PasswordReset::create([
        'user_id' => $this->user->id,
        'token_hash' => $tokenHash,
        'expires_at' => now()->addMinutes(60),
        'is_used' => false,
    ]);

    $this->postJson('/api/v1/auth/password/reset', [
        'token' => $rawToken,
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ]);

    // All refresh tokens should be revoked
    $activeTokens = RefreshToken::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->where('is_revoked', false)
        ->count();

    expect($activeTokens)->toBe(0);
});

it('expired token returns error', function () {
    $rawToken = bin2hex(random_bytes(64));
    $tokenHash = hash('sha256', $rawToken);

    PasswordReset::create([
        'user_id' => $this->user->id,
        'token_hash' => $tokenHash,
        'expires_at' => now()->subMinutes(1), // expired
        'is_used' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/password/reset', [
        'token' => $rawToken,
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'TOKEN_EXPIRED',
                'message' => 'The password reset token has expired.',
            ],
        ]);
});

it('already-used token returns error', function () {
    $rawToken = bin2hex(random_bytes(64));
    $tokenHash = hash('sha256', $rawToken);

    PasswordReset::create([
        'user_id' => $this->user->id,
        'token_hash' => $tokenHash,
        'expires_at' => now()->addMinutes(60),
        'is_used' => true, // already used
    ]);

    $response = $this->postJson('/api/v1/auth/password/reset', [
        'token' => $rawToken,
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'TOKEN_ALREADY_USED',
                'message' => 'The password reset token has already been used.',
            ],
        ]);
});

it('invalid/unknown token returns error', function () {
    $response = $this->postJson('/api/v1/auth/password/reset', [
        'token' => 'completely-invalid-token-that-does-not-exist',
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => [
                'code' => 'INVALID_RESET_TOKEN',
                'message' => 'The password reset token is invalid.',
            ],
        ]);
});

it('dispatches UserPasswordReset event on successful reset', function () {
    Event::fake([UserPasswordReset::class]);

    $rawToken = bin2hex(random_bytes(64));
    $tokenHash = hash('sha256', $rawToken);

    PasswordReset::create([
        'user_id' => $this->user->id,
        'token_hash' => $tokenHash,
        'expires_at' => now()->addMinutes(60),
        'is_used' => false,
    ]);

    $this->postJson('/api/v1/auth/password/reset', [
        'token' => $rawToken,
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ]);

    Event::assertDispatched(UserPasswordReset::class, function (UserPasswordReset $event) {
        expect($event->event_type)->toBe('user.password_reset');
        expect($event->tenant_id)->toBe($this->company->id);
        expect($event->user_id)->toBe($this->user->id);
        expect($event->data)->toHaveKeys(['ip_address', 'user_agent']);

        return true;
    });
});

it('does not dispatch UserPasswordReset event on failed reset', function () {
    Event::fake([UserPasswordReset::class]);

    $response = $this->postJson('/api/v1/auth/password/reset', [
        'token' => 'invalid-token',
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ]);

    $response->assertStatus(400);
    Event::assertNotDispatched(UserPasswordReset::class);
});

it('returns 422 when forgot password email is missing', function () {
    $response = $this->postJson('/api/v1/auth/password/forgot', []);

    $response->assertStatus(422);
});

it('returns 422 when reset password token is missing', function () {
    $response = $this->postJson('/api/v1/auth/password/reset', [
        'password' => 'NewSecurePass456!',
        'password_confirmation' => 'NewSecurePass456!',
    ]);

    $response->assertStatus(422);
});
