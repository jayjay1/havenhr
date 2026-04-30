"use client";

import { useState, useEffect, useCallback } from "react";
import { apiClient, ApiRequestError } from "@/lib/api";
import type { Role } from "@/types/permission";

function formatRoleName(name: string): string {
  return name
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

interface RolesApiResponse {
  data: Role[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export default function RolesPage() {
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const fetchRoles = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await apiClient.get<RolesApiResponse>("/roles");
      // response.data is { data: Role[], meta: {...} }
      const rolesData = response.data;
      if (Array.isArray(rolesData)) {
        // Direct array response
        setRoles(rolesData);
      } else if (rolesData && Array.isArray(rolesData.data)) {
        // Paginated response
        setRoles(rolesData.data);
      } else {
        setRoles([]);
      }
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message || "Failed to load roles.");
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchRoles();
  }, [fetchRoles]);

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Roles</h1>
        <p className="mt-1 text-sm text-gray-500">
          View the roles configured for your organization.
        </p>
      </div>

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
            aria-label="Loading roles"
          />
        </div>
      ) : !roles || roles.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-sm text-gray-500">No roles found.</p>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {roles.map((role) => (
            <div
              key={role.id}
              className="bg-white rounded-lg border border-gray-200 p-5"
            >
              <div className="flex items-center justify-between mb-2">
                <h2 className="text-lg font-semibold text-gray-900">
                  {formatRoleName(role.name)}
                </h2>
                {role.is_system_default && (
                  <span className="inline-flex items-center rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 text-xs font-medium">
                    System
                  </span>
                )}
              </div>
              <p className="text-sm text-gray-500">
                {role.description || "No description"}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
