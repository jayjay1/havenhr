<?php

namespace App\Http\Controllers;

use App\Events\RoleAssigned;
use App\Events\RoleChanged;
use App\Http\Requests\AssignRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Controller for role management.
 *
 * Provides endpoints for listing roles, viewing role details,
 * and assigning/updating roles for users.
 */
class RoleController extends Controller
{
    /**
     * Display a paginated list of roles for the current tenant.
     *
     * GET /api/v1/roles
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $roles = Role::query()->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ]);
    }

    /**
     * Display a single role with its permissions.
     *
     * GET /api/v1/roles/{id}
     */
    public function show(string $id): JsonResponse
    {
        $role = Role::with('permissions')->find($id);

        if (! $role) {
            return response()->json([
                'error' => [
                    'code' => 'ROLE_NOT_FOUND',
                    'message' => 'Role not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system_default' => $role->is_system_default,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'resource' => $permission->resource,
                        'action' => $permission->action,
                        'description' => $permission->description,
                    ];
                })->values()->toArray(),
            ],
        ]);
    }

    /**
     * Assign a role to a user.
     *
     * POST /api/v1/users/{id}/roles
     *
     * Enforces role assignment hierarchy:
     * - Owner can assign ALL roles
     * - Admin can assign all roles EXCEPT Owner
     */
    public function assignRole(AssignRoleRequest $request, string $userId): JsonResponse
    {
        // Get the requesting user's role from JWT claims
        $payload = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->getPayload();
        $requestingRole = $payload->get('role');
        $requestingUserId = $payload->get('sub');

        // Find the target user (tenant-scoped)
        $targetUser = User::with('roles')->find($userId);

        if (! $targetUser) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        // Find the role to assign
        $roleToAssign = Role::find($request->validated('role_id'));

        if (! $roleToAssign) {
            return response()->json([
                'error' => [
                    'code' => 'ROLE_NOT_FOUND',
                    'message' => 'Role not found.',
                ],
            ], 404);
        }

        // Enforce role assignment hierarchy
        if ($requestingRole !== 'Owner' && strtolower($roleToAssign->name) === 'owner') {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to assign the Owner role.',
                ],
            ], 403);
        }

        // Check if the user already has this role
        if ($targetUser->roles()->where('role_id', $roleToAssign->id)->exists()) {
            return response()->json([
                'error' => [
                    'code' => 'ROLE_ALREADY_ASSIGNED',
                    'message' => 'The user already has this role.',
                ],
            ], 409);
        }

        // Assign the role to the target user within a transaction
        DB::transaction(function () use ($targetUser, $roleToAssign, $requestingUserId) {
            $targetUser->roles()->attach($roleToAssign->id, [
                'assigned_by' => $requestingUserId,
                'assigned_at' => now(),
            ]);
        });

        // Dispatch RoleAssigned event
        RoleAssigned::dispatch(
            $targetUser->tenant_id,
            $requestingUserId,
            [
                'target_user_id' => $targetUser->id,
                'role_id' => $roleToAssign->id,
                'role_name' => $roleToAssign->name,
                'assigned_by' => $requestingUserId,
            ],
        );

        // Return the updated user with role
        $targetUser->load('roles');

        return response()->json([
            'data' => $this->formatUser($targetUser),
        ]);
    }

    /**
     * Update a user's role.
     *
     * PUT /api/v1/users/{id}/roles
     *
     * Removes existing role(s) and assigns the new one.
     * On role change: sets a force_reauth cache key so JwtAuth middleware
     * will reject the affected user's existing tokens.
     */
    public function updateRole(AssignRoleRequest $request, string $userId): JsonResponse
    {
        // Get the requesting user's role from JWT claims
        $payload = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->getPayload();
        $requestingRole = $payload->get('role');
        $requestingUserId = $payload->get('sub');

        // Find the target user (tenant-scoped)
        $targetUser = User::with('roles')->find($userId);

        if (! $targetUser) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        // Find the new role to assign
        $newRole = Role::find($request->validated('role_id'));

        if (! $newRole) {
            return response()->json([
                'error' => [
                    'code' => 'ROLE_NOT_FOUND',
                    'message' => 'Role not found.',
                ],
            ], 404);
        }

        // Enforce role assignment hierarchy
        if ($requestingRole !== 'Owner' && strtolower($newRole->name) === 'owner') {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to assign the Owner role.',
                ],
            ], 403);
        }

        // Capture previous role for the event
        $previousRole = $targetUser->roles->first();
        $previousRoleName = $previousRole?->name;

        // Remove existing roles and assign the new one within a transaction
        DB::transaction(function () use ($targetUser, $newRole, $requestingUserId) {
            $targetUser->roles()->detach();
            $targetUser->roles()->attach($newRole->id, [
                'assigned_by' => $requestingUserId,
                'assigned_at' => now(),
            ]);
        });

        // Invalidate affected user's tokens by setting a force_reauth cache key
        // TTL of 15 minutes (access token lifetime) ensures all existing tokens
        // will be rejected until they naturally expire
        Cache::put("force_reauth:{$targetUser->id}", true, 900);

        // Clear the RBAC permission cache for the old role so it gets refreshed
        if ($previousRoleName) {
            Cache::forget("rbac:permissions:{$targetUser->tenant_id}:{$previousRoleName}");
        }
        Cache::forget("rbac:permissions:{$targetUser->tenant_id}:{$newRole->name}");

        // Dispatch RoleChanged event with previous and new role
        RoleChanged::dispatch(
            $targetUser->tenant_id,
            $requestingUserId,
            [
                'target_user_id' => $targetUser->id,
                'previous_role' => $previousRoleName,
                'previous_role_id' => $previousRole?->id,
                'new_role' => $newRole->name,
                'new_role_id' => $newRole->id,
                'changed_by' => $requestingUserId,
            ],
        );

        // Return the updated user with new role
        $targetUser->load('roles');

        return response()->json([
            'data' => $this->formatUser($targetUser),
        ]);
    }

    /**
     * Format a user model for API response.
     */
    protected function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name ?? null,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
