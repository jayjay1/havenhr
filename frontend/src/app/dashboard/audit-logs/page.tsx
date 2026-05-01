"use client";

import { useState, useEffect, useCallback } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { apiClient, ApiRequestError } from "@/lib/api";
import {
  AuditLogFilterBar,
  type AuditLogFilters,
} from "@/components/dashboard/AuditLogFilterBar";

interface AuditLog {
  id: string;
  tenant_id: string;
  user_id: string | null;
  action: string;
  resource_type: string;
  resource_id: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
}

interface PaginatedAuditLogs {
  data: AuditLog[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
}

function ActionBadge({ action }: { action: string }) {
  const colors: Record<string, string> = {
    "user.login": "bg-green-100 text-green-800",
    "user.logout": "bg-gray-100 text-gray-800",
    "user.login_failed": "bg-red-100 text-red-800",
    "user.registered": "bg-blue-100 text-blue-800",
    "user.password_reset": "bg-yellow-100 text-yellow-800",
    "role.assigned": "bg-purple-100 text-purple-800",
    "role.changed": "bg-purple-100 text-purple-800",
    "tenant.created": "bg-indigo-100 text-indigo-800",
  };

  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
        colors[action] || "bg-gray-100 text-gray-800"
      }`}
    >
      {action}
    </span>
  );
}

export default function AuditLogsPage() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const page = Number(searchParams.get("page")) || 1;
  const perPage = 20;

  // Read filters from URL params
  const filters: AuditLogFilters = {
    action: searchParams.get("action") || undefined,
    from: searchParams.get("from") || undefined,
    to: searchParams.get("to") || undefined,
    user_id: searchParams.get("user_id") || undefined,
  };

  const hasActiveFilters = !!(
    filters.action ||
    filters.from ||
    filters.to ||
    filters.user_id
  );

  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [meta, setMeta] = useState<PaginatedAuditLogs["meta"] | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const fetchLogs = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const params = new URLSearchParams();
      params.set("page", String(page));
      params.set("per_page", String(perPage));
      if (filters.action) params.set("action", filters.action);
      if (filters.from) params.set("from", filters.from);
      if (filters.to) params.set("to", filters.to);
      if (filters.user_id) params.set("user_id", filters.user_id);

      const response = await apiClient.get<PaginatedAuditLogs>(
        `/audit-logs?${params.toString()}`
      );
      const paginated = response.data;
      if (paginated && Array.isArray(paginated.data)) {
        setLogs(paginated.data);
        setMeta(paginated.meta);
      } else if (Array.isArray(paginated)) {
        setLogs(paginated as unknown as AuditLog[]);
      } else {
        setLogs([]);
      }
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message || "Failed to load audit logs.");
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, filters.action, filters.from, filters.to, filters.user_id]);

  useEffect(() => {
    fetchLogs();
  }, [fetchLogs]);

  function updateUrl(newParams: Record<string, string | undefined>) {
    const params = new URLSearchParams();

    // Merge current filters with new values
    const merged = { ...filters, ...newParams };

    if (merged.action) params.set("action", merged.action);
    if (merged.from) params.set("from", merged.from);
    if (merged.to) params.set("to", merged.to);
    if (merged.user_id) params.set("user_id", merged.user_id);

    // Reset page to 1 on filter change
    params.set("page", "1");

    router.push(`/dashboard/audit-logs?${params.toString()}`);
  }

  function handleFilterChange(
    changedFilters: Record<string, string | undefined>
  ) {
    updateUrl(changedFilters);
  }

  function handleClearFilters() {
    router.push("/dashboard/audit-logs");
  }

  function handlePageChange(newPage: number) {
    const params = new URLSearchParams();
    params.set("page", String(newPage));
    if (filters.action) params.set("action", filters.action);
    if (filters.from) params.set("from", filters.from);
    if (filters.to) params.set("to", filters.to);
    if (filters.user_id) params.set("user_id", filters.user_id);
    router.push(`/dashboard/audit-logs?${params.toString()}`);
  }

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Audit Logs</h1>
        <p className="mt-1 text-sm text-gray-500">
          Track all actions and events in your organization.
        </p>
      </div>

      <AuditLogFilterBar
        filters={filters}
        onFilterChange={handleFilterChange}
        onClear={handleClearFilters}
        hasActiveFilters={hasActiveFilters}
      />

      {error && (
        <div
          role="alert"
          className="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700"
        >
          {error}
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div
            className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-r-transparent"
            role="status"
            aria-label="Loading audit logs"
          />
        </div>
      ) : !logs || logs.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-sm text-gray-500">No audit logs found.</p>
        </div>
      ) : (
        <>
          {/* Desktop table */}
          <div className="hidden md:block overflow-hidden rounded-lg border border-gray-200 bg-white">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Action
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Resource
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    IP Address
                  </th>
                  <th
                    scope="col"
                    className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                  >
                    Timestamp
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {logs.map((log) => (
                  <tr key={log.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <ActionBadge action={log.action} />
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {log.resource_type}
                      {log.resource_id && (
                        <span className="text-gray-400 ml-1 text-xs">
                          {log.resource_id.substring(0, 8)}...
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {log.ip_address || "—"}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(log.created_at)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile cards */}
          <div className="md:hidden space-y-3">
            {logs.map((log) => (
              <div
                key={log.id}
                className="bg-white rounded-lg border border-gray-200 p-4 space-y-2"
              >
                <div className="flex items-center justify-between">
                  <ActionBadge action={log.action} />
                  <span className="text-xs text-gray-400">
                    {formatDate(log.created_at)}
                  </span>
                </div>
                <p className="text-sm text-gray-600">
                  {log.resource_type}
                  {log.ip_address && (
                    <span className="text-gray-400 ml-2">
                      from {log.ip_address}
                    </span>
                  )}
                </p>
              </div>
            ))}
          </div>

          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <nav
              aria-label="Pagination"
              className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 mt-4 rounded-lg sm:px-6"
            >
              <div className="hidden sm:block">
                <p className="text-sm text-gray-700">
                  Page{" "}
                  <span className="font-medium">{meta.current_page}</span> of{" "}
                  <span className="font-medium">{meta.last_page}</span> (
                  {meta.total} total)
                </p>
              </div>
              <div className="flex flex-1 justify-between sm:justify-end gap-2">
                <button
                  type="button"
                  onClick={() => handlePageChange(page - 1)}
                  disabled={page <= 1}
                  className="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                <button
                  type="button"
                  onClick={() => handlePageChange(page + 1)}
                  disabled={page >= meta.last_page}
                  className="rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </div>
            </nav>
          )}
        </>
      )}
    </div>
  );
}
