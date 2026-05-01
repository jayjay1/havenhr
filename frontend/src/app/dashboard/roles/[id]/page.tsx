"use client";

import { useState, useEffect, useCallback } from "react";
import { useParams } from "next/navigation";
import { apiClient, ApiRequestError } from "@/lib/api";
import type { Role } from "@/types/permission";

interface Permission {
  id: string;
  name: string;
  resource: string;
  action: string;
  description: string;
}

interface RoleWithPermissions extends Role {
  permissions: Permission[];
}

function formatRoleName(name: string): string {
  return name
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

function formatResourceName(resource: string): string {
  return resource
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

function groupPermissionsByResource(
  permissions: Permission[]
): Record<string, Permission[]> {
  const groups: Record<string, Permission[]> = {};
  for (const perm of permissions) {
    const key = perm.resource;
    if (!groups[key]) {
      groups[key] = [];
    }
    groups[key].push(perm);
  }
  return groups;
}

export default function RoleDetailPage() {
  const params = useParams();
  const roleId = params.id as string;

  const [role, setRole] = useState<RoleWithPermissions | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notFound, setNotFound] = useState(false);

  const fetchRole = useCallback(async () => {
    setLoading(true);
    setError("");
    setNotFound(false);
    try {
      const response = await apiClient.get<RoleWithPermissions>(
        `/roles/${roleId}`
      );
      setRole(response.data);
    } catch (err) {
      if (err instanceof ApiRequestError) {
        if (err.status === 404) {
          setNotFound(true);
        } else {
          setError(err.message || "Failed to load role.");
        }
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setLoading(false);
    }
  }, [roleId]);

  useEffect(() => {
    fetchRole();
  }, [fetchRole]);

  if (loading) {
    return (
      <div>
        <div className="mb-6">
          <a
            href="/dashboard/roles"
            className="text-sm text-blue-600 hover:text-blue-800 font-medium"
          >
            ← Back to Roles
          </a>
        </div>
        <div className="flex items-center justify-center py-12">
          <div
            className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-r-transparent"
            role="status"
            aria-label="Loading role details"
          />
        </div>
      </div>
    );
  }

  if (notFound) {
    return (
      <div>
        <div className="mb-6">
          <a
            href="/dashboard/roles"
            className="text-sm text-blue-600 hover:text-blue-800 font-medium"
          >
            ← Back to Roles
          </a>
        </div>
        <div className="text-center py-12">
          <h2 className="text-lg font-semibold text-gray-900">
            Role not found
          </h2>
          <p className="mt-1 text-sm text-gray-500">
            The role you are looking for does not exist or has been removed.
          </p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div>
        <div className="mb-6">
          <a
            href="/dashboard/roles"
            className="text-sm text-blue-600 hover:text-blue-800 font-medium"
          >
            ← Back to Roles
          </a>
        </div>
        <div
          role="alert"
          className="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700"
        >
          {error}
        </div>
      </div>
    );
  }

  if (!role) return null;

  const permissionGroups = groupPermissionsByResource(role.permissions || []);
  const groupKeys = Object.keys(permissionGroups).sort();

  return (
    <div>
      <div className="mb-6">
        <a
          href="/dashboard/roles"
          className="text-sm text-blue-600 hover:text-blue-800 font-medium"
        >
          ← Back to Roles
        </a>
      </div>

      <div className="mb-6">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold text-gray-900">
            {formatRoleName(role.name)}
          </h1>
          {role.is_system_default && (
            <span className="inline-flex items-center rounded-full bg-gray-100 text-gray-600 px-2.5 py-0.5 text-xs font-medium">
              System Role
            </span>
          )}
        </div>
        <p className="mt-1 text-sm text-gray-500">
          {role.description || "No description"}
        </p>
      </div>

      {groupKeys.length === 0 ? (
        <div className="text-center py-8">
          <p className="text-sm text-gray-500">
            This role has no permissions assigned.
          </p>
        </div>
      ) : (
        <div className="space-y-6">
          {groupKeys.map((resource) => (
            <div
              key={resource}
              className="bg-white rounded-lg border border-gray-200 overflow-hidden"
            >
              <div className="bg-gray-50 px-5 py-3 border-b border-gray-200">
                <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                  {formatResourceName(resource)}
                </h2>
              </div>
              <ul className="divide-y divide-gray-100">
                {permissionGroups[resource].map((perm) => (
                  <li key={perm.id} className="px-5 py-3">
                    <p className="text-sm font-medium text-gray-900">
                      {perm.name}
                    </p>
                    {perm.description && (
                      <p className="text-sm text-gray-500 mt-0.5">
                        {perm.description}
                      </p>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
