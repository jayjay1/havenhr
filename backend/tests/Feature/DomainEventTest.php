<?php

use App\Events\DomainEvent;
use App\Events\RoleAssigned;
use App\Events\RoleChanged;
use App\Events\TenantCreated;
use App\Events\UserLogin;
use App\Events\UserLoginFailed;
use App\Events\UserLogout;
use App\Events\UserPasswordReset;
use App\Events\UserRegistered;
use Illuminate\Support\Str;

describe('DomainEvent base class', function () {

    it('sets all payload fields correctly via constructor', function () {
        $tenantId = (string) Str::uuid();
        $userId = (string) Str::uuid();
        $data = ['key' => 'value', 'nested' => ['a' => 1]];
        $timestamp = '2025-01-15T10:30:00+00:00';

        $event = new TenantCreated(
            tenant_id: $tenantId,
            user_id: $userId,
            data: $data,
            timestamp: $timestamp,
        );

        expect($event->tenant_id)->toBe($tenantId);
        expect($event->user_id)->toBe($userId);
        expect($event->data)->toBe($data);
        expect($event->timestamp)->toBe($timestamp);
    });

    it('allows null user_id for system events', function () {
        $tenantId = (string) Str::uuid();

        $event = new TenantCreated(
            tenant_id: $tenantId,
            user_id: null,
            data: ['company_name' => 'Acme Corp'],
        );

        expect($event->user_id)->toBeNull();
    });

    it('generates ISO 8601 timestamp when none provided', function () {
        $tenantId = (string) Str::uuid();

        $event = new TenantCreated(tenant_id: $tenantId);

        // Verify it's a valid ISO 8601 timestamp
        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $event->timestamp);
        expect($parsed)->not->toBeFalse();
    });

    it('defaults data to empty array', function () {
        $tenantId = (string) Str::uuid();

        $event = new TenantCreated(tenant_id: $tenantId);

        expect($event->data)->toBe([]);
    });

    it('converts to payload array with all required fields', function () {
        $tenantId = (string) Str::uuid();
        $userId = (string) Str::uuid();
        $data = ['action_detail' => 'created'];
        $timestamp = '2025-06-01T12:00:00+00:00';

        $event = new TenantCreated(
            tenant_id: $tenantId,
            user_id: $userId,
            data: $data,
            timestamp: $timestamp,
        );

        $payload = $event->toPayload();

        expect($payload)->toHaveKeys(['event_type', 'tenant_id', 'user_id', 'data', 'timestamp']);
        expect($payload['event_type'])->toBe('tenant.created');
        expect($payload['tenant_id'])->toBe($tenantId);
        expect($payload['user_id'])->toBe($userId);
        expect($payload['data'])->toBe($data);
        expect($payload['timestamp'])->toBe($timestamp);
    });

    it('sets tenant-specific queue name for per-tenant ordering', function () {
        $tenantId = (string) Str::uuid();

        $event = new TenantCreated(tenant_id: $tenantId);

        expect($event->onQueue())->toBe("tenant:{$tenantId}:events");
        expect($event->broadcastQueue())->toBe("tenant:{$tenantId}:events");
    });

    it('configures 3 retry attempts', function () {
        $event = new TenantCreated(tenant_id: (string) Str::uuid());

        expect($event->tries)->toBe(3);
    });

    it('configures exponential backoff of 1s, 4s, 16s', function () {
        $event = new TenantCreated(tenant_id: (string) Str::uuid());

        expect($event->backoff())->toBe([1, 4, 16]);
    });

    it('implements ShouldQueue interface', function () {
        $event = new TenantCreated(tenant_id: (string) Str::uuid());

        expect($event)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });
});

