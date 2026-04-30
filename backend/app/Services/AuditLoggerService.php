<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Service responsible for writing audit log entries.
 *
 * Creates append-only audit log records with all required fields
 * for compliance and security tracking.
 */
class AuditLoggerService
{
    /**
     * Supported audit action types.
     */
    public const ACTION_TYPES = [
        'user.registered',
        'user.login',
        'user.login_failed',
        'user.logout',
        'user.password_reset',
        'role.assigned',
        'role.changed',
        'tenant.created',
        'tenant.updated',
    ];

    /**
     * Log an auditable action.
     *
     * @param  string       $tenantId       The tenant this action belongs to
     * @param  string|null  $userId         The user who performed the action (null for system events)
     * @param  string       $action         The action type (e.g., 'user.login')
     * @param  string       $resourceType   The type of resource affected (e.g., 'user', 'tenant')
     * @param  string|null  $resourceId     The ID of the affected resource
     * @param  array|null   $previousState  The state before the action (nullable)
     * @param  array|null   $newState       The state after the action (nullable)
     * @param  string|null  $ipAddress      The IP address of the request
     * @param  string|null  $userAgent      The user agent of the request
     * @return AuditLog
     */
    public function log(
        string $tenantId,
        ?string $userId,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?array $previousState = null,
        ?array $newState = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        return AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
