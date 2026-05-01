import type { ApiError, ApiResponse } from "@/types/api";

const BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8080/api/v1";

const TOKEN_KEY = "havenhr_access_token";

/**
 * Custom error class for API errors that includes the parsed error response.
 */
export class ApiRequestError extends Error {
  public status: number;
  public code: string;
  public details: Record<string, unknown> | undefined;

  constructor(status: number, apiError: ApiError) {
    super(apiError.error.message);
    this.name = "ApiRequestError";
    this.status = status;
    this.code = apiError.error.code;
    this.details = apiError.error.details;
  }
}

/**
 * Get the stored access token.
 */
export function getAccessToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(TOKEN_KEY);
}

/**
 * Store the access token.
 */
export function setAccessToken(token: string): void {
  if (typeof window === "undefined") return;
  localStorage.setItem(TOKEN_KEY, token);
}

/**
 * Clear the stored access token.
 */
export function clearAccessToken(): void {
  if (typeof window === "undefined") return;
  localStorage.removeItem(TOKEN_KEY);
}

/**
 * Build authorization headers if a token is available.
 */
function authHeaders(): Record<string, string> {
  const token = getAccessToken();
  if (token) {
    return { Authorization: `Bearer ${token}` };
  }
  return {};
}

/**
 * Parse the response body and throw an ApiRequestError if the response is not ok.
 */
async function handleResponse<T>(response: Response): Promise<ApiResponse<T>> {
  if (!response.ok) {
    let apiError: ApiError;
    try {
      apiError = (await response.json()) as ApiError;
    } catch {
      apiError = {
        error: {
          code: "UNKNOWN_ERROR",
          message: response.statusText || "An unexpected error occurred.",
        },
      };
    }
    throw new ApiRequestError(response.status, apiError);
  }

  return (await response.json()) as ApiResponse<T>;
}

/**
 * Build a full URL from a path, prepending the base URL.
 */
function buildUrl(path: string): string {
  const cleanPath = path.startsWith("/") ? path : `/${path}`;
  return `${BASE_URL}${cleanPath}`;
}

/**
 * Typed API client for HavenHR backend.
 *
 * Uses Bearer token authentication via Authorization header.
 * Token is stored in localStorage after login.
 */
export const apiClient = {
  async get<T>(path: string): Promise<ApiResponse<T>> {
    const response = await fetch(buildUrl(path), {
      method: "GET",
      headers: {
        Accept: "application/json",
        ...authHeaders(),
      },
    });
    return handleResponse<T>(response);
  },

  async post<T>(
    path: string,
    body?: Record<string, unknown>
  ): Promise<ApiResponse<T>> {
    const response = await fetch(buildUrl(path), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...authHeaders(),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    return handleResponse<T>(response);
  },

  async put<T>(
    path: string,
    body?: Record<string, unknown>
  ): Promise<ApiResponse<T>> {
    const response = await fetch(buildUrl(path), {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...authHeaders(),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    return handleResponse<T>(response);
  },

  async patch<T>(
    path: string,
    body?: Record<string, unknown>
  ): Promise<ApiResponse<T>> {
    const response = await fetch(buildUrl(path), {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...authHeaders(),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    return handleResponse<T>(response);
  },

  async del<T>(path: string): Promise<ApiResponse<T>> {
    const response = await fetch(buildUrl(path), {
      method: "DELETE",
      headers: {
        Accept: "application/json",
        ...authHeaders(),
      },
    });
    return handleResponse<T>(response);
  },
};
