<?php

use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterTenantRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\Company;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Route setup for testing each Form Request
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    Route::post('/test/register-tenant', function (RegisterTenantRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    Route::post('/test/login', function (LoginRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    Route::post('/test/create-user', function (CreateUserRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    Route::post('/test/forgot-password', function (ForgotPasswordRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    Route::post('/test/reset-password', function (ResetPasswordRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');

    Route::post('/test/assign-role', function (AssignRoleRequest $request) {
        return response()->json(['data' => $request->validated()]);
    })->middleware('api');
});

/*
|--------------------------------------------------------------------------
| RegisterTenantRequest
|--------------------------------------------------------------------------
*/

describe('RegisterTenantRequest', function () {
    it('accepts valid registration data', function () {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'Acme Corp',
            'company_email_domain' => 'acme.com',
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'StrongPass1!ab',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.company_name', 'Acme Corp');
        $response->assertJsonPath('data.company_email_domain', 'acme.com');
    });

    it('requires company_name', function () {
        $response = $this->postJson('/test/register-tenant', [
            'company_email_domain' => 'acme.com',
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'StrongPass1!ab',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('company_name');
    });

    it('enforces company_name max length of 255', function () {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => str_repeat('a', 256),
            'company_email_domain' => 'acme.com',
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'StrongPass1!ab',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('company_name');
    });

    it('requires company_email_domain', function () {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'Acme Corp',
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'StrongPass1!ab',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('company_email_domain');
    });

    it('rejects duplicate company_email_domain', function () {
        Company::create([
            'name' => 'Existing Corp',
            'email_domain' => 'existing.com',
            'subscription_status' => 'trial',
        ]);

        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'New Corp',
            'company_email_domain' => 'existing.com',
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@existing.com',
            'owner_password' => 'StrongPass1!ab',
        ]);

        $response->assertStatus(409);
        expect($response->json('error.code'))->toBe('DOMAIN_ALREADY_EXISTS');
    });

    it('rejects invalid domain format', function (string $domain) {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'Acme Corp',
            'company_email_domain' => $domain,
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'StrongPass1!ab',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('company_email_domain');
    })->with([
        'no TLD' => ['acme'],
        'starts with dash' => ['-acme.com'],
        'has spaces' => ['acme .com'],
        'has @' => ['@acme.com'],
    ]);

    it('accepts valid domain formats', function (string $domain) {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'Acme Corp',
            'company_email_domain' => $domain,
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'StrongPass1!ab',
        ]);

        $response->assertStatus(200);
    })->with([
        'simple domain' => ['acme.com'],
        'subdomain' => ['hr.acme.com'],
        'hyphenated' => ['acme-corp.com'],
        'long TLD' => ['acme.company'],
    ]);

    it('requires owner_name', function () {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'Acme Corp',
            'company_email_domain' => 'acme.com',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'StrongPass1!ab',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('owner_name');
    });

    it('requires owner_email to be RFC compliant', function () {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'Acme Corp',
            'company_email_domain' => 'acme.com',
            'owner_name' => 'Jane Doe',
            'owner_email' => 'not-an-email',
            'owner_password' => 'StrongPass1!ab',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('owner_email');
    });

    it('requires owner_password with StrongPassword rule', function () {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'Acme Corp',
            'company_email_domain' => 'acme.com',
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'weak',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('owner_password');
    });

    it('redacts owner_password in error response', function () {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'Acme Corp',
            'company_email_domain' => 'acme.com',
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'weak',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.details.fields.owner_password.value', '[REDACTED]');
    });

    it('rejects unknown fields', function () {
        $response = $this->postJson('/test/register-tenant', [
            'company_name' => 'Acme Corp',
            'company_email_domain' => 'acme.com',
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@acme.com',
            'owner_password' => 'StrongPass1!ab',
            'extra_field' => 'not allowed',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('extra_field');
    });
});

/*
|--------------------------------------------------------------------------
| LoginRequest
|--------------------------------------------------------------------------
*/

describe('LoginRequest', function () {
    it('accepts valid login data', function () {
        $response = $this->postJson('/test/login', [
            'email' => 'user@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(200);
    });

    it('requires email', function () {
        $response = $this->postJson('/test/login', [
            'password' => 'anypassword',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('email');
    });

    it('requires email to be RFC compliant', function () {
        $response = $this->postJson('/test/login', [
            'email' => 'not-an-email',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('email');
    });

    it('requires password', function () {
        $response = $this->postJson('/test/login', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('password');
    });

    it('does not enforce password complexity for login', function () {
        $response = $this->postJson('/test/login', [
            'email' => 'user@example.com',
            'password' => 'short',
        ]);

        // Login should accept any password string — complexity is only for registration
        $response->assertStatus(200);
    });

    it('rejects unknown fields', function () {
        $response = $this->postJson('/test/login', [
            'email' => 'user@example.com',
            'password' => 'anypassword',
            'remember_me' => true,
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('remember_me');
    });

    it('redacts password in error response', function () {
        $response = $this->postJson('/test/login', [
            'password' => 'anypassword',
        ]);

        $response->assertStatus(422);
        // password field should be redacted even though it's valid — email is missing
        $response->assertJsonPath('error.details.fields.password.value', '[REDACTED]');
    })->skip(fn () => true, 'Password is valid here so no error for it — only email errors');
});

/*
|--------------------------------------------------------------------------
| CreateUserRequest
|--------------------------------------------------------------------------
*/

describe('CreateUserRequest', function () {
    it('accepts valid user creation data', function () {
        $response = $this->postJson('/test/create-user', [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'password' => 'StrongPass1!ab',
            'role' => 'Admin',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'John Smith');
        $response->assertJsonPath('data.role', 'Admin');
    });

    it('requires name', function () {
        $response = $this->postJson('/test/create-user', [
            'email' => 'john@example.com',
            'password' => 'StrongPass1!ab',
            'role' => 'Admin',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('name');
    });

    it('enforces name max length of 255', function () {
        $response = $this->postJson('/test/create-user', [
            'name' => str_repeat('a', 256),
            'email' => 'john@example.com',
            'password' => 'StrongPass1!ab',
            'role' => 'Admin',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('name');
    });

    it('requires email to be RFC compliant', function () {
        $response = $this->postJson('/test/create-user', [
            'name' => 'John Smith',
            'email' => 'invalid-email',
            'password' => 'StrongPass1!ab',
            'role' => 'Admin',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('email');
    });

    it('requires password with StrongPassword rule', function () {
        $response = $this->postJson('/test/create-user', [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'password' => 'weak',
            'role' => 'Admin',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('password');
    });

    it('redacts password in error response', function () {
        $response = $this->postJson('/test/create-user', [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'password' => 'weak',
            'role' => 'Admin',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.details.fields.password.value', '[REDACTED]');
    });

    it('accepts all valid role names', function (string $role) {
        $response = $this->postJson('/test/create-user', [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'password' => 'StrongPass1!ab',
            'role' => $role,
        ]);

        $response->assertStatus(200);
    })->with(['Owner', 'Admin', 'Recruiter', 'Hiring_Manager', 'Viewer']);

    it('rejects invalid role names', function (string $role) {
        $response = $this->postJson('/test/create-user', [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'password' => 'StrongPass1!ab',
            'role' => $role,
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('role');
    })->with([
        'lowercase' => ['admin'],
        'nonexistent' => ['SuperAdmin'],
        'empty' => [''],
    ]);

    it('rejects unknown fields', function () {
        $response = $this->postJson('/test/create-user', [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'password' => 'StrongPass1!ab',
            'role' => 'Admin',
            'department' => 'Engineering',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('department');
    });
});

/*
|--------------------------------------------------------------------------
| ForgotPasswordRequest
|--------------------------------------------------------------------------
*/

describe('ForgotPasswordRequest', function () {
    it('accepts valid email', function () {
        $response = $this->postJson('/test/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(200);
    });

    it('requires email', function () {
        $response = $this->postJson('/test/forgot-password', []);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('email');
    });

    it('requires email to be RFC compliant', function () {
        $response = $this->postJson('/test/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('email');
    });

    it('rejects unknown fields', function () {
        $response = $this->postJson('/test/forgot-password', [
            'email' => 'user@example.com',
            'username' => 'extra',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('username');
    });
});

/*
|--------------------------------------------------------------------------
| ResetPasswordRequest
|--------------------------------------------------------------------------
*/

describe('ResetPasswordRequest', function () {
    it('accepts valid reset password data', function () {
        $response = $this->postJson('/test/reset-password', [
            'token' => 'valid-reset-token-string',
            'password' => 'NewStrongPass1!',
            'password_confirmation' => 'NewStrongPass1!',
        ]);

        $response->assertStatus(200);
    });

    it('requires token', function () {
        $response = $this->postJson('/test/reset-password', [
            'password' => 'NewStrongPass1!',
            'password_confirmation' => 'NewStrongPass1!',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('token');
    });

    it('requires password with StrongPassword rule', function () {
        $response = $this->postJson('/test/reset-password', [
            'token' => 'valid-reset-token-string',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('password');
    });

    it('requires password_confirmation', function () {
        $response = $this->postJson('/test/reset-password', [
            'token' => 'valid-reset-token-string',
            'password' => 'NewStrongPass1!',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('password_confirmation');
    });

    it('requires password_confirmation to match password', function () {
        $response = $this->postJson('/test/reset-password', [
            'token' => 'valid-reset-token-string',
            'password' => 'NewStrongPass1!',
            'password_confirmation' => 'DifferentPass1!',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('password_confirmation');
    });

    it('redacts password fields in error response', function () {
        $response = $this->postJson('/test/reset-password', [
            'token' => 'valid-reset-token-string',
            'password' => 'weak',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.details.fields.password.value', '[REDACTED]');
        $response->assertJsonPath('error.details.fields.password_confirmation.value', '[REDACTED]');
    });

    it('rejects unknown fields', function () {
        $response = $this->postJson('/test/reset-password', [
            'token' => 'valid-reset-token-string',
            'password' => 'NewStrongPass1!',
            'password_confirmation' => 'NewStrongPass1!',
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('email');
    });
});

/*
|--------------------------------------------------------------------------
| AssignRoleRequest
|--------------------------------------------------------------------------
*/

describe('AssignRoleRequest', function () {
    it('accepts valid role_id that exists in the database', function () {
        $company = Company::create([
            'name' => 'Test Corp',
            'email_domain' => 'test.com',
            'subscription_status' => 'trial',
        ]);

        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Admin',
            'description' => 'Administrator',
            'is_system_default' => true,
            'tenant_id' => $company->id,
        ]);

        $response = $this->postJson('/test/assign-role', [
            'role_id' => $role->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.role_id', $role->id);
    });

    it('requires role_id', function () {
        $response = $this->postJson('/test/assign-role', []);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('role_id');
    });

    it('requires role_id to be a valid UUID', function () {
        $response = $this->postJson('/test/assign-role', [
            'role_id' => 'not-a-uuid',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('role_id');
    });

    it('requires role_id to exist in the roles table', function () {
        $response = $this->postJson('/test/assign-role', [
            'role_id' => '00000000-0000-4000-a000-000000000000',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('role_id');
    });

    it('rejects unknown fields', function () {
        $company = Company::create([
            'name' => 'Test Corp',
            'email_domain' => 'test2.com',
            'subscription_status' => 'trial',
        ]);

        $role = Role::withoutGlobalScopes()->create([
            'name' => 'Admin',
            'description' => 'Administrator',
            'is_system_default' => true,
            'tenant_id' => $company->id,
        ]);

        $response = $this->postJson('/test/assign-role', [
            'role_id' => $role->id,
            'user_id' => 'some-uuid',
        ]);

        $response->assertStatus(422);
        expect($response->json('error.details.fields'))->toHaveKey('user_id');
    });
});

/*
|--------------------------------------------------------------------------
| All Form Requests extend BaseFormRequest
|--------------------------------------------------------------------------
*/

describe('Form Request inheritance', function () {
    it('RegisterTenantRequest extends BaseFormRequest', function () {
        $request = new RegisterTenantRequest();
        expect($request)->toBeInstanceOf(\App\Http\Requests\BaseFormRequest::class);
    });

    it('LoginRequest extends BaseFormRequest', function () {
        $request = new LoginRequest();
        expect($request)->toBeInstanceOf(\App\Http\Requests\BaseFormRequest::class);
    });

    it('CreateUserRequest extends BaseFormRequest', function () {
        $request = new CreateUserRequest();
        expect($request)->toBeInstanceOf(\App\Http\Requests\BaseFormRequest::class);
    });

    it('ForgotPasswordRequest extends BaseFormRequest', function () {
        $request = new ForgotPasswordRequest();
        expect($request)->toBeInstanceOf(\App\Http\Requests\BaseFormRequest::class);
    });

    it('ResetPasswordRequest extends BaseFormRequest', function () {
        $request = new ResetPasswordRequest();
        expect($request)->toBeInstanceOf(\App\Http\Requests\BaseFormRequest::class);
    });

    it('AssignRoleRequest extends BaseFormRequest', function () {
        $request = new AssignRoleRequest();
        expect($request)->toBeInstanceOf(\App\Http\Requests\BaseFormRequest::class);
    });
});
