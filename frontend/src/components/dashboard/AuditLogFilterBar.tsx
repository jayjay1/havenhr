"use client";

import { useState, useEffect, useCallback } from "react";
import { apiClient } from "@/lib/api";
import type { User } from "@/types/user";
import type { PaginatedResponse } from "@/types/api";

const ACTION_TYPES = [
  "user.login",
  "user.logout",
  "user.login_failed",
  "user.registered",
  "user.password_reset",
  "role.assigned",
  "role.changed",
  "tenant.created",
  "job.created",
  "job.updated",
  "job.deleted",
  "job.status_changed",
  "application.created",
  "application.stage_changed",
];

export interface AuditLogFilters {
  action?: string;
  from?: string;
  to?: string;
  user_id?: string;
}

interface AuditLogFilterBarProps {
  filters: AuditLogFilters;
  onFilterChange: (filters: Record<string, string | undefined>) => void;
  onClear: () => void;
  hasActiveFilters: boolean;
}

export function AuditLogFilterBar({
  filters,
  onFilterChange,
  onClear,
  hasActiveFilters,
}: AuditLogFilterBarProps) {
  const [users, setUsers] = useState<User[]>([]);
  const [usersLoading, setUsersLoading] = useState(false);

  const fetchUsers = useCallback(async () => {
    setUsersLoading(true);
    try {
      const response = await apiClient.get<PaginatedResponse<User>>(
        "/users?per_page=100"
      );
      const data = response.data;
      if (data && Array.isArray(data.data)) {
        setUsers(data.data);
      } else if (Array.isArray(data)) {
        setUsers(data as unknown as User[]);
      }
    } catch {
      // Silently fail — filter still works without user list
    } finally {
      setUsersLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  return (
    <div className="mb-4 bg-white rounded-lg border border-gray-200 p-4">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        {/* Action type dropdown */}
        <div>
          <label
            htmlFor="filter-action"
            className="block text-xs font-medium text-gray-500 mb-1"
          >
            Action
          </label>
          <select
            id="filter-action"
            value={filters.action || ""}
            onChange={(e) =>
              onFilterChange({ action: e.target.value || undefined })
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All actions</option>
            {ACTION_TYPES.map((action) => (
              <option key={action} value={action}>
                {action}
              </option>
            ))}
          </select>
        </div>

        {/* From date */}
        <div>
          <label
            htmlFor="filter-from"
            className="block text-xs font-medium text-gray-500 mb-1"
          >
            From
          </label>
          <input
            id="filter-from"
            type="date"
            value={filters.from || ""}
            onChange={(e) =>
              onFilterChange({ from: e.target.value || undefined })
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          />
        </div>

        {/* To date */}
        <div>
          <label
            htmlFor="filter-to"
            className="block text-xs font-medium text-gray-500 mb-1"
          >
            To
          </label>
          <input
            id="filter-to"
            type="date"
            value={filters.to || ""}
            onChange={(e) =>
              onFilterChange({ to: e.target.value || undefined })
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          />
        </div>

        {/* User dropdown */}
        <div>
          <label
            htmlFor="filter-user"
            className="block text-xs font-medium text-gray-500 mb-1"
          >
            User
          </label>
          <select
            id="filter-user"
            value={filters.user_id || ""}
            onChange={(e) =>
              onFilterChange({ user_id: e.target.value || undefined })
            }
            disabled={usersLoading}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400"
          >
            <option value="">
              {usersLoading ? "Loading..." : "All users"}
            </option>
            {users.map((user) => (
              <option key={user.id} value={user.id}>
                {user.name}
              </option>
            ))}
          </select>
        </div>

        {/* Clear Filters */}
        <div className="flex items-end">
          <button
            type="button"
            onClick={onClear}
            className={`w-full rounded-md px-4 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-600 ${
              hasActiveFilters
                ? "bg-red-50 text-red-700 ring-1 ring-red-200 hover:bg-red-100"
                : "bg-gray-50 text-gray-500 ring-1 ring-gray-200 hover:bg-gray-100"
            }`}
          >
            Clear Filters
          </button>
        </div>
      </div>
    </div>
  );
}
