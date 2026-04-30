<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for all domain events in the HavenHR platform.
 *
 * Domain events are dispatched to tenant-specific Redis-backed queues
 * for per-tenant ordering. Each event carries a standardized payload
 * with event_type, tenant_id, user_id, data, and timestamp.
 */
abstract class DomainEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The event type identifier (e.g., "tenant.created", "user.login").
     */
    public string $event_type;

    /**
     * The tenant ID this event belongs to.
     */
    public string $tenant_id;

    /**
     * The user ID associated with this event (null for system events).
     */
    public ?string $user_id;

    /**
     * Additional event data as an associative array.
     */
    public array $data;

    /**
     * ISO 8601 timestamp of when the event occurred.
     */
    public string $timestamp;

    /**
     * The number of times the queued listener should be attempted.
     */
    public int $tries = 3;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Exponential backoff: 1s, 4s, 16s.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 4, 16];
    }

    /**
     * Create a new domain event instance.
     */
    public function __construct(
        string $tenant_id,
        ?string $user_id = null,
        array $data = [],
        ?string $timestamp = null,
    ) {
        $this->tenant_id = $tenant_id;
        $this->user_id = $user_id;
        $this->data = $data;
        $this->timestamp = $timestamp ?? now()->toIso8601String();
    }

    /**
     * Get the tenant-specific queue name for per-tenant ordering.
     */
    public function broadcastQueue(): string
    {
        return "tenant:{$this->tenant_id}:events";
    }

    /**
     * Determine which queue this event should be dispatched to.
     * Uses tenant-specific queue channels for per-tenant ordering.
     */
    public function onQueue(): string
    {
        return "tenant:{$this->tenant_id}:events";
    }

    /**
     * Convert the event to its payload array representation.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'event_type' => $this->event_type,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
        ];
    }
}
