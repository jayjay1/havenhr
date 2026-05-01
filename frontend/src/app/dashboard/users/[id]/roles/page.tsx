"use client";

import { useState, useEffect, useCallback } from "react";
import { useParams, useRouter } from "next/navigation";
import { apiClient, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/Button";
import type { User, UserRole } from "@/types/user";
import type { Role } from "@/types/permission";

/**
 * All system roles in hierarchy order.
 */
const ALL_ROLES: UserRole[] = [
  "owner",
  "admin",
  "recruiter",
  "hiring_manager",
  "viewer",
];

/**
 * Get the roles that the requesting user can assign based on their own role.
 * Owner can assign all roles. Admin can assign all except Owner.
 */
function getAssignableRoles(requestingUserRole: UserRole): UserRole[] {
  if (requestingUserRole === "owner") {
    return ALL_ROLES;
  }
  if (requestingUserRole === "admin") {
    return ALL_ROLES.filter((r) => r !== "owner");
  }
  return [];
}

/**
 * Format a role name for display.
 */
function formatRoleName(role: string): string {
  return role
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

export default function UserRolesPage() {
  const params = useParams();
  const router = useRouter();
  const { role: currentUserRole } = useAuth();
  const userId = params.id as string;

  const [targetUser, setTargetUser] = useState<User | null>(null);
  const [roles, setRoles] = useState<Role[]>([]);
  const [selectedRole, setSelectedRole] = useState<string>("");
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const assignableRoleNames = currentUserRole
    ? getAssignableRoles(currentUserRole)
    : [];

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [userResponse, rolesResponse] = await Promise.all([
        apiClient.get<User>(`/users/${userId}`),
        apiClient.get<Role[]>("/roles"),
      ]);
      setTargetUser(userResponse.data);
      setRoles(rolesResponse.data);
      setSelectedRole(userResponse.data.role);
    } catch (err) {
      if (err instanceof ApiRequestError) {
        if (err.status === 404) {
          setError("User not found.");
        } else if (err.status === 403) {
          setError("You do not have permission to manage roles.");
        } else {
          setError(err.message || "Failed to load user data.");
        }
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setLoading(false);
    }
  }, [userId]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Filter available roles to only those the current user can assign
  const availableRoles = roles.filter((role) =>
    assignableRoleNames.includes(role.name as UserRole)
  );

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!selectedRole || !targetUser) return;

    setSubmitting(true);
    setError("");
    setSuccess("");

    try {
      const roleToAssign = roles.find((r) => r.name === selectedRole);
      if (!roleToAssign) {
        setError("Selected role not found.");
        return;
      }

      await apiClient.put(`/users/${userId}/roles`, {
        role_id: roleToAssign.id,
      });

      setSuccess(
        `Role updated to ${formatRoleName(selectedRole)} for ${targetUser.name}.`
      );
      // Update local state to reflect the change
      setTargetUser((prev) =>
        prev ? { ...prev, role: selectedRole as UserRole } : prev
      );
    } catch (err) {
      if (err instanceof ApiRequestError) {
        if (err.status === 403) {
          setError("You do not have permission to assign this role.");
        } else {
          setError(err.message || "Failed to update role.");
        }
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div
          className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-r-transparent"
          role="status"
          aria-label="Loading user role data"
        />
      </div>
    );
  }

  if (error && !targetUser) {
    return (
      <div>
        <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          {error}
        </div>
        <div className="mt-4">
          <Button variant="secondary" onClick={() => router.push("/dashboard/users")}>
            Back to Users
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl">
      <div className="mb-6">
        <button
          type="button"
          onClick={() => router.push("/dashboard/users")}
          className="text-sm text-gray-500 hover:text-gray-700 focus:outline-none focus:underline mb-2 inline-flex items-center gap-1"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
          </svg>
          Back to Users
        </button>
        <h1 className="text-2xl font-bold text-gray-900">Manage Role</h1>
        {targetUser && (
          <p className="mt-1 text-sm text-gray-500">
            Change the role for {targetUser.name} ({targetUser.email})
          </p>
        )}
      </div>

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
          {error}
        </div>
      )}

      {success && (
        <div role="status" className="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">
          {success}
        </div>
      )}

      {targetUser && (
        <div className="bg-white rounded-lg border border-gray-200 p-6">
          <div className="mb-6">
            <h2 className="text-sm font-medium text-gray-700 mb-1">
              Current Role
            </h2>
            <p className="text-sm text-gray-900">
              <span className="inline-flex items-center rounded-full bg-blue-100 text-blue-800 px-2.5 py-0.5 text-xs font-medium capitalize">
                {formatRoleName(targetUser.role)}
              </span>
            </p>
          </div>

          <form onSubmit={handleSubmit}>
            <fieldset>
              <legend className="text-sm font-medium text-gray-700 mb-3">
                Select new role
              </legend>
              <div className="space-y-3">
                {availableRoles.length === 0 ? (
                  <p className="text-sm text-gray-500">
                    No roles available to assign.
                  </p>
                ) : (
                  availableRoles.map((role) => (
                    <label
                      key={role.id}
                      className={`
                        flex items-start gap-3 rounded-lg border p-4 cursor-pointer
                        transition-colors
                        ${
                          selectedRole === role.name
                            ? "border-blue-600 bg-blue-50 ring-1 ring-blue-600"
                            : "border-gray-200 hover:border-gray-300"
                        }
                      `}
                    >
                      <input
                        type="radio"
                        name="role"
                        value={role.name}
                        checked={selectedRole === role.name}
                        onChange={(e) => setSelectedRole(e.target.value)}
                        className="mt-0.5 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-600"
                      />
                      <div>
                        <span className="text-sm font-medium text-gray-900">
                          {formatRoleName(role.name)}
                        </span>
                        {role.description && (
                          <p className="text-xs text-gray-500 mt-0.5">
                            {role.description}
                          </p>
                        )}
                      </div>
                    </label>
                  ))
                )}
              </div>
            </fieldset>

            <div className="mt-6 flex flex-col sm:flex-row gap-3">
              <Button
                type="submit"
                loading={submitting}
                disabled={
                  !selectedRole ||
                  selectedRole === targetUser.role ||
                  availableRoles.length === 0
                }
              >
                Update Role
              </Button>
              <Button
                type="button"
                variant="secondary"
                onClick={() => router.push("/dashboard/users")}
              >
                Cancel
              </Button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
