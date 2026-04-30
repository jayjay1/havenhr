/**
 * User roles available in the system.
 */
export type UserRole =
  | "owner"
  | "admin"
  | "recruiter"
  | "hiring_manager"
  | "viewer";

/**
 * User entity as returned by the API.
 */
export interface User {
  id: string;
  tenant_id: string;
  name: string;
  email: string;
  is_active: boolean;
  role: UserRole;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
}
