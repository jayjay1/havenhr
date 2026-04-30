<?php

namespace App\Http\Middleware;

use App\Models\Candidate;
use App\Services\CandidateAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth as JWTAuthFacade;

/**
 * Middleware that verifies the JWT access token for candidate routes.
 *
 * Checks:
 * 1. Token is present and valid (signature + expiration)
 * 2. Token JTI is not blocklisted
 * 3. Token role claim equals "candidate" (rejects tenant user tokens)
 * 4. Resolves Candidate model from sub claim
 * 5. Sets the authenticated candidate on the request
 *
 * Returns 401 for invalid, expired, blocklisted, or non-candidate tokens.
 */
class CandidateAuth
{
    public function __construct(
        protected CandidateAuthService $candidateAuthService,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract token from Authorization header (Bearer token)
        $token = null;
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
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
        if ($jti && $this->candidateAuthService->isTokenBlocklisted($jti)) {
            return response()->json([
                'error' => [
                    'code' => 'TOKEN_BLOCKLISTED',
                    'message' => 'Access token has been revoked.',
                ],
            ], 401);
        }

        // Verify role claim equals "candidate" — reject tenant user tokens
        $role = $payload->get('role');
        if ($role !== 'candidate') {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Invalid token for candidate routes.',
                ],
            ], 401);
        }

        // Resolve the authenticated candidate
        $candidateId = $payload->get('sub');
        $candidate = Candidate::find($candidateId);

        if (! $candidate) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Candidate not found.',
                ],
            ], 401);
        }

        // Set the authenticated candidate on the request
        $request->setUserResolver(fn () => $candidate);

        // Store the JWT payload on the request for downstream use
        $request->attributes->set('jwt_payload', $payload);

        return $next($request);
    }
}
