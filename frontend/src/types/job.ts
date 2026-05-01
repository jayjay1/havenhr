/**
 * Job posting status values.
 */
export type JobStatus = "draft" | "published" | "closed" | "archived";

/**
 * Employment type values.
 */
export type EmploymentType = "full-time" | "part-time" | "contract" | "internship";

/**
 * Remote status values.
 */
export type RemoteStatus = "remote" | "on-site" | "hybrid";

/**
 * Full job posting as returned by employer endpoints.
 */
export interface JobPosting {
  id: string;
  title: string;
  slug: string;
  status: JobStatus;
  department: string | null;
  location: string;
  employment_type: EmploymentType;
  remote_status: RemoteStatus | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  description: string;
  requirements: string | null;
  benefits: string | null;
  application_count: number;
  published_at: string | null;
  closed_at: string | null;
  created_at: string;
  updated_at: string;
  pipeline_stages?: PipelineStage[];
}

/**
 * Job posting list item for employer dashboard.
 */
export interface JobPostingListItem {
  id: string;
  title: string;
  status: JobStatus;
  department: string | null;
  location: string;
  employment_type: EmploymentType;
  application_count: number;
  published_at: string | null;
  created_at: string;
}

/**
 * Public job list item (no auth required).
 */
export interface PublicJobListItem {
  id: string;
  title: string;
  slug: string;
  company_name: string;
  department: string | null;
  location: string;
  employment_type: EmploymentType;
  remote_status: RemoteStatus | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  published_at: string;
  application_count: number;
}

/**
 * Public job detail (no auth required).
 */
export interface PublicJobDetail {
  id: string;
  title: string;
  slug: string;
  company_name: string;
  company_logo_url: string | null;
  department: string | null;
  location: string;
  employment_type: EmploymentType;
  remote_status: RemoteStatus | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  description: string;
  requirements: string | null;
  benefits: string | null;
  published_at: string;
  application_count: number;
  og?: {
    title: string;
    description: string;
    url: string;
    type: string;
  };
}

/**
 * Pipeline stage for a job posting.
 */
export interface PipelineStage {
  id: string;
  name: string;
  sort_order: number;
  application_count: number;
}

/**
 * Stage transition record.
 */
export interface StageTransition {
  id: string;
  from_stage: { id: string; name: string } | null;
  to_stage: { id: string; name: string };
  moved_by: { id: string; name: string };
  moved_at: string;
}

/**
 * Employer-facing job application.
 */
export interface EmployerJobApplication {
  id: string;
  candidate_name: string;
  candidate_email: string;
  current_stage: string;
  status: string;
  applied_at: string;
}

/**
 * Payload for creating a job posting.
 */
export interface CreateJobPostingPayload {
  title: string;
  description: string;
  location: string;
  employment_type: EmploymentType;
  department?: string;
  salary_min?: number;
  salary_max?: number;
  salary_currency?: string;
  requirements?: string;
  benefits?: string;
  remote_status?: RemoteStatus;
}

/**
 * Payload for updating a job posting.
 */
export type UpdateJobPostingPayload = Partial<CreateJobPostingPayload>;

/**
 * Extended pipeline stage with color support.
 */
export interface PipelineStageDetail extends PipelineStage {
  color: string | null;
}

/**
 * Result of a bulk action (move or reject).
 */
export interface BulkActionResult {
  success_count: number;
  failed_count: number;
  failed_ids: string[];
}
