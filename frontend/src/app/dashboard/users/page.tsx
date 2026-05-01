"use client";

import { useState, useEffect, useCallback } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { apiClient, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/contexts/AuthContext";
import { InviteUserModal } from "@/components/dashboard/InviteUserModal";
import type { User } from "@/types/user";
import type { PaginatedResponse } from "@/types/api";

/**
 * Format a date string for display. Returns "Never" for null values.
 */
function formatDate(dateStr: string | null): string {
  if (!dateStr) return "Never";
  const date = new Date(dateStr);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

/**
 * Status badge component for active/inactive users.
 */
function StatusBadge({ isActive }: { isActive: boolean }) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
        isActive
          ? "bg-green-100 text-green-800"
          : "bg-red-100 text-red-800"
      }`}
    >
      {isActive ? "Active" : "Inactive"}
    </span>
  );
}

/**
 * Role badge component.
 */
function RoleBadge({ role }: { role: string }) {
  const displayRole = role.replace("_", " ");
  return (
    <span className="inline-flex items-center rounded-full bg-blue-100 text-blue-800 px-2.5 py-0.5 text-xs font-medium capitalize">
      {displayRole}
    </span>
  );
}

/**
 * Pagination controls component.
 */
function Pagination({
  currentPage,
  lastPage,
  total,
  perPage,
  onPageChange,
}: {
  currentPage: number;
  lastPage: number;
  total: number;
  perPage: number;
  onPageChange: (page: number) => void;
}) {
  const start = (currentPage - 1) * perPage + 1;
  const end = Math.min(currentPage * perPage, total);

  return (
    <nav aria-label="Pagination" className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
      <div className="hidden sm:block">
        <p className="text-sm text-gray-700">
          Showing <span className="font-medium">{start}</span> to{" "}
          <span className="font-medium">{end}</span> of{" "}
          <span className="font-medium">{total}</span> users
        </p>
      </div>
      <div className="flex flex-1 justify-between sm:justify-end gap-2">
        <button
          type="button"
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage <= 1}
          aria-label="Previous page"
          className="relative inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Previous
        </button>
        <button
          type="button"
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage >= lastPage}
          aria-label="Next page"
          className="relative inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Next
        </button>
      </div>
    </nav>
  );
}

/**
 * Mobile card view for a single user.
 */
function UserCard({ user }: { user: User }) {
  return (
    <div className="bg-white rounded-lg border border-gray-200 p-4 space-y-2">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-medium text-gray-900">{user.name}</h3>
        <StatusBadge isActive={user.is_active} />
      </div>
      <p className="text-sm text-gray-500">{user.email}</p>
      <div className="flex items-center justify-between">
        <RoleBadge role={user.role} />
        <span className="text-xs text-gray-400">
          Last login: {formatDate(user.last_login_at)}
        </span>
      </div>
    </div>
  );
}

export default function UsersPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { hasPermission } = useAuth();

  const page = Number(searchParams.get("page")) || 1;
  const perPage = Number(searchParams.get("per_page")) || 20;

  const [users, setUsers] = useState<User[]>([]);
  const [meta, setMeta] = useState<PaginatedResponse<User>["meta"] | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [inviteModalOpen, setInviteModalOpen] = useState(false);

  const canCreateUsers = hasPermission("users.create");

  const fetchUsers = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await apiClient.get<PaginatedResponse<User>>(
        `/users?page=${page}&per_page=${perPage}`
      );
      const paginated = response.data;
      if (paginated && Array.isArray(paginated.data)) {
        setUsers(paginated.data);
        setMeta(paginated.meta);
      } else if (Array.isArray(paginated)) {
        setUsers(paginated as unknown as User[]);
      } else {
        setUsers([]);
      }
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message || "Failed to load users.");
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setLoading(false);
    }
  }, [page, perPage]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  // Open modal when URL has ?action=invite
  useEffect(() => {
    if (searchParams.get("action") === "invite" && canCreateUsers) {
      setInviteModalOpen(true);
    }
  }, [searchParams, canCreateUsers]);

  function handlePageChange(newPage: number) {
    const params = new URLSearchParams();
    params.set("page", String(newPage));
    params.set("per_page", String(perPage));
    router.push(`/dashboard/users?${params.toString()}`);
  }

  function handleInviteSuccess() {
    fetchUsers();
  }

  function handleInviteClose() {
    setInviteModalOpen(false);
    // Remove action=invite from URL if present
    if (searchParams.get("action") === "invite") {
      const params = new URLSearchParams(searchParams.toString());
      params.delete("action");
      const qs = params.toString();
      router.push(`/dashboard/users${qs ? `?${qs}` : ""}`);
    }
  }

  const canManageRoles = hasPermission("manage_roles");

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Users</h1>
          <p className="mt-1 text-sm text-gray-500">
            Manage users in your organization.
          </p>
        </div>
        {canCreateUsers && (
          <button
            type="button"
            onClick={() => setInviteModalOpen(true)}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600"
          >
            Invite User
          </button>
        )}
      </div>

      {canCreateUsers && (
        <InviteUserModal
          isOpen={inviteModalOpen}
          onClose={handleInviteClose}
          onSuccess={handleInviteSuccess}
        />
      )}

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
          {error}
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div
            className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-r-transparent"
            role="status"
            aria-label="Loading users"
          />
        </div>
      ) : !users || users.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-sm text-gray-500">No users found.</p>
        </div>
      ) : (
        <>
          {/* Desktop table view */}
          <div className="hidden md:block overflow-hidden rounded-lg border border-gray-200 bg-white">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Name
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Email
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Role
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Last Login
                  </th>
                  {canManageRoles && (
                    <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  )}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {users.map((user) => (
                  <tr key={user.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {user.name}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {user.email}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <RoleBadge role={user.role} />
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <StatusBadge isActive={user.is_active} />
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {formatDate(user.last_login_at)}
                    </td>
                    {canManageRoles && (
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <a
                          href={`/dashboard/users/${user.id}/roles`}
                          className="text-blue-600 hover:text-blue-800 font-medium focus:outline-none focus:underline"
                        >
                          Manage Role
                        </a>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile card view */}
          <div className="md:hidden space-y-3">
            {users.map((user) => (
              <div key={user.id}>
                <UserCard user={user} />
                {canManageRoles && (
                  <div className="mt-2 text-right">
                    <a
                      href={`/dashboard/users/${user.id}/roles`}
                      className="text-sm text-blue-600 hover:text-blue-800 font-medium focus:outline-none focus:underline"
                    >
                      Manage Role
                    </a>
                  </div>
                )}
              </div>
            ))}
          </div>

          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <div className="mt-4">
              <Pagination
                currentPage={meta.current_page}
                lastPage={meta.last_page}
                total={meta.total}
                perPage={meta.per_page}
                onPageChange={handlePageChange}
              />
            </div>
          )}
        </>
      )}
    </div>
  );
}
