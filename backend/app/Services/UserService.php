<?php

namespace App\Services;

use App\Events\UserRegistered;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Create a new user with bcrypt password hash (cost 12), assign specified role,
     * and enforce email uniqueness per tenant.
     *
     * @param  array{name: string, email: string, password: string, role: string}  $data
     * @param  string  $tenantId
     * @param  string|null  $createdById  The ID of the user who created this user
     * @return User
     *
     * @throws \Illuminate\Database\QueryException  If email already exists within the tenant
     */
    public function create(array $data, string $tenantId, ?string $createdById = null): User
    {
        $user = DB::transaction(function () use ($data, $tenantId, $createdById) {
            // Create user with bcrypt cost 12
            $user = User::withoutGlobalScopes()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => Hash::make($data['password'], ['rounds' => 12]),
                'tenant_id' => $tenantId,
                'is_active' => true,
            ]);

            // Find the role within the tenant and assign it
            $role = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('name', $data['role'])
                ->first();

            if ($role) {
                $user->roles()->attach($role->id, [
                    'assigned_by' => $createdById,
                    'assigned_at' => now(),
                ]);
            }

            return $user;
        });

        // Dispatch UserRegistered event outside the transaction
        UserRegistered::dispatch(
            $tenantId,
            $user->id,
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'created_by' => $createdById,
            ],
        );

        return $user;
    }

    /**
     * Get a paginated list of users (tenant-scoped via global scope).
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return LengthAwarePaginator
     */
    public function list(int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return User::with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Find a user by ID (tenant-scoped via global scope).
     *
     * @param  string  $id
     * @return User|null
     */
    public function find(string $id): ?User
    {
        return User::with('roles')->find($id);
    }

    /**
     * Update a user's fields.
     *
     * @param  string  $id
     * @param  array  $data
     * @return User
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(string $id, array $data): User
    {
        $user = User::findOrFail($id);

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }

        if (isset($data['password'])) {
            $updateData['password_hash'] = Hash::make($data['password'], ['rounds' => 12]);
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }

        if (! empty($updateData)) {
            $user->update($updateData);
        }

        return $user->fresh(['roles']);
    }

    /**
     * Delete a user.
     *
     * @param  string  $id
     * @return bool
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function delete(string $id): bool
    {
        $user = User::findOrFail($id);

        return (bool) $user->delete();
    }
}
