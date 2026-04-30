<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('Database Migrations', function () {

    it('creates the companies table with correct columns', function () {
        expect(Schema::hasTable('companies'))->toBeTrue();
        expect(Schema::hasColumns('companies', [
            'id', 'name', 'email_domain', 'subscription_status', 'settings',
            'created_at', 'updated_at',
        ]))->toBeTrue();
    });

    it('creates the users table with correct columns', function () {
        expect(Schema::hasTable('users'))->toBeTrue();
        expect(Schema::hasColumns('users', [
            'id', 'tenant_id', 'name', 'email', 'password_hash',
            'email_verified_at', 'is_active', 'last_login_at',
            'created_at', 'updated_at',
        ]))->toBeTrue();
    });

    it('creates the roles table with correct columns', function () {
        expect(Schema::hasTable('roles'))->toBeTrue();
        expect(Schema::hasColumns('roles', [
            'id', 'tenant_id', 'name', 'description', 'is_system_default',
            'created_at', 'updated_at',
        ]))->toBeTrue();
    });

    it('creates the permissions table with correct columns', function () {
        expect(Schema::hasTable('permissions'))->toBeTrue();
        expect(Schema::hasColumns('permissions', [
            'id', 'name', 'resource', 'action', 'description',
            'created_at', 'updated_at',
        ]))->toBeTrue();
    });

    it('creates the role_permission pivot table with correct columns', function () {
        expect(Schema::hasTable('role_permission'))->toBeTrue();
        expect(Schema::hasColumns('role_permission', [
            'role_id', 'permission_id',
        ]))->toBeTrue();
    });

    it('creates the user_role pivot table with correct columns', function () {
        expect(Schema::hasTable('user_role'))->toBeTrue();
        expect(Schema::hasColumns('user_role', [
            'user_id', 'role_id', 'assigned_by', 'assigned_at',
        ]))->toBeTrue();
    });

    it('creates the refresh_tokens table with correct columns', function () {
        expect(Schema::hasTable('refresh_tokens'))->toBeTrue();
        expect(Schema::hasColumns('refresh_tokens', [
            'id', 'user_id', 'tenant_id', 'token_hash',
            'expires_at', 'is_revoked', 'created_at',
        ]))->toBeTrue();
    });

    it('creates the password_resets table with correct columns', function () {
        expect(Schema::hasTable('password_resets'))->toBeTrue();
        expect(Schema::hasColumns('password_resets', [
            'id', 'user_id', 'token_hash',
            'expires_at', 'is_used', 'created_at',
        ]))->toBeTrue();
    });

    it('creates the audit_logs table with correct columns', function () {
        expect(Schema::hasTable('audit_logs'))->toBeTrue();
        expect(Schema::hasColumns('audit_logs', [
            'id', 'tenant_id', 'user_id', 'action', 'resource_type',
            'resource_id', 'previous_state', 'new_state', 'ip_address',
            'user_agent', 'created_at',
        ]))->toBeTrue();
    });

    it('creates the failed_jobs table for the event bus', function () {
        expect(Schema::hasTable('failed_jobs'))->toBeTrue();
        expect(Schema::hasTable('jobs'))->toBeTrue();
        expect(Schema::hasTable('job_batches'))->toBeTrue();
    });

    it('enforces UUID primary keys on all core tables', function () {
        $uuidTables = [
            'companies', 'users', 'roles', 'permissions',
            'refresh_tokens', 'password_resets', 'audit_logs',
        ];

        foreach ($uuidTables as $table) {
            $type = Schema::getColumnType($table, 'id');
            // UUID columns report as 'string' or 'varchar' depending on driver
            expect(in_array($type, ['string', 'varchar', 'uuid']))->toBeTrue(
                "Table {$table} should have a string/UUID id column, got: {$type}"
            );
        }
    });

    it('enforces foreign key relationships via insert constraints', function () {
        // Verify that inserting a user with a non-existent tenant_id fails
        expect(fn () => DB::table('users')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Test',
            'email' => 'test@example.com',
            'password_hash' => 'hash',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
