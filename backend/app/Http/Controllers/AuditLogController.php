<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Carbon\Carbon;
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
     * Supports filtering by action type, date range, and user.
     *
     * GET /api/v1/audit-logs?action=user.login&from=2024-01-01&to=2024-12-31&user_id=uuid&page=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $query = AuditLog::query()->orderBy('created_at', 'desc');

        if ($request->has('action')) {
            $query->where('action', $request->query('action'));
        }

        // Filter by from date (silently ignore invalid dates)
        if ($request->has('from')) {
            try {
                $fromDate = Carbon::parse($request->query('from'))->startOfDay();
                $query->where('created_at', '>=', $fromDate);
            } catch (\Exception $e) {
                // Silently ignore invalid date format
            }
        }

        // Filter by to date (silently ignore invalid dates)
        if ($request->has('to')) {
            try {
                $toDate = Carbon::parse($request->query('to'))->endOfDay();
                $query->where('created_at', '<=', $toDate);
            } catch (\Exception $e) {
                // Silently ignore invalid date format
            }
        }

        // Filter by user_id
        if ($request->has('user_id')) {
            $query->where('user_id', $request->query('user_id'));
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
