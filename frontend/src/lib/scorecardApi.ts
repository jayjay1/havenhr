import { apiClient } from "@/lib/api";
import type { ApiResponse } from "@/types/api";
import type {
  Scorecard,
  ScorecardForm,
  ScorecardSummary,
  SubmitScorecardPayload,
  UpdateScorecardPayload,
} from "@/types/scorecard";

/**
 * Get the scorecard form structure for an interview.
 */
export async function getScorecardForm(
  interviewId: string
): Promise<ApiResponse<ScorecardForm>> {
  return apiClient.get<ScorecardForm>(
    `/interviews/${interviewId}/scorecard-form`
  );
}

/**
 * Submit a scorecard for an interview.
 */
export async function submitScorecard(
  interviewId: string,
  payload: SubmitScorecardPayload
): Promise<ApiResponse<Scorecard>> {
  return apiClient.post<Scorecard>(
    `/interviews/${interviewId}/scorecard`,
    payload as unknown as Record<string, unknown>
  );
}

/**
 * Get a single scorecard with full details.
 */
export async function getScorecardDetail(
  scorecardId: string
): Promise<ApiResponse<Scorecard>> {
  return apiClient.get<Scorecard>(`/scorecards/${scorecardId}`);
}

/**
 * Update an existing scorecard.
 */
export async function updateScorecard(
  scorecardId: string,
  payload: UpdateScorecardPayload
): Promise<ApiResponse<Scorecard>> {
  return apiClient.put<Scorecard>(
    `/scorecards/${scorecardId}`,
    payload as unknown as Record<string, unknown>
  );
}

/**
 * List all scorecards for a specific interview.
 */
export async function listScorecardsForInterview(
  interviewId: string
): Promise<ApiResponse<Scorecard[]>> {
  return apiClient.get<Scorecard[]>(
    `/interviews/${interviewId}/scorecards`
  );
}

/**
 * Get the aggregated scorecard summary for a job application.
 */
export async function getScorecardSummary(
  applicationId: string
): Promise<ApiResponse<ScorecardSummary>> {
  return apiClient.get<ScorecardSummary>(
    `/applications/${applicationId}/scorecard-summary`
  );
}
