<?php

namespace App\Listeners;

use App\Events\DomainEvent;
use App\Services\AuditLoggerService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that processes all domain events and creates audit log entries.
 *
 * Implements ShouldQueue for async processing so audit logging
 * does not impact API response times.
 */
class AuditLogListener implements ShouldQueue
{
    /**
     * The number of times the queued listener should be attempted.
     */
    public int $tries = 3;

    /**
     * Calculate the number of seconds to wait before retrying.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 4, 16];
    }

    /**
     * Create the event listener.
     */
    public function __construct(
        protected AuditLoggerService $auditLogger,
    ) {}

    /**
     * Handle the event.
     *
     * Extracts data from the domain event and creates an audit log entry.
     */
    public function handle(DomainEvent $event): void
    {
        $data = $event->data;

        $this->auditLogger->log(
            tenantId: $event->tenant_id,
            userId: $event->user_id,
            action: $event->event_type,
            resourceType: $this->extractResourceType($event->event_type),
            resourceId: $data['resource_id'] ?? null,
            previousState: $data['previous_state'] ?? null,
            newState: $data['new_state'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
        );
    }

    /**
     * Extract the resource type from the event type.
     *
     * Maps event types like "user.login" to resource type "user",
     * and "tenant.created" to resource type "tenant".
     */
    protected function extractResourceType(string $eventType): string
    {
        $parts = explode('.', $eventType);

        return $parts[0] ?? 'unknown';
    }
}
