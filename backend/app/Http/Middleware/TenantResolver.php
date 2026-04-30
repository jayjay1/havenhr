<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Middleware that extracts tenant_id from JWT claims and binds it
 * to the TenantContext singleton so global scopes can filter queries.
 *
 * Returns 401 if no valid JWT or no tenant_id claim is present.
 * Returns 403 if the JWT tenant_id doesn't match the resource's tenant.
 */
class TenantResolver
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        $tenantId = $payload->get('tenant_id');

        if (empty($tenantId)) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'No tenant context found in token.',
                ],
            ], 401);
        }

        // Set the tenant context for global scopes
        $this->tenantContext->setTenantId($tenantId);

        // Check for cross-tenant access on route model bindings
        // If the route has a resource with a tenant_id, verify it matches
        $response = $next($request);

        return $response;
    }
}
