import { apiClient } from "@/lib/api";
import type { ApiResponse, PaginatedResponse } from "@/types/api";
import type {
  BulkActionResult,
  EmployerJobApplication,
  PipelineStageDetail,
} from "@/types/job";

// ---------------------------------------------------------------------------
// Pipeline-specific API functions
// ---------------------------------------------------------------------------

/**
 * Bulk move multiple applications to a target pipeline stage.
 */
export async function bulkMoveApplications(
  appIds: string[],
  stageId: string
): Promise<ApiResponse<BulkActionResult>> {
  return apiClient.post<BulkActionResult>("/applications/bulk-move", {
    application_ids: appIds,
    stage_id: stageId,
  });
}

/**
 * Bulk reject multiple applications (moves them to the Rejected stage).
 */
export async function bulkRejectApplications(
  appIds: string[]
): Promise<ApiResponse<BulkActionResult>> {
  return apiClient.post<BulkActionResult>("/applications/bulk-reject", {
    application_ids: appIds,
  });
}

/**
 * Update a pipeline stage's name and/or color.
 */
export async function updatePipelineStage(
  jobId: string,
  stageId: string,
  data: { name?: string; color?: string | null }
): Promise<ApiResponse<PipelineStageDetail>> {
  return apiClient.patch<PipelineStageDetail>(
    `/jobs/${jobId}/stages/${stageId}`,
    data as Record<string, unknown>
  );
}

/**
 * Fetch applications for a job posting with search, sort, and pagination.
 */
export async function fetchJobApplicationsWithSearch(
  jobId: string,
  params?: {
    page?: number;
    per_page?: number;
    q?: string;
    sort?: string;
  }
): Promise<PaginatedResponse<EmployerJobApplication>> {
  const query = new URLSearchParams();
  if (params?.page) query.set("page", String(params.page));
  if (params?.per_page) query.set("per_page", String(params.per_page));
  if (params?.q) query.set("q", params.q);
  if (params?.sort) query.set("sort", params.sort);

  const qs = query.toString();
  const path = `/jobs/${jobId}/applications${qs ? `?${qs}` : ""}`;
  const response = await apiClient.get<EmployerJobApplication[]>(path);
  return response as unknown as PaginatedResponse<EmployerJobApplication>;
}
