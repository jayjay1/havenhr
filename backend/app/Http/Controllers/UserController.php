<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Services\TenantContext;
use App\Services\UserService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected TenantContext $tenantContext,
    ) {}

    /**
     * Get a paginated list of users.
     *
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = $this->userService->list($page, $perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($user) => $this->formatUser($user)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Create a new user.
     *
     * POST /api/v1/users
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();

        if (! $tenantId) {
            return response()->json([
                'error' => [
                    'code' => 'TENANT_NOT_SET',
                    'message' => 'Tenant context is not set.',
                ],
            ], 403);
        }

        try {
            $user = $this->userService->create(
                data: $request->validated(),
                tenantId: $tenantId,
                createdById: null, // Will be set from auth context when auth middleware is added
            );

            $user->load('roles');

            return response()->json([
                'data' => $this->formatUser($user),
            ], 201);
        } catch (QueryException $e) {
            if ($this->isDuplicateEntryException($e)) {
                return response()->json([
                    'error' => [
                        'code' => 'EMAIL_ALREADY_EXISTS',
                        'message' => 'A user with this email already exists in this workspace.',
                    ],
                ], 409);
            }

            throw $e;
        }
    }

    /**
     * Get a single user by ID.
     *
     * GET /api/v1/users/{id}
     */
    public function show(string $id): JsonResponse
    {
        $user = $this->userService->find($id);

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $this->formatUser($user),
        ]);
    }

    /**
     * Update a user.
     *
     * PUT /api/v1/users/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $this->userService->find($id);

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        $data = $request->only(['name', 'email', 'is_active']);

        $updatedUser = $this->userService->update($id, $data);

        return response()->json([
            'data' => $this->formatUser($updatedUser),
        ]);
    }

    /**
     * Delete a user.
     *
     * DELETE /api/v1/users/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $user = $this->userService->find($id);

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ], 404);
        }

        $this->userService->delete($id);

        return response()->json([
            'data' => [
                'message' => 'User deleted successfully.',
            ],
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

    /**
     * Determine if the query exception is a unique constraint violation.
     */
    protected function isDuplicateEntryException(QueryException $e): bool
    {
        // SQLite: UNIQUE constraint failed
        if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
            return true;
        }

        // PostgreSQL: duplicate key value violates unique constraint
        if (str_contains($e->getMessage(), 'duplicate key value violates unique constraint')) {
            return true;
        }

        // MySQL: Duplicate entry
        $errorCode = $e->errorInfo[1] ?? null;
        if ($errorCode === 1062) {
            return true;
        }

        return false;
    }
}
