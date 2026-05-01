import { apiClient } from "@/lib/api";
import type { ApiResponse, PaginatedResponse } from "@/types/api";
import type {
  JobPosting,
  JobPostingListItem,
  PublicJobListItem,
  PublicJobDetail,
  PipelineStage,
  StageTransition,
  EmployerJobApplication,
  CreateJobPostingPayload,
  UpdateJobPostingPayload,
  JobStatus,
} from "@/types/job";

const BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8080/api/v1";

// ---------------------------------------------------------------------------
// Public endpoints (no auth required)
// ---------------------------------------------------------------------------

/**
 * Fetch public job listings with optional search, filters, sorting, and pagination.
 */
export async function fetchPublicJobs(params?: {
  page?: number;
  per_page?: number;
  q?: string;
  employment_type?: string;
  remote_status?: string;
  sort?: string;
  direction?: string;
}): Promise<PaginatedResponse<PublicJobListItem>> {
  const query = new URLSearchParams();
  if (params?.page) query.set("page", String(params.page));
  if (params?.per_page) query.set("per_page", String(params.per_page));
  if (params?.q) query.set("q", params.q);
  if (params?.employment_type) query.set("employment_type", params.employment_type);
  if (params?.remote_status) query.set("remote_status", params.remote_status);
  if (params?.sort) query.set("sort", params.sort);
  if (params?.direction) query.set("direction", params.direction);

  const qs = query.toString();
  const url = `${BASE_URL}/public/jobs${qs ? `?${qs}` : ""}`;
  const response = await fetch(url, {
    method: "GET",
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    throw new Error("Failed to fetch public jobs");
  }

  return (await response.json()) as PaginatedResponse<PublicJobListItem>;
}

/**
 * Fetch a single public job by slug.
 */
export async function fetchPublicJobBySlug(
  slug: string
): Promise<ApiResponse<PublicJobDetail>> {
  const url = `${BASE_URL}/public/jobs/${encodeURIComponent(slug)}`;
  const response = await fetch(url, {
    method: "GET",
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    throw new Error("Job not found");
  }

  return (await response.json()) as ApiResponse<PublicJobDetail>;
}

// ---------------------------------------------------------------------------
// Employer endpoints (auth required — uses apiClient with Bearer token)
// ---------------------------------------------------------------------------

/**
 * Fetch tenant job postings for the employer dashboard.
 */
export async function fetchTenantJobs(params?: {
  page?: number;
  per_page?: number;
  status?: string;
  sort?: string;
  direction?: string;
}): Promise<PaginatedResponse<JobPostingListItem>> {
  const query = new URLSearchParams();
  if (params?.page) query.set("page", String(params.page));
  if (params?.per_page) query.set("per_page", String(params.per_page));
  if (params?.status) query.set("status", params.status);
  if (params?.sort) query.set("sort", params.sort);
  if (params?.direction) query.set("direction", params.direction);

  const qs = query.toString();
  const path = `/jobs${qs ? `?${qs}` : ""}`;
  const response = await apiClient.get<JobPostingListItem[]>(path);
  // The backend returns a paginated response, cast accordingly
  return response as unknown as PaginatedResponse<JobPostingListItem>;
}

/**
 * Create a new job posting.
 */
export async function createJobPosting(
  data: CreateJobPostingPayload
): Promise<ApiResponse<JobPosting>> {
  return apiClient.post<JobPosting>(
    "/jobs",
    data as unknown as Record<string, unknown>
  );
}

/**
 * Update an existing job posting.
 */
export async function updateJobPosting(
  id: string,
  data: UpdateJobPostingPayload
): Promise<ApiResponse<JobPosting>> {
  return apiClient.put<JobPosting>(
    `/jobs/${id}`,
    data as unknown as Record<string, unknown>
  );
}

/**
 * Delete a draft job posting.
 */
export async function deleteJobPosting(
  id: string
): Promise<ApiResponse<void>> {
  return apiClient.del<void>(`/jobs/${id}`);
}

/**
 * Transition a job posting's status.
 */
export async function transitionJobStatus(
  id: string,
  status: JobStatus
): Promise<ApiResponse<JobPosting>> {
  return apiClient.patch<JobPosting>(`/jobs/${id}/status`, { status });
}

/**
 * Fetch a single job posting detail (employer view).
 */
export async function fetchJobDetail(
  id: string
): Promise<ApiResponse<JobPosting>> {
  return apiClient.get<JobPosting>(`/jobs/${id}`);
}

/**
 * Fetch pipeline stages for a job posting.
 */
export async function fetchPipelineStages(
  jobId: string
): Promise<ApiResponse<PipelineStage[]>> {
  return apiClient.get<PipelineStage[]>(`/jobs/${jobId}/stages`);
}

/**
 * Move an application to a different pipeline stage.
 */
export async function moveApplication(
  appId: string,
  stageId: string
): Promise<ApiResponse<StageTransition>> {
  return apiClient.post<StageTransition>(`/applications/${appId}/move`, {
    stage_id: stageId,
  });
}

/**
 * Fetch applications for a job posting (employer view).
 */
export async function fetchJobApplications(
  jobId: string,
  params?: { page?: number; per_page?: number }
): Promise<PaginatedResponse<EmployerJobApplication>> {
  const query = new URLSearchParams();
  if (params?.page) query.set("page", String(params.page));
  if (params?.per_page) query.set("per_page", String(params.per_page));

  const qs = query.toString();
  const path = `/jobs/${jobId}/applications${qs ? `?${qs}` : ""}`;
  const response = await apiClient.get<EmployerJobApplication[]>(path);
  return response as unknown as PaginatedResponse<EmployerJobApplication>;
}

/**
 * Fetch transition history for an application.
 */
export async function fetchTransitionHistory(
  appId: string
): Promise<ApiResponse<StageTransition[]>> {
  return apiClient.get<StageTransition[]>(
    `/applications/${appId}/transitions`
  );
}
