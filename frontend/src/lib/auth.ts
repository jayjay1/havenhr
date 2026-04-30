import { apiClient, ApiRequestError } from "@/lib/api";
import type { ApiResponse } from "@/types/api";

/**
 * Flag to prevent concurrent refresh attempts (infinite loop protection).
 * Only one refresh request can be in-flight at a time.
 */
let isRefreshing = false;

/**
 * Attempt to refresh the access token by calling POST /auth/refresh.
 * Returns true if the refresh succeeded, false otherwise.
 *
 * The backend sets new HTTP-only cookies automatically on success,
 * so we just need to make the request with credentials included.
 */
export async function refreshAccessToken(): Promise<boolean> {
  if (isRefreshing) {
    return false;
  }

  isRefreshing = true;
  try {
    await apiClient.post("/auth/refresh");
    return true;
  } catch {
    return false;
  } finally {
    isRefreshing = false;
  }
}

/**
 * Execute an API request with automatic token refresh on 401.
 *
 * If the request fails with a 401, this function will:
 * 1. Attempt to refresh the access token
 * 2. If refresh succeeds: retry the original request once
 * 3. If refresh fails: redirect to /login
 *
 * This prevents infinite refresh loops by only retrying once.
 *
 * @param requestFn - A function that performs the API request
 * @returns The API response
 */
export async function withTokenRefresh<T>(
  requestFn: () => Promise<ApiResponse<T>>
): Promise<ApiResponse<T>> {
  try {
    return await requestFn();
  } catch (error) {
    if (error instanceof ApiRequestError && error.status === 401) {
      const refreshed = await refreshAccessToken();

      if (refreshed) {
        // Retry the original request once
        return await requestFn();
      }

      // Refresh failed — redirect to login
      if (typeof window !== "undefined") {
        window.location.href = "/login";
      }
    }

    throw error;
  }
}

/**
 * Log out the current user.
 *
 * 1. Calls POST /auth/logout to invalidate tokens server-side
 * 2. Redirects to /login
 *
 * If the API call fails, we still redirect to /login since the
 * client-side state should be cleared regardless.
 */
export async function logout(): Promise<void> {
  try {
    await apiClient.post("/auth/logout");
  } catch {
    // Proceed with redirect even if API call fails
  }

  if (typeof window !== "undefined") {
    window.location.href = "/login";
  }
}
