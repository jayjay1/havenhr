<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateLoginRequest;
use App\Http\Requests\CandidateRegisterRequest;
use App\Services\CandidateAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateAuthController extends Controller
{
    public function __construct(
        protected CandidateAuthService $candidateAuthService,
    ) {}

    /**
     * Register a new candidate.
     *
     * POST /api/v1/candidate/auth/register
     */
    public function register(CandidateRegisterRequest $request): JsonResponse
    {
        $result = $this->candidateAuthService->register(
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'candidate' => [
                    'id' => $result['candidate']->id,
                    'name' => $result['candidate']->name,
                    'email' => $result['candidate']->email,
                ],
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
            ],
        ], 201);
    }

    /**
     * Handle a candidate login request.
     *
     * POST /api/v1/candidate/auth/login
     */
    public function login(CandidateLoginRequest $request): JsonResponse
    {
        $result = $this->candidateAuthService->login(
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

        return response()->json([
            'data' => [
                'candidate' => [
                    'id' => $result['candidate']->id,
                    'name' => $result['candidate']->name,
                    'email' => $result['candidate']->email,
                ],
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'token_type' => 'bearer',
                'expires_in' => 900,
            ],
        ]);
    }

    /**
     * Handle a candidate token refresh request.
     *
     * POST /api/v1/candidate/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $rawRefreshToken = $request->input('refresh_token');

        if (! $rawRefreshToken) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_REFRESH_TOKEN',
                    'message' => 'Refresh token is missing.',
                ],
            ], 401);
        }

        $result = $this->candidateAuthService->refresh($rawRefreshToken);

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

        return response()->json([
            'data' => [
                'candidate' => [
                    'id' => $result->candidate->id,
                    'name' => $result->candidate->name,
                    'email' => $result->candidate->email,
                ],
                'access_token' => $result->accessToken,
                'refresh_token' => $result->refreshToken,
                'token_type' => 'bearer',
                'expires_in' => 900,
            ],
        ]);
    }

    /**
     * Handle a candidate logout request.
     *
     * POST /api/v1/candidate/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Extract access token from Authorization header
        $accessToken = null;
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $accessToken = substr($authHeader, 7);
        }

        if (! $accessToken) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Access token is missing.',
                ],
            ], 401);
        }

        $rawRefreshToken = $request->input('refresh_token', '');

        $this->candidateAuthService->logout(
            accessToken: $accessToken,
            rawRefreshToken: $rawRefreshToken,
        );

        return response()->json([
            'data' => [
                'message' => 'Successfully logged out.',
            ],
        ]);
    }

    /**
     * Get the authenticated candidate's profile.
     *
     * GET /api/v1/candidate/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $candidate = $request->user();

        if (! $candidate) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Not authenticated.',
                ],
            ], 401);
        }

        return response()->json([
            'data' => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'location' => $candidate->location,
                'linkedin_url' => $candidate->linkedin_url,
                'portfolio_url' => $candidate->portfolio_url,
                'is_active' => $candidate->is_active,
                'email_verified_at' => $candidate->email_verified_at?->toIso8601String(),
                'last_login_at' => $candidate->last_login_at?->toIso8601String(),
                'created_at' => $candidate->created_at?->toIso8601String(),
                'updated_at' => $candidate->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
