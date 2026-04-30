<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterTenantRequest;
use App\Services\RegistrationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function __construct(
        protected RegistrationService $registrationService,
    ) {}

    /**
     * Register a new tenant and owner account.
     *
     * POST /api/v1/register
     */
    public function register(RegisterTenantRequest $request): JsonResponse
    {
        try {
            $result = $this->registrationService->register($request->validated());

            $tenant = $result['tenant'];
            $user = $result['user'];

            return response()->json([
                'data' => [
                    'tenant' => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'email_domain' => $tenant->email_domain,
                    ],
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => 'owner',
                    ],
                ],
            ], 201);
        } catch (QueryException $e) {
            // Catch unique constraint violation on email_domain
            if ($this->isDuplicateEntryException($e)) {
                return response()->json([
                    'error' => [
                        'code' => 'DOMAIN_ALREADY_EXISTS',
                        'message' => 'The company email domain is already registered.',
                    ],
                ], 409);
            }

            throw $e;
        }
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
