"use client";

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  type ReactNode,
} from "react";
import { useRouter } from "next/navigation";
import { apiClient, ApiRequestError, getAccessToken, clearAccessToken } from "@/lib/api";
import type { User, UserRole } from "@/types/user";
import type { PermissionName } from "@/types/permission";

/**
 * Auth context value provided to consumers.
 */
export interface AuthContextValue {
  /** Current authenticated user, null while loading or if unauthenticated */
  user: User | null;
  /** Current user's role */
  role: UserRole | null;
  /** Current user's permissions */
  permissions: PermissionName[];
  /** Whether the user is authenticated */
  isAuthenticated: boolean;
  /** Whether auth state is still loading */
  isLoading: boolean;
  /** Log out the current user */
  logout: () => Promise<void>;
  /** Check if the user has a specific permission */
  hasPermission: (permission: PermissionName) => boolean;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

/**
 * Response shape from GET /auth/me endpoint.
 */
interface MeResponse {
  id: string;
  tenant_id: string;
  name: string;
  email: string;
  is_active: boolean;
  role: UserRole;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
  permissions: PermissionName[];
}

/**
 * Maps each role to its set of permissions.
 * Used as a fallback when the API doesn't return permissions.
 */
const ROLE_PERMISSIONS: Record<UserRole, PermissionName[]> = {
  owner: [
    "users.create",
    "users.view",
    "users.list",
    "users.update",
    "users.delete",
    "roles.list",
    "roles.view",
    "manage_roles",
    "audit_logs.view",
    "tenant.update",
    "tenant.delete",
    "jobs.create",
    "jobs.view",
    "jobs.list",
    "jobs.update",
    "jobs.delete",
    "candidates.create",
    "candidates.view",
    "candidates.list",
    "candidates.update",
    "candidates.delete",
    "pipeline.manage",
    "reports.view",
    "owner.assign",
  ],
  admin: [
    "users.create",
    "users.view",
    "users.list",
    "users.update",
    "users.delete",
    "roles.list",
    "roles.view",
    "manage_roles",
    "audit_logs.view",
    "tenant.update",
    "jobs.create",
    "jobs.view",
    "jobs.list",
    "jobs.update",
    "jobs.delete",
    "candidates.create",
    "candidates.view",
    "candidates.list",
    "candidates.update",
    "candidates.delete",
    "pipeline.manage",
    "reports.view",
  ],
  recruiter: [
    "jobs.create",
    "jobs.view",
    "jobs.list",
    "jobs.update",
    "jobs.delete",
    "candidates.create",
    "candidates.view",
    "candidates.list",
    "candidates.update",
    "candidates.delete",
    "pipeline.manage",
  ],
  hiring_manager: [
    "jobs.view",
    "jobs.list",
    "candidates.view",
    "candidates.list",
    "reports.view",
  ],
  viewer: [
    "jobs.view",
    "jobs.list",
    "candidates.view",
    "candidates.list",
    "reports.view",
  ],
};

/**
 * Get permissions for a role, using the static mapping as fallback.
 */
export function getPermissionsForRole(role: UserRole): PermissionName[] {
  return ROLE_PERMISSIONS[role] ?? [];
}

/**
 * AuthProvider wraps the dashboard layout and provides auth state.
 * Fetches user info from /auth/me on mount.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [permissions, setPermissions] = useState<PermissionName[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    async function fetchUser() {
      // Check if we have a token at all
      const token = getAccessToken();
      if (!token) {
        if (!cancelled) {
          setIsLoading(false);
        }
        router.push("/login");
        return;
      }

      try {
        const response = await apiClient.get<MeResponse>("/auth/me");
        if (cancelled) return;

        const me = response.data;
        setUser({
          id: me.id,
          tenant_id: me.tenant_id,
          name: me.name,
          email: me.email,
          is_active: me.is_active,
          role: me.role,
          last_login_at: me.last_login_at,
          created_at: me.created_at,
          updated_at: me.updated_at,
        });
        setPermissions(
          me.permissions?.length ? me.permissions : getPermissionsForRole(me.role)
        );
      } catch (err) {
        if (cancelled) return;
        if (err instanceof ApiRequestError && err.status === 401) {
          clearAccessToken();
          router.push("/login");
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }

    fetchUser();
    return () => {
      cancelled = true;
    };
  }, [router]);

  const logout = useCallback(async () => {
    try {
      await apiClient.post("/auth/logout");
    } catch {
      // Proceed with client-side cleanup even if API call fails
    }
    clearAccessToken();
    setUser(null);
    setPermissions([]);
    router.push("/login");
  }, [router]);

  const hasPermission = useCallback(
    (permission: PermissionName) => permissions.includes(permission),
    [permissions]
  );

  const value: AuthContextValue = {
    user,
    role: user?.role ?? null,
    permissions,
    isAuthenticated: !!user,
    isLoading,
    logout,
    hasPermission,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

/**
 * Hook to access auth context. Must be used within an AuthProvider.
 */
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
