/**
 * Subscription status for a tenant.
 */
export type SubscriptionStatus = "trial" | "active" | "suspended" | "cancelled";

/**
 * Tenant (company) entity as returned by the API.
 */
export interface Tenant {
  id: string;
  name: string;
  email_domain: string;
  subscription_status: SubscriptionStatus;
  created_at: string;
  updated_at: string;
}
