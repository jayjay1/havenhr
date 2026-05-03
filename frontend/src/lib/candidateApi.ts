import type { ApiError, ApiResponse } from "@/types/api";
import { ApiRequestError } from "@/lib/api";
import type { ApplicationListItem, ApplicationDetail } from "@/types/candidate";

const BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8080/api/v1";

const CANDIDATE_TOKEN_KEY = "havenhr_candidate_token";

/**
 * Get the stored candidate access token.
 */
export function getCandidateAccessToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(CANDIDATE_TOKEN_KEY);
}

/**
 * Store the candidate access token.
 */
export function setCandidateAccessToken(token: string): void {
  if (typeof window === "undefined") return;
  localStorage.setItem(CANDIDATE_TOKEN_KEY, token);
}

/**
 * Clear the stored candidate access token.
 */
export function clearCandidateAccessToken(): void {
  if (typeof window === "undefined") return;
  localStorage.removeItem(CANDIDATE_TOKEN_KEY);
}

/**
 * Build authorization headers using the candidate token.
 */
function authHeaders(): Record<string, string> {
  const token = getCandidateAccessToken();
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
 * Typed API client for candidate endpoints.
 * Uses a separate token key (havenhr_candidate_token) from the employer client.
 */
export const candidateApiClient = {
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


// ---------------------------------------------------------------------------
// Application API helpers
// ---------------------------------------------------------------------------

export interface FetchApplicationsParams {
  status?: string;
  sort_by?: string;
  sort_dir?: string;
}

/**
 * Fetch the authenticated candidate's applications with optional filters.
 */
export async function fetchApplications(
  params?: FetchApplicationsParams
): Promise<ApiResponse<ApplicationListItem[]>> {
  const query = new URLSearchParams();
  if (params?.status) query.set("status", params.status);
  if (params?.sort_by) query.set("sort_by", params.sort_by);
  if (params?.sort_dir) query.set("sort_dir", params.sort_dir);
  const qs = query.toString();
  const path = `/candidate/applications${qs ? `?${qs}` : ""}`;
  return candidateApiClient.get<ApplicationListItem[]>(path);
}

/**
 * Fetch a single application detail by ID.
 */
export async function fetchApplicationDetail(
  id: string
): Promise<ApiResponse<ApplicationDetail>> {
  return candidateApiClient.get<ApplicationDetail>(
    `/candidate/applications/${id}`
  );
}

// ---------------------------------------------------------------------------
// Interview API helpers
// ---------------------------------------------------------------------------

import type { CandidateInterview } from "@/types/interview";

/**
 * Fetch the authenticated candidate's interviews.
 */
export async function fetchCandidateInterviews(): Promise<
  ApiResponse<CandidateInterview[]>
> {
  return candidateApiClient.get<CandidateInterview[]>("/candidate/interviews");
}
