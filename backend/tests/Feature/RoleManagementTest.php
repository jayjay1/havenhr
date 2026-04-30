<?php

use App\Events\RoleAssigned;
use App\Events\RoleChanged;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleTemplateService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    // Create a tenant with default roles
    $this->company = Company::factory()->create();
    $roleTemplateService = app(RoleTemplateService::class);
    $this->roles = $roleTemplateService->createDefaultRoles($this->company);

    // Set tenant context so global scopes work
    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($this->company->id);

    // Create Owner user
    $this->ownerUser = User::withoutGlobalScopes()->create([
        'name' => 'Owner User',
        'email' => 'owner@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);
    $ownerRole = $this->roles->get('Owner');
    $this->ownerUser->roles()->attach($ownerRole->id, ['assigned_at' => now()]);

    $this->ownerToken = JWTAuth::claims([
        'tenant_id' => $this->company->id,
        'role' => 'Owner',
    ])->fromUser($this->ownerUser);

    // Create Admin user
    $this->adminUser = User::withoutGlobalScopes()->create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);
    $adminRole = $this->roles->get('Admin');
    $this->adminUser->roles()->attach($adminRole->id, ['assigned_at' => now()]);

    $this->adminToken = JWTAuth::claims([
        'tenant_id' => $this->company->id,
        'role' => 'Admin',
    ])->fromUser($this->adminUser);

    // Create a target user (Viewer) for role assignment tests
    $this->targetUser = User::withoutGlobalScopes()->create([
        'name' => 'Target User',
        'email' => 'target@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);
    $viewerRole = $this->roles->get('Viewer');
    $this->targetUser->roles()->attach($viewerRole->id, ['assigned_at' => now()]);
});

// ─── Role Assignment (POST /api/v1/users/{id}/roles) ────────────────────────

it('Owner can assign any role including Owner', function () {
    Event::fake([RoleAssigned::class]);

    $ownerRoleId = $this->roles->get('Owner')->id;

    // Create a user without any role
    $newUser = User::withoutGlobalScopes()->create([
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->postJson("/api/v1/users/{$newUser->id}/roles", [
        'role_id' => $ownerRoleId,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'role'],
        ]);

    expect($response->json('data.role'))->toBe('Owner');

    // Verify user_role record was created
    $newUser->refresh();
    expect($newUser->roles()->where('role_id', $ownerRoleId)->exists())->toBeTrue();
});

it('Owner can assign Admin role', function () {
    Event::fake([RoleAssigned::class]);

    $adminRoleId = $this->roles->get('Admin')->id;

    $newUser = User::withoutGlobalScopes()->create([
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->postJson("/api/v1/users/{$newUser->id}/roles", [
        'role_id' => $adminRoleId,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.role'))->toBe('Admin');
});

it('Admin cannot assign Owner role (403)', function () {
    Event::fake([RoleAssigned::class]);

    $ownerRoleId = $this->roles->get('Owner')->id;

    $newUser = User::withoutGlobalScopes()->create([
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
    ])->postJson("/api/v1/users/{$newUser->id}/roles", [
        'role_id' => $ownerRoleId,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to assign the Owner role.',
            ],
        ]);

    // Verify no role was assigned
    expect($newUser->roles()->count())->toBe(0);

    // Verify no event was dispatched
    Event::assertNotDispatched(RoleAssigned::class);
});

it('Admin can assign non-Owner roles', function () {
    Event::fake([RoleAssigned::class]);

    $recruiterRoleId = $this->roles->get('Recruiter')->id;

    $newUser = User::withoutGlobalScopes()->create([
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
    ])->postJson("/api/v1/users/{$newUser->id}/roles", [
        'role_id' => $recruiterRoleId,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.role'))->toBe('Recruiter');
});

it('role assignment creates user_role record', function () {
    Event::fake([RoleAssigned::class]);

    $recruiterRoleId = $this->roles->get('Recruiter')->id;

    $newUser = User::withoutGlobalScopes()->create([
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);

    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->postJson("/api/v1/users/{$newUser->id}/roles", [
        'role_id' => $recruiterRoleId,
    ])->assertStatus(200);

    // Verify user_role pivot record exists with correct data
    $pivot = $newUser->roles()->where('role_id', $recruiterRoleId)->first();
    expect($pivot)->not->toBeNull();
    expect($pivot->pivot->assigned_by)->toBe($this->ownerUser->id);
    expect($pivot->pivot->assigned_at)->not->toBeNull();
});

it('dispatches RoleAssigned event on assignment', function () {
    Event::fake([RoleAssigned::class]);

    $recruiterRoleId = $this->roles->get('Recruiter')->id;

    $newUser = User::withoutGlobalScopes()->create([
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);

    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->postJson("/api/v1/users/{$newUser->id}/roles", [
        'role_id' => $recruiterRoleId,
    ])->assertStatus(200);

    Event::assertDispatched(RoleAssigned::class, function (RoleAssigned $event) use ($newUser) {
        expect($event->event_type)->toBe('role.assigned');
        expect($event->tenant_id)->toBe($this->company->id);
        expect($event->user_id)->toBe($this->ownerUser->id);
        expect($event->data['target_user_id'])->toBe($newUser->id);
        expect($event->data['role_name'])->toBe('Recruiter');
        expect($event->data['assigned_by'])->toBe($this->ownerUser->id);

        return true;
    });
});

it('returns 404 when assigning role to non-existent user', function () {
    $recruiterRoleId = $this->roles->get('Recruiter')->id;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->postJson('/api/v1/users/non-existent-id/roles', [
        'role_id' => $recruiterRoleId,
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'USER_NOT_FOUND',
            ],
        ]);
});

// ─── Role Update (PUT /api/v1/users/{id}/roles) ─────────────────────────────

it('role update changes the role and dispatches RoleChanged event', function () {
    Event::fake([RoleChanged::class]);

    $recruiterRoleId = $this->roles->get('Recruiter')->id;

    // Target user currently has Viewer role
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->putJson("/api/v1/users/{$this->targetUser->id}/roles", [
        'role_id' => $recruiterRoleId,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.role'))->toBe('Recruiter');

    // Verify old role was removed and new one assigned
    $this->targetUser->refresh();
    $roles = $this->targetUser->roles;
    expect($roles)->toHaveCount(1);
    expect($roles->first()->name)->toBe('Recruiter');

    // Verify RoleChanged event was dispatched with previous and new role
    Event::assertDispatched(RoleChanged::class, function (RoleChanged $event) {
        expect($event->event_type)->toBe('role.changed');
        expect($event->tenant_id)->toBe($this->company->id);
        expect($event->user_id)->toBe($this->ownerUser->id);
        expect($event->data['target_user_id'])->toBe($this->targetUser->id);
        expect($event->data['previous_role'])->toBe('Viewer');
        expect($event->data['new_role'])->toBe('Recruiter');
        expect($event->data['changed_by'])->toBe($this->ownerUser->id);

        return true;
    });
});

it('role change invalidates affected user tokens via force_reauth', function () {
    $recruiterRoleId = $this->roles->get('Recruiter')->id;

    Event::fake([RoleChanged::class]);

    // Generate a token for the target user before role change
    $targetToken = JWTAuth::claims([
        'tenant_id' => $this->company->id,
        'role' => 'Viewer',
    ])->fromUser($this->targetUser);

    // Verify the target user's token works before role change
    $this->withHeaders([
        'Authorization' => "Bearer {$targetToken}",
    ])->getJson('/api/v1/users')->assertStatus(200);

    // Change the target user's role
    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->putJson("/api/v1/users/{$this->targetUser->id}/roles", [
        'role_id' => $recruiterRoleId,
    ])->assertStatus(200);

    // Verify force_reauth cache key was set
    expect(Cache::has("force_reauth:{$this->targetUser->id}"))->toBeTrue();

    // Verify the target user's old token is now rejected
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$targetToken}",
    ])->getJson('/api/v1/users');

    $response->assertStatus(401)
        ->assertJson([
            'error' => [
                'code' => 'TOKEN_REVOKED',
            ],
        ]);
});

it('Admin cannot update role to Owner (403)', function () {
    Event::fake([RoleChanged::class]);

    $ownerRoleId = $this->roles->get('Owner')->id;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
    ])->putJson("/api/v1/users/{$this->targetUser->id}/roles", [
        'role_id' => $ownerRoleId,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to assign the Owner role.',
            ],
        ]);

    // Verify role was not changed
    $this->targetUser->refresh();
    expect($this->targetUser->roles->first()->name)->toBe('Viewer');

    Event::assertNotDispatched(RoleChanged::class);
});

it('Owner can update role to Owner', function () {
    Event::fake([RoleChanged::class]);

    $ownerRoleId = $this->roles->get('Owner')->id;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->putJson("/api/v1/users/{$this->targetUser->id}/roles", [
        'role_id' => $ownerRoleId,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.role'))->toBe('Owner');
});

it('returns 404 when updating role for non-existent user', function () {
    $recruiterRoleId = $this->roles->get('Recruiter')->id;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->putJson('/api/v1/users/non-existent-id/roles', [
        'role_id' => $recruiterRoleId,
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'USER_NOT_FOUND',
            ],
        ]);
});

it('role update replaces all existing roles with the new one', function () {
    Event::fake([RoleChanged::class, RoleAssigned::class]);

    $adminRoleId = $this->roles->get('Admin')->id;
    $recruiterRoleId = $this->roles->get('Recruiter')->id;

    // First, add a second role to the target user
    $this->targetUser->roles()->attach($adminRoleId, [
        'assigned_by' => $this->ownerUser->id,
        'assigned_at' => now(),
    ]);

    // Verify user has 2 roles
    expect($this->targetUser->roles()->count())->toBe(2);

    // Update role — should remove all existing and assign only the new one
    $this->withHeaders([
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->putJson("/api/v1/users/{$this->targetUser->id}/roles", [
        'role_id' => $recruiterRoleId,
    ])->assertStatus(200);

    $this->targetUser->refresh();
    expect($this->targetUser->roles)->toHaveCount(1);
    expect($this->targetUser->roles->first()->name)->toBe('Recruiter');
});
