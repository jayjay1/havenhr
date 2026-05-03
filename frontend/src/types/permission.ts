/**
 * System permission names used for RBAC.
 */
export type PermissionName =
  | "users.create"
  | "users.view"
  | "users.list"
  | "users.update"
  | "users.delete"
  | "roles.list"
  | "roles.view"
  | "manage_roles"
  | "audit_logs.view"
  | "tenant.update"
  | "tenant.delete"
  | "jobs.create"
  | "jobs.view"
  | "jobs.list"
  | "jobs.update"
  | "jobs.delete"
  | "candidates.create"
  | "candidates.view"
  | "candidates.list"
  | "candidates.update"
  | "candidates.delete"
  | "applications.view"
  | "applications.manage"
  | "pipeline.manage"
  | "reports.view"
  | "owner.assign";

/**
 * Role entity as returned by the API.
 */
export interface Role {
  id: string;
  tenant_id: string;
  name: string;
  description: string;
  is_system_default: boolean;
  created_at: string;
  updated_at: string;
}
