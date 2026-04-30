<?php

namespace App\Services;

use App\Events\TenantCreated;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistrationService
{
    public function __construct(
        protected RoleTemplateService $roleTemplateService,
    ) {}

    /**
     * Register a new tenant with an owner account.
     *
     * Creates a Company, User, seeds default roles, assigns the Owner role,
     * and dispatches a TenantCreated event — all within a DB transaction.
     *
     * @param  array{company_name: string, company_email_domain: string, owner_name: string, owner_email: string, owner_password: string}  $data
     * @return array{tenant: Company, user: User}
     */
    public function register(array $data): array
    {
        $result = DB::transaction(function () use ($data) {
            // Create Company record
            $company = Company::create([
                'name' => $data['company_name'],
                'email_domain' => $data['company_email_domain'],
                'subscription_status' => 'trial',
            ]);

            // Create User record with bcrypt cost 12
            $user = User::withoutGlobalScopes()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password_hash' => Hash::make($data['owner_password'], ['rounds' => 12]),
                'tenant_id' => $company->id,
            ]);

            // Seed default roles for the tenant
            $roles = $this->roleTemplateService->createDefaultRoles($company);

            // Assign Owner role to the user
            $ownerRole = $roles->get('Owner');
            $user->roles()->attach($ownerRole->id, [
                'assigned_at' => now(),
            ]);

            return ['tenant' => $company, 'user' => $user, 'role' => $ownerRole];
        });

        // Dispatch TenantCreated event outside the transaction
        TenantCreated::dispatch(
            $result['tenant']->id,
            $result['user']->id,
            [
                'company_name' => $data['company_name'],
                'company_email_domain' => $data['company_email_domain'],
                'owner_name' => $data['owner_name'],
                'owner_email' => $data['owner_email'],
            ],
        );

        return [
            'tenant' => $result['tenant'],
            'user' => $result['user'],
        ];
    }
}
