<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for read-only audit log access.
 *
 * Provides a paginated, tenant-scoped endpoint for viewing audit logs.
 * Requires 'audit_logs.view' permission (enforced via RBAC middleware later).
 */
class AuditLogController extends Controller
{
    /**
     * Display a paginated list of audit logs for the current tenant.
     *
     * Supports filtering by action type via the 'action' query parameter.
     *
     * GET /api/v1/audit-logs?action=user.login&page=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $query = AuditLog::query()->orderBy('created_at', 'desc');

        if ($request->has('action')) {
            $query->where('action', $request->query('action'));
        }

        $auditLogs = $query->paginate($perPage);

        return response()->json([
            'data' => $auditLogs->items(),
            'meta' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
            ],
        ]);
    }
}
