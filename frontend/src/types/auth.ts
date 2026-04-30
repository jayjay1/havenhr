import type { Tenant } from "./tenant";
import type { User } from "./user";

/**
 * Response from the tenant registration endpoint.
 */
export interface RegisterResponse {
  tenant: Pick<Tenant, "id" | "name" | "email_domain">;
  user: Pick<User, "id" | "name" | "email" | "role">;
}

/**
 * Response from the login endpoint.
 */
export interface LoginResponse {
  user: User;
}

/**
 * Response from the password reset request endpoint.
 */
export interface ForgotPasswordResponse {
  message: string;
}

/**
 * Response from the password reset confirmation endpoint.
 */
export interface ResetPasswordResponse {
  message: string;
}
