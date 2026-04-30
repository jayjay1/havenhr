<?php

namespace App\Services;

use App\Events\UserLogin;
use App\Events\UserLoginFailed;
use App\Events\UserLogout;
use App\Events\UserPasswordReset;
use App\Models\PasswordReset;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class TokenRefreshResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly ?User $user = null,
        public readonly ?string $role = null,
        public readonly ?string $errorCode = null,
    ) {}
}

class PasswordResetResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorCode = null,
    ) {}
}

class AuthService
{
    /**
     * Authenticate a user and issue tokens.
     *
     * @param  string  $email
     * @param  string  $password
     * @param  string  $ipAddress
     * @param  string  $userAgent
     * @return array{access_token: string, refresh_token: string, user: User}|null
     */
    public function login(string $email, string $password, string $ipAddress, string $userAgent): ?array
    {
        // Look up user by email without global scopes (we don't know tenant yet)
        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        // TIMING-SAFE: Always hash a dummy password even if user not found
        // This prevents timing attacks that could reveal whether an email exists
        if (! $user) {
            Hash::make('dummy-password-for-timing-safety');

            UserLoginFailed::dispatch(
                'system', // no tenant context
                null,
                [
                    'email' => $email,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'reason' => 'user_not_found',
                ],
            );

            return null;
        }

        // Verify bcrypt password
        if (! Hash::check($password, $user->password_hash)) {
            UserLoginFailed::dispatch(
                $user->tenant_id,
                $user->id,
                [
                    'email' => $email,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'reason' => 'invalid_password',
                ],
            );

            return null;
        }

        // Get user's first role name
        $roleName = $user->roles()->first()?->name ?? 'none';

        // Generate JWT Access Token (15 min expiry) with custom claims
        $customClaims = [
            'tenant_id' => $user->tenant_id,
            'role' => $roleName,
        ];

        $accessToken = JWTAuth::claims($customClaims)->fromUser($user);

        // Generate opaque Refresh Token (64 bytes hex)
        $rawRefreshToken = bin2hex(random_bytes(64));
        $refreshTokenHash = hash('sha256', $rawRefreshToken);

        // Store SHA-256 hash in refresh_tokens table with 7-day expiry
        RefreshToken::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'token_hash' => $refreshTokenHash,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
        ]);

        // Update user's last_login_at
        User::withoutGlobalScopes()->where('id', $user->id)->update([
            'last_login_at' => now(),
        ]);
        $user = User::withoutGlobalScopes()->find($user->id);

        // Dispatch UserLogin event
        UserLogin::dispatch(
            $user->tenant_id,
            $user->id,
            [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ],
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $rawRefreshToken,
            'user' => $user,
            'role' => $roleName,
        ];
    }

    /**
     * Refresh an access/refresh token pair using a raw refresh token.
     *
     * Implements replay detection: if the token is not found or already revoked,
     * ALL refresh tokens for that user are revoked and 401 is returned.
     *
     * @param  string  $rawRefreshToken
     * @return TokenRefreshResult
     */
    public function refresh(string $rawRefreshToken): TokenRefreshResult
    {
        $tokenHash = hash('sha256', $rawRefreshToken);

        // Look up the refresh token in the database (bypass tenant scope)
        $storedToken = RefreshToken::withoutGlobalScopes()
            ->where('token_hash', $tokenHash)
            ->first();

        // REPLAY DETECTION: token not found or already revoked
        if (! $storedToken || $storedToken->is_revoked) {
            // If the token was found but revoked, revoke ALL tokens for that user
            if ($storedToken) {
                RefreshToken::withoutGlobalScopes()
                    ->where('user_id', $storedToken->user_id)
                    ->where('is_revoked', false)
                    ->update(['is_revoked' => true]);
            }

            return new TokenRefreshResult(
                success: false,
                errorCode: 'INVALID_REFRESH_TOKEN',
            );
        }

        // Check if the token is expired
        if ($storedToken->expires_at->isPast()) {
            return new TokenRefreshResult(
                success: false,
                errorCode: 'TOKEN_EXPIRED',
            );
        }

        // Revoke the current refresh token (token rotation)
        RefreshToken::withoutGlobalScopes()
            ->where('id', $storedToken->id)
            ->update(['is_revoked' => true]);

        // Get the user from the refresh token
        $user = User::withoutGlobalScopes()->find($storedToken->user_id);

        if (! $user) {
            return new TokenRefreshResult(
                success: false,
                errorCode: 'INVALID_REFRESH_TOKEN',
            );
        }

        // Get user's role
        $roleName = $user->roles()->first()?->name ?? 'none';

        // Generate new JWT Access Token (15 min expiry) with same claims as login
        $customClaims = [
            'tenant_id' => $user->tenant_id,
            'role' => $roleName,
        ];

        $newAccessToken = JWTAuth::claims($customClaims)->fromUser($user);

        // Generate new opaque Refresh Token
        $newRawRefreshToken = bin2hex(random_bytes(64));
        $newRefreshTokenHash = hash('sha256', $newRawRefreshToken);

        // Store new refresh token hash with 7-day expiry
        RefreshToken::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'token_hash' => $newRefreshTokenHash,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
        ]);

        return new TokenRefreshResult(
            success: true,
            accessToken: $newAccessToken,
            refreshToken: $newRawRefreshToken,
            user: $user,
            role: $roleName,
        );
    }

    /**
     * Log out a user by blocklisting their access token and revoking the refresh token.
     *
     * @param  string  $accessToken  The raw JWT access token
     * @param  string  $rawRefreshToken  The raw refresh token value
     * @param  string  $ipAddress
     * @param  string  $userAgent
     */
    public function logout(string $accessToken, string $rawRefreshToken, string $ipAddress = '', string $userAgent = ''): void
    {
        // Parse the access token to extract claims
        $payload = JWTAuth::setToken($accessToken)->getPayload();
        $jti = $payload->get('jti');
        $exp = $payload->get('exp');
        $tenantId = $payload->get('tenant_id');
        $userId = $payload->get('sub');

        // Calculate remaining lifetime of the access token (exp - now)
        $remainingSeconds = max(0, $exp - now()->timestamp);

        // Add the JTI to Redis blocklist with TTL = remaining lifetime
        if ($remainingSeconds > 0) {
            Cache::put("token_blocklist:{$jti}", true, $remainingSeconds);
        }

        // Revoke the associated refresh token in DB (find by SHA-256 hash)
        $refreshTokenHash = hash('sha256', $rawRefreshToken);
        RefreshToken::withoutGlobalScopes()
            ->where('token_hash', $refreshTokenHash)
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);

        // Dispatch UserLogout event
        UserLogout::dispatch(
            $tenantId,
            $userId,
            [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ],
        );
    }

    /**
     * Check if an access token JTI is blocklisted in Redis.
     *
     * @param  string  $jti  The JWT ID claim
     * @return bool  True if the token is blocklisted
     */
    public function isTokenBlocklisted(string $jti): bool
    {
        return Cache::has("token_blocklist:{$jti}");
    }

    /**
     * Handle a forgot password request.
     *
     * Always returns void (same success response) regardless of whether the email exists,
     * to prevent email enumeration attacks.
     *
     * If the user exists, generates a cryptographically random token (64 bytes hex),
     * stores the SHA-256 hash in the password_resets table with a 60-minute expiry,
     * and logs the reset link (email not configured).
     *
     * @param  string  $email
     */
    public function forgotPassword(string $email): void
    {
        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        if (! $user) {
            // Do nothing — always return the same response to prevent enumeration
            return;
        }

        // Generate cryptographically random token (64 bytes hex)
        $rawToken = bin2hex(random_bytes(64));
        $tokenHash = hash('sha256', $rawToken);

        // Store SHA-256 hash in password_resets table with 60-minute expiry
        PasswordReset::create([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addMinutes(60),
            'is_used' => false,
        ]);

        // Log the reset link (email not configured, so just log it)
        Log::info('Password reset link generated', [
            'user_id' => $user->id,
            'token' => $rawToken,
            'reset_url' => config('app.frontend_url', 'http://localhost:3000') . '/reset-password/' . $rawToken,
        ]);
    }

    /**
     * Reset a user's password using a reset token.
     *
     * @param  string  $token  The raw reset token
     * @param  string  $newPassword  The new password (plaintext)
     * @param  string  $ipAddress
     * @param  string  $userAgent
     * @return PasswordResetResult
     */
    public function resetPassword(string $token, string $newPassword, string $ipAddress = '', string $userAgent = ''): PasswordResetResult
    {
        $tokenHash = hash('sha256', $token);

        // Look up the token hash in password_resets table
        $resetRecord = PasswordReset::where('token_hash', $tokenHash)->first();

        if (! $resetRecord) {
            return new PasswordResetResult(
                success: false,
                errorCode: 'INVALID_RESET_TOKEN',
            );
        }

        // Check if already used
        if ($resetRecord->is_used) {
            return new PasswordResetResult(
                success: false,
                errorCode: 'TOKEN_ALREADY_USED',
            );
        }

        // Check if expired
        if ($resetRecord->expires_at->isPast()) {
            return new PasswordResetResult(
                success: false,
                errorCode: 'TOKEN_EXPIRED',
            );
        }

        // Update user's password_hash (bcrypt cost 12)
        $user = User::withoutGlobalScopes()->find($resetRecord->user_id);

        if (! $user) {
            return new PasswordResetResult(
                success: false,
                errorCode: 'INVALID_RESET_TOKEN',
            );
        }

        User::withoutGlobalScopes()
            ->where('id', $user->id)
            ->update([
                'password_hash' => Hash::make($newPassword, ['rounds' => 12]),
            ]);

        // Mark reset token as used
        $resetRecord->update(['is_used' => true]);

        // Revoke ALL refresh tokens for that user
        RefreshToken::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);

        // Dispatch UserPasswordReset event
        UserPasswordReset::dispatch(
            $user->tenant_id,
            $user->id,
            [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ],
        );

        return new PasswordResetResult(success: true);
    }
}
