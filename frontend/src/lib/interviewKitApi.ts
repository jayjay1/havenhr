import { apiClient } from "@/lib/api";
import type { ApiResponse } from "@/types/api";
import type {
  InterviewKit,
  InterviewKitTemplate,
  StageKits,
  CreateInterviewKitPayload,
  UpdateInterviewKitPayload,
} from "@/types/interviewKit";

/**
 * List all interview kits for a job posting grouped by pipeline stage.
 */
export async function listInterviewKitsForJob(
  jobId: string
): Promise<ApiResponse<StageKits[]>> {
  return apiClient.get<StageKits[]>(`/jobs/${jobId}/interview-kits`);
}

/**
 * Get a single interview kit with all questions.
 */
export async function getInterviewKitDetail(
  kitId: string
): Promise<ApiResponse<InterviewKit>> {
  return apiClient.get<InterviewKit>(`/interview-kits/${kitId}`);
}

/**
 * Create a new interview kit for a pipeline stage.
 */
export async function createInterviewKit(
  jobId: string,
  stageId: string,
  payload: CreateInterviewKitPayload
): Promise<ApiResponse<InterviewKit>> {
  return apiClient.post<InterviewKit>(
    `/jobs/${jobId}/stages/${stageId}/interview-kits`,
    payload as unknown as Record<string, unknown>
  );
}

/**
 * Update an existing interview kit.
 */
export async function updateInterviewKit(
  kitId: string,
  payload: UpdateInterviewKitPayload
): Promise<ApiResponse<InterviewKit>> {
  return apiClient.put<InterviewKit>(
    `/interview-kits/${kitId}`,
    payload as unknown as Record<string, unknown>
  );
}

/**
 * Delete an interview kit.
 */
export async function deleteInterviewKit(
  kitId: string
): Promise<ApiResponse<void>> {
  return apiClient.del<void>(`/interview-kits/${kitId}`);
}

/**
 * List available default interview kit templates.
 */
export async function listInterviewKitTemplates(): Promise<
  ApiResponse<InterviewKitTemplate[]>
> {
  return apiClient.get<InterviewKitTemplate[]>("/interview-kit-templates");
}

/**
 * Create an interview kit from a default template.
 */
export async function createInterviewKitFromTemplate(
  jobId: string,
  stageId: string,
  templateKey: string
): Promise<ApiResponse<InterviewKit>> {
  return apiClient.post<InterviewKit>(
    `/jobs/${jobId}/stages/${stageId}/interview-kits/from-template`,
    { template_key: templateKey }
  );
}