describe('Concrete event classes', function () {

    it('TenantCreated has correct event_type', function () {
        $event = new TenantCreated(tenant_id: (string) Str::uuid());
        expect($event->event_type)->toBe('tenant.created');
    });

    it('UserRegistered has correct event_type', function () {
        $event = new UserRegistered(tenant_id: (string) Str::uuid());
        expect($event->event_type)->toBe('user.registered');
    });

    it('UserLogin has correct event_type', function () {
        $event = new UserLogin(tenant_id: (string) Str::uuid());
        expect($event->event_type)->toBe('user.login');
    });

    it('UserLoginFailed has correct event_type', function () {
        $event = new UserLoginFailed(tenant_id: (string) Str::uuid());
        expect($event->event_type)->toBe('user.login_failed');
    });

    it('UserLogout has correct event_type', function () {
        $event = new UserLogout(tenant_id: (string) Str::uuid());
        expect($event->event_type)->toBe('user.logout');
    });

    it('UserPasswordReset has correct event_type', function () {
        $event = new UserPasswordReset(tenant_id: (string) Str::uuid());
        expect($event->event_type)->toBe('user.password_reset');
    });

    it('RoleAssigned has correct event_type', function () {
        $event = new RoleAssigned(tenant_id: (string) Str::uuid());
        expect($event->event_type)->toBe('role.assigned');
    });

    it('RoleChanged has correct event_type', function () {
        $event = new RoleChanged(tenant_id: (string) Str::uuid());
        expect($event->event_type)->toBe('role.changed');
    });

    it('all concrete events extend DomainEvent', function () {
        $classes = [
            TenantCreated::class,
            UserRegistered::class,
            UserLogin::class,
            UserLoginFailed::class,
            UserLogout::class,
            UserPasswordReset::class,
            RoleAssigned::class,
            RoleChanged::class,
        ];

        foreach ($classes as $class) {
            $event = new $class(tenant_id: (string) Str::uuid());
            expect($event)->toBeInstanceOf(DomainEvent::class);
        }
    });

    it('all concrete events produce valid payloads with required fields', function () {
        $classes = [
            TenantCreated::class,
            UserRegistered::class,
            UserLogin::class,
            UserLoginFailed::class,
            UserLogout::class,
            UserPasswordReset::class,
            RoleAssigned::class,
            RoleChanged::class,
        ];

        $requiredKeys = ['event_type', 'tenant_id', 'user_id', 'data', 'timestamp'];

        foreach ($classes as $class) {
            $tenantId = (string) Str::uuid();
            $userId = (string) Str::uuid();
            $event = new $class(
                tenant_id: $tenantId,
                user_id: $userId,
                data: ['test' => true],
            );

            $payload = $event->toPayload();

            expect($payload)->toHaveKeys($requiredKeys);
            expect($payload['event_type'])->toBeString()->not->toBeEmpty();
            expect($payload['tenant_id'])->toBe($tenantId);
            expect($payload['user_id'])->toBe($userId);
            expect($payload['data'])->toBe(['test' => true]);
            expect($payload['timestamp'])->toBeString()->not->toBeEmpty();
        }
    });
});

describe('Event dispatching', function () {

    it('can dispatch TenantCreated event', function () {
        \Illuminate\Support\Facades\Event::fake();

        $tenantId = (string) Str::uuid();
        $userId = (string) Str::uuid();

        TenantCreated::dispatch($tenantId, $userId, ['company_name' => 'Test Corp']);

        \Illuminate\Support\Facades\Event::assertDispatched(TenantCreated::class, function ($event) use ($tenantId, $userId) {
            return $event->tenant_id === $tenantId
                && $event->user_id === $userId
                && $event->data['company_name'] === 'Test Corp';
        });
    });

    it('can dispatch UserRegistered event', function () {
        \Illuminate\Support\Facades\Event::fake();

        $tenantId = (string) Str::uuid();
        $userId = (string) Str::uuid();

        UserRegistered::dispatch($tenantId, $userId, ['email' => 'user@example.com']);

        \Illuminate\Support\Facades\Event::assertDispatched(UserRegistered::class);
    });

    it('can dispatch all event types', function () {
        \Illuminate\Support\Facades\Event::fake();

        $tenantId = (string) Str::uuid();
        $userId = (string) Str::uuid();

        TenantCreated::dispatch($tenantId, $userId);
        UserRegistered::dispatch($tenantId, $userId);
        UserLogin::dispatch($tenantId, $userId);
        UserLoginFailed::dispatch($tenantId, null, ['email' => 'test@example.com']);
        UserLogout::dispatch($tenantId, $userId);
        UserPasswordReset::dispatch($tenantId, $userId);
        RoleAssigned::dispatch($tenantId, $userId);
        RoleChanged::dispatch($tenantId, $userId);

        \Illuminate\Support\Facades\Event::assertDispatched(TenantCreated::class);
        \Illuminate\Support\Facades\Event::assertDispatched(UserRegistered::class);
        \Illuminate\Support\Facades\Event::assertDispatched(UserLogin::class);
        \Illuminate\Support\Facades\Event::assertDispatched(UserLoginFailed::class);
        \Illuminate\Support\Facades\Event::assertDispatched(UserLogout::class);
        \Illuminate\Support\Facades\Event::assertDispatched(UserPasswordReset::class);
        \Illuminate\Support\Facades\Event::assertDispatched(RoleAssigned::class);
        \Illuminate\Support\Facades\Event::assertDispatched(RoleChanged::class);
    });

    it('dispatched events contain all required payload fields', function () {
        \Illuminate\Support\Facades\Event::fake();

        $tenantId = (string) Str::uuid();
        $userId = (string) Str::uuid();
        $data = ['ip_address' => '127.0.0.1'];

        UserLogin::dispatch($tenantId, $userId, $data);

        \Illuminate\Support\Facades\Event::assertDispatched(UserLogin::class, function ($event) use ($tenantId, $userId, $data) {
            $payload = $event->toPayload();

            return $payload['event_type'] === 'user.login'
                && $payload['tenant_id'] === $tenantId
                && $payload['user_id'] === $userId
                && $payload['data'] === $data
                && ! empty($payload['timestamp']);
        });
    });
});
