/**
 * Candidate user model — platform-level user, no tenant association.
 */
export interface Candidate {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  location: string | null;
  linkedin_url: string | null;
  portfolio_url: string | null;
  is_active: boolean;
  email_verified_at: string | null;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Response from candidate registration endpoint.
 */
export interface CandidateRegisterResponse {
  candidate: Pick<Candidate, "id" | "name" | "email">;
  access_token: string;
  refresh_token: string;
}

/**
 * Response from candidate login endpoint.
 */
export interface CandidateLoginResponse {
  candidate: Pick<Candidate, "id" | "name" | "email">;
  access_token: string;
  refresh_token: string;
}

/**
 * Response from GET /candidate/auth/me endpoint.
 */
export interface CandidateMeResponse {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  location: string | null;
  linkedin_url: string | null;
  portfolio_url: string | null;
  is_active: boolean;
  email_verified_at: string | null;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Resume summary for dashboard listing.
 */
export interface ResumeSummary {
  id: string;
  title: string;
  template_slug: string;
  is_complete: boolean;
  updated_at: string;
}

/**
 * Work history entry from the candidate profile.
 */
export interface WorkHistory {
  id: string;
  job_title: string;
  company_name: string;
  start_date: string;
  end_date: string | null;
  description: string;
  sort_order: number;
}

/**
 * Education entry from the candidate profile.
 */
export interface Education {
  id: string;
  institution_name: string;
  degree: string;
  field_of_study: string;
  start_date: string;
  end_date: string | null;
  sort_order: number;
}

/**
 * Skill entry from the candidate profile.
 */
export interface Skill {
  id: string;
  name: string;
  category: "technical" | "soft";
  sort_order: number;
}

/**
 * Full candidate profile response from GET /candidate/profile.
 */
export interface CandidateProfile {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  location: string | null;
  linkedin_url: string | null;
  portfolio_url: string | null;
  professional_summary: string | null;
  github_url: string | null;
  is_profile_public: boolean;
  work_history: WorkHistory[];
  education: Education[];
  skills: Skill[];
}

/**
 * Pipeline stage info returned with application data.
 */
export interface PipelineStageInfo {
  id: string;
  name: string;
  color: string;
  sort_order: number;
}

/**
 * Stage transition record for application detail.
 */
export interface StageTransitionInfo {
  from_stage: string;
  to_stage: string;
  moved_at: string;
}

/**
 * Enriched application list item from GET /candidate/applications.
 */
export interface ApplicationListItem {
  id: string;
  job_posting_id: string;
  resume_id: string;
  status: "submitted" | "reviewed" | "shortlisted" | "rejected";
  applied_at: string;
  job_title: string;
  company_name: string;
  location: string | null;
  employment_type: string | null;
  pipeline_stage: {
    name: string;
    color: string;
  } | null;
  all_stages: PipelineStageInfo[];
}

/**
 * Full application detail from GET /candidate/applications/{id}.
 */
export interface ApplicationDetail extends ApplicationListItem {
  transitions: StageTransitionInfo[];
  resume_snapshot: ResumeContent | null;
}

/**
 * Resume content JSON structure.
 */
export interface ResumeContent {
  personal_info?: {
    name?: string;
    email?: string;
    phone?: string;
    location?: string;
    linkedin_url?: string;
    portfolio_url?: string;
  };
  summary?: string;
  work_experience?: {
    job_title: string;
    company_name: string;
    start_date: string;
    end_date: string | null;
    bullets: string[];
  }[];
  education?: {
    institution_name: string;
    degree: string;
    field_of_study: string;
    start_date: string;
    end_date: string | null;
  }[];
  skills?: string[];
}

/**
 * Full resume detail from GET /candidate/resumes/{id}.
 */
export interface ResumeDetail {
  id: string;
  title: string;
  template_slug: string;
  content: ResumeContent;
  is_complete: boolean;
  public_link_token: string | null;
  public_link_active: boolean;
  show_contact_on_public: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Public resume response from GET /public/resumes/{token}.
 */
export interface PublicResume {
  title: string;
  template_slug: string;
  content: ResumeContent;
}

/**
 * Job application entry.
 */
export interface JobApplication {
  id: string;
  job_posting_id: string;
  resume_id: string;
  status: "submitted" | "reviewed" | "shortlisted" | "rejected";
  applied_at: string;
  updated_at: string;
}
