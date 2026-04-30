<?php

namespace App\Services;

use App\Events\CandidateLogin;
use App\Events\CandidateRegistered;
use App\Models\Candidate;
use App\Models\CandidateRefreshToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class CandidateTokenRefreshResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly ?Candidate $candidate = null,
        public readonly ?string $errorCode = null,
    ) {}
}

class CandidateAuthService
{
    /**
     * Register a new candidate.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{candidate: Candidate, access_token: string, refresh_token: string}
     */
    public function register(array $data): array
    {
        $candidate = DB::transaction(function () use ($data) {
            // Create candidate record with bcrypt cost 12
            $candidate = Candidate::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => Hash::make($data['password'], ['rounds' => 12]),
            ]);

            return $candidate;
        });

        // Generate JWT with candidate claims (no tenant_id)
        $customClaims = [
            'role' => 'candidate',
        ];
        $accessToken = JWTAuth::claims($customClaims)->fromUser($candidate);

        // Generate opaque refresh token (64 bytes hex)
        $rawRefreshToken = bin2hex(random_bytes(64));
        $refreshTokenHash = hash('sha256', $rawRefreshToken);

        // Store SHA-256 hash in candidate_refresh_tokens table with 7-day expiry
        CandidateRefreshToken::create([
            'candidate_id' => $candidate->id,
            'token_hash' => $refreshTokenHash,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
        ]);

        // Dispatch CandidateRegistered event
        CandidateRegistered::dispatch(
            'platform', // no tenant context for candidates
            $candidate->id,
            [
                'name' => $candidate->name,
                'email' => $candidate->email,
            ],
        );

        return [
            'candidate' => $candidate,
            'access_token' => $accessToken,
            'refresh_token' => $rawRefreshToken,
        ];
    }

    /**
     * Authenticate a candidate and issue tokens.
     *
     * @param  string  $email
     * @param  string  $password
     * @param  string  $ipAddress
     * @param  string  $userAgent
     * @return array{candidate: Candidate, access_token: string, refresh_token: string}|null
     */
    public function login(string $email, string $password, string $ipAddress, string $userAgent): ?array
    {
        // Look up candidate by email
        $candidate = Candidate::where('email', $email)->first();

        // TIMING-SAFE: Always hash a dummy password even if candidate not found
        if (! $candidate) {
            Hash::make('dummy-password-for-timing-safety');

            return null;
        }

        // Verify bcrypt password
        if (! Hash::check($password, $candidate->password_hash)) {
            return null;
        }

        // Generate JWT Access Token (15 min) with candidate claims (no tenant_id)
        $customClaims = [
            'role' => 'candidate',
        ];
        $accessToken = JWTAuth::claims($customClaims)->fromUser($candidate);

        // Generate opaque Refresh Token (64 bytes hex)
        $rawRefreshToken = bin2hex(random_bytes(64));
        $refreshTokenHash = hash('sha256', $rawRefreshToken);

        // Store SHA-256 hash in candidate_refresh_tokens table with 7-day expiry
        CandidateRefreshToken::create([
            'candidate_id' => $candidate->id,
            'token_hash' => $refreshTokenHash,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
        ]);

        // Update candidate's last_login_at
        Candidate::where('id', $candidate->id)->update([
            'last_login_at' => now(),
        ]);
        $candidate = Candidate::find($candidate->id);

        // Dispatch CandidateLogin event
        CandidateLogin::dispatch(
            'platform', // no tenant context for candidates
            $candidate->id,
            [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ],
        );

        return [
            'candidate' => $candidate,
            'access_token' => $accessToken,
            'refresh_token' => $rawRefreshToken,
        ];
    }

    /**
     * Refresh an access/refresh token pair using a raw refresh token.
     *
     * Implements replay detection: if the token is not found or already revoked,
     * ALL refresh tokens for that candidate are revoked and error is returned.
     *
     * @param  string  $rawRefreshToken
     * @return CandidateTokenRefreshResult
     */
    public function refresh(string $rawRefreshToken): CandidateTokenRefreshResult
    {
        $tokenHash = hash('sha256', $rawRefreshToken);

        // Look up the refresh token in the database
        $storedToken = CandidateRefreshToken::where('token_hash', $tokenHash)->first();

        // REPLAY DETECTION: token not found or already revoked
        if (! $storedToken || $storedToken->is_revoked) {
            // If the token was found but revoked, revoke ALL tokens for that candidate
            if ($storedToken) {
                CandidateRefreshToken::where('candidate_id', $storedToken->candidate_id)
                    ->where('is_revoked', false)
                    ->update(['is_revoked' => true]);
            }

            return new CandidateTokenRefreshResult(
                success: false,
                errorCode: 'INVALID_REFRESH_TOKEN',
            );
        }

        // Check if the token is expired
        if ($storedToken->expires_at->isPast()) {
            return new CandidateTokenRefreshResult(
                success: false,
                errorCode: 'TOKEN_EXPIRED',
            );
        }

        // Revoke the current refresh token (token rotation)
        CandidateRefreshToken::where('id', $storedToken->id)
            ->update(['is_revoked' => true]);

        // Get the candidate from the refresh token
        $candidate = Candidate::find($storedToken->candidate_id);

        if (! $candidate) {
            return new CandidateTokenRefreshResult(
                success: false,
                errorCode: 'INVALID_REFRESH_TOKEN',
            );
        }

        // Generate new JWT Access Token with candidate claims
        $customClaims = [
            'role' => 'candidate',
        ];
        $newAccessToken = JWTAuth::claims($customClaims)->fromUser($candidate);

        // Generate new opaque Refresh Token
        $newRawRefreshToken = bin2hex(random_bytes(64));
        $newRefreshTokenHash = hash('sha256', $newRawRefreshToken);

        // Store new refresh token hash with 7-day expiry
        CandidateRefreshToken::create([
            'candidate_id' => $candidate->id,
            'token_hash' => $newRefreshTokenHash,
            'expires_at' => now()->addDays(7),
            'is_revoked' => false,
        ]);

        return new CandidateTokenRefreshResult(
            success: true,
            accessToken: $newAccessToken,
            refreshToken: $newRawRefreshToken,
            candidate: $candidate,
        );
    }

    /**
     * Log out a candidate by blocklisting their access token and revoking the refresh token.
     *
     * @param  string  $accessToken  The raw JWT access token
     * @param  string  $rawRefreshToken  The raw refresh token value
     */
    public function logout(string $accessToken, string $rawRefreshToken): void
    {
        // Parse the access token to extract claims
        $payload = JWTAuth::setToken($accessToken)->getPayload();
        $jti = $payload->get('jti');
        $exp = $payload->get('exp');

        // Calculate remaining lifetime of the access token (exp - now)
        $remainingSeconds = max(0, $exp - now()->timestamp);

        // Add the JTI to Redis blocklist with TTL = remaining lifetime
        if ($remainingSeconds > 0) {
            Cache::put("token_blocklist:{$jti}", true, $remainingSeconds);
        }

        // Revoke the associated refresh token in DB (find by SHA-256 hash)
        if ($rawRefreshToken) {
            $refreshTokenHash = hash('sha256', $rawRefreshToken);
            CandidateRefreshToken::where('token_hash', $refreshTokenHash)
                ->where('is_revoked', false)
                ->update(['is_revoked' => true]);
        }
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
}
