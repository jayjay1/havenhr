<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth as JWTAuthFacade;

/**
 * Middleware that verifies the JWT access token from cookie or Authorization header.
 *
 * Checks:
 * 1. Token is present and valid (signature + expiration)
 * 2. Token JTI is not blocklisted (via AuthService::isTokenBlocklisted)
 * 3. Sets the authenticated user on the request
 *
 * Returns 401 for invalid, expired, or blocklisted tokens.
 */
class JwtAuth
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract token from cookie first, then Authorization header
        $token = $request->cookie('access_token');

        if (! $token) {
            $authHeader = $request->header('Authorization', '');
            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
            }
        }

        if (! $token) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        try {
            // Set the token and parse the payload (verifies signature + expiration)
            $payload = JWTAuthFacade::setToken($token)->getPayload();
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'error' => [
                    'code' => 'TOKEN_EXPIRED',
                    'message' => 'Access token has expired.',
                ],
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'error' => [
                    'code' => 'TOKEN_INVALID',
                    'message' => 'Access token is invalid.',
                ],
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        // Check if the token JTI is blocklisted
        $jti = $payload->get('jti');
        if ($jti && $this->authService->isTokenBlocklisted($jti)) {
            return response()->json([
                'error' => [
                    'code' => 'TOKEN_BLOCKLISTED',
                    'message' => 'Access token has been revoked.',
                ],
            ], 401);
        }

        // Resolve the authenticated user
        $userId = $payload->get('sub');

        // Check if the user has been flagged for forced re-authentication
        // (e.g., after a role change). The force_reauth key is set with a TTL
        // matching the access token lifetime (15 min) so all existing tokens
        // are rejected until they naturally expire.
        if ($userId && Cache::has("force_reauth:{$userId}")) {
            return response()->json([
                'error' => [
                    'code' => 'TOKEN_REVOKED',
                    'message' => 'Your role has been changed. Please log in again.',
                ],
            ], 401);
        }
        $user = User::withoutGlobalScopes()->find($userId);

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'User not found.',
                ],
            ], 401);
        }

        // Set the authenticated user on the request
        $request->setUserResolver(fn () => $user);

        // Store the JWT payload on the request for downstream middleware
        $request->attributes->set('jwt_payload', $payload);

        file_put_contents(storage_path('logs/rbac_debug.log'), date('Y-m-d H:i:s') . " JwtAuth: user={$user->id}, role={$payload->get('role')}\n", FILE_APPEND);

        return $next($request);
    }
}
