import { apiClient } from "@/lib/api";
import type { ApiResponse } from "@/types/api";
import type {
  Interview,
  InterviewDetail,
  InterviewListItem,
  UpcomingInterview,
  ScheduleInterviewPayload,
  UpdateInterviewPayload,
} from "@/types/interview";

/**
 * Schedule a new interview for a job application.
 */
export async function scheduleInterview(
  payload: ScheduleInterviewPayload
): Promise<ApiResponse<Interview>> {
  return apiClient.post<Interview>(
    "/interviews",
    payload as unknown as Record<string, unknown>
  );
}

/**
 * List all interviews for a specific application.
 */
export async function listInterviewsForApplication(
  applicationId: string
): Promise<ApiResponse<InterviewListItem[]>> {
  return apiClient.get<InterviewListItem[]>(
    `/applications/${applicationId}/interviews`
  );
}

/**
 * Get full detail for a specific interview.
 */
export async function getInterviewDetail(
  interviewId: string
): Promise<ApiResponse<InterviewDetail>> {
  return apiClient.get<InterviewDetail>(`/interviews/${interviewId}`);
}

/**
 * Update an existing interview.
 */
export async function updateInterview(
  interviewId: string,
  payload: UpdateInterviewPayload
): Promise<ApiResponse<Interview>> {
  return apiClient.put<Interview>(
    `/interviews/${interviewId}`,
    payload as unknown as Record<string, unknown>
  );
}

/**
 * Cancel an interview.
 */
export async function cancelInterview(
  interviewId: string
): Promise<ApiResponse<Interview>> {
  return apiClient.patch<Interview>(`/interviews/${interviewId}/cancel`);
}

/**
 * Fetch upcoming interviews for the dashboard widget.
 */
export async function fetchUpcomingInterviews(): Promise<
  ApiResponse<UpcomingInterview[]>
> {
  return apiClient.get<UpcomingInterview[]>(
    "/dashboard/upcoming-interviews"
  );
}
