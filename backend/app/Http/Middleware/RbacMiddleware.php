<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class RbacMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Get the JWT payload directly from JWTAuth (works with both our custom and Tymon's middleware)
        try {
            $payload = JWTAuth::parseToken()->getPayload();
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to access this resource.',
                ],
            ], 403);
        }

        $roleName = $payload->get('role');
        $tenantId = $payload->get('tenant_id');

        if (! $roleName || ! $tenantId) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to access this resource.',
                ],
            ], 403);
        }

        // Look up the role's permissions (cached for 5 minutes per tenant+role)
        $cacheKey = "rbac:permissions:{$tenantId}:{$roleName}";
        $permissions = Cache::remember($cacheKey, 300, function () use ($tenantId, $roleName) {
            $role = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('name', $roleName)
                ->first();

            if (! $role) {
                return [];
            }

            return $role->permissions()->pluck('name')->toArray();
        });

        if (! in_array($permission, $permissions, true)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to access this resource.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
