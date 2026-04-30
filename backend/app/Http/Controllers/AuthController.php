<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * Whether cookies should be marked as Secure (HTTPS only).
     * Disabled in local/testing environments so cookies work on http://localhost.
     */
    protected function isSecureCookie(): bool
    {
        return app()->environment('production', 'staging');
    }

    /**
     * SameSite policy for cookies.
     * Use 'Lax' in development (cross-origin localhost), 'Strict' in production.
     */
    protected function cookieSameSite(): string
    {
        return app()->environment('production', 'staging') ? 'Strict' : 'Lax';
    }

    /**
     * Get the authenticated user's profile.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Not authenticated.',
                ],
            ], 401);
        }

        $user->load('roles.permissions');
        $role = $user->roles->first();
        $permissions = $role ? $role->permissions->pluck('name')->toArray() : [];

        return response()->json([
            'data' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'role' => strtolower($role?->name ?? 'viewer'),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'permissions' => $permissions,
            ],
        ]);
    }

    /**
     * Handle a login request.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            email: $request->validated('email'),
            password: $request->validated('password'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent() ?? '',
        );

        if ($result === null) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'Invalid credentials.',
                ],
            ], 401);
        }

        $response = response()->json([
            'data' => [
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'role' => $result['role'],
                ],
                'access_token' => $result['access_token'],
                'token_type' => 'bearer',
                'expires_in' => 900,
            ],
        ]);

        $response->withCookie(new Cookie(
            name: 'access_token',
            value: $result['access_token'],
            expire: now()->addMinutes(15)->getTimestamp(),
            path: '/',
            secure: $this->isSecureCookie(),
            httpOnly: true,
            sameSite: $this->cookieSameSite(),
        ));

        $response->withCookie(new Cookie(
            name: 'refresh_token',
            value: $result['refresh_token'],
            expire: now()->addDays(7)->getTimestamp(),
            path: '/',
            secure: $this->isSecureCookie(),
            httpOnly: true,
            sameSite: $this->cookieSameSite(),
        ));

        return $response;
    }

    /**
     * Handle a token refresh request.
     *
     * POST /api/v1/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $rawRefreshToken = $request->cookie('refresh_token');

        if (! $rawRefreshToken) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_REFRESH_TOKEN',
                    'message' => 'Refresh token is missing.',
                ],
            ], 401);
        }

        $result = $this->authService->refresh($rawRefreshToken);

        if (! $result->success) {
            return response()->json([
                'error' => [
                    'code' => $result->errorCode,
                    'message' => $result->errorCode === 'TOKEN_EXPIRED'
                        ? 'Refresh token has expired.'
                        : 'Invalid refresh token.',
                ],
            ], 401);
        }

        $response = response()->json([
            'data' => [
                'user' => [
                    'id' => $result->user->id,
                    'name' => $result->user->name,
                    'email' => $result->user->email,
                    'role' => $result->role,
                ],
                'token_type' => 'bearer',
                'expires_in' => 900,
            ],
        ]);

        $response->withCookie(new Cookie(
            name: 'access_token',
            value: $result->accessToken,
            expire: now()->addMinutes(15)->getTimestamp(),
            path: '/',
            secure: $this->isSecureCookie(),
            httpOnly: true,
            sameSite: $this->cookieSameSite(),
        ));

        $response->withCookie(new Cookie(
            name: 'refresh_token',
            value: $result->refreshToken,
            expire: now()->addDays(7)->getTimestamp(),
            path: '/',
            secure: $this->isSecureCookie(),
            httpOnly: true,
            sameSite: $this->cookieSameSite(),
        ));

        return $response;
    }

    /**
     * Handle a logout request.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->cookie('access_token');

        if (! $accessToken) {
            $authHeader = $request->header('Authorization', '');
            if (str_starts_with($authHeader, 'Bearer ')) {
                $accessToken = substr($authHeader, 7);
            }
        }

        if (! $accessToken) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Access token is missing.',
                ],
            ], 401);
        }

        $rawRefreshToken = $request->cookie('refresh_token') ?? '';

        $this->authService->logout(
            accessToken: $accessToken,
            rawRefreshToken: $rawRefreshToken,
            ipAddress: $request->ip() ?? '',
            userAgent: $request->userAgent() ?? '',
        );

        $response = response()->json([
            'data' => [
                'message' => 'Successfully logged out.',
            ],
        ]);

        $response->withCookie(new Cookie(
            name: 'access_token',
            value: '',
            expire: now()->subMinute()->getTimestamp(),
            path: '/',
            secure: $this->isSecureCookie(),
            httpOnly: true,
            sameSite: $this->cookieSameSite(),
        ));

        $response->withCookie(new Cookie(
            name: 'refresh_token',
            value: '',
            expire: now()->subMinute()->getTimestamp(),
            path: '/',
            secure: $this->isSecureCookie(),
            httpOnly: true,
            sameSite: $this->cookieSameSite(),
        ));

        return $response;
    }

    /**
     * Handle a forgot password request.
     *
     * POST /api/v1/auth/password/forgot
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword(
            email: $request->validated('email'),
        );

        return response()->json([
            'data' => [
                'message' => 'If an account exists with that email, a password reset link has been sent.',
            ],
        ]);
    }

    /**
     * Handle a password reset request.
     *
     * POST /api/v1/auth/password/reset
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->authService->resetPassword(
            token: $request->validated('token'),
            newPassword: $request->validated('password'),
            ipAddress: $request->ip() ?? '',
            userAgent: $request->userAgent() ?? '',
        );

        if (! $result->success) {
            $messages = [
                'TOKEN_EXPIRED' => 'The password reset token has expired.',
                'TOKEN_ALREADY_USED' => 'The password reset token has already been used.',
                'INVALID_RESET_TOKEN' => 'The password reset token is invalid.',
            ];

            return response()->json([
                'error' => [
                    'code' => $result->errorCode,
                    'message' => $messages[$result->errorCode] ?? 'Invalid reset token.',
                ],
            ], 400);
        }

        return response()->json([
            'data' => [
                'message' => 'Password has been reset successfully.',
            ],
        ]);
    }
}
