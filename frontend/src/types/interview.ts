export type InterviewType = "phone" | "video" | "in_person";
export type InterviewStatus = "scheduled" | "completed" | "cancelled" | "no_show";

export interface Interview {
  id: string;
  job_application_id: string;
  interviewer_id: string;
  interviewer_name: string;
  interviewer_email: string;
  scheduled_at: string;
  duration_minutes: number;
  location: string;
  interview_type: InterviewType;
  status: InterviewStatus;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface InterviewListItem {
  id: string;
  interviewer_name: string;
  interviewer_email: string;
  scheduled_at: string;
  duration_minutes: number;
  interview_type: InterviewType;
  status: InterviewStatus;
  location: string;
  notes: string | null;
}

export interface InterviewDetail extends Interview {
  candidate_name: string;
  job_title: string;
}

export interface UpcomingInterview {
  id: string;
  candidate_name: string;
  job_title: string;
  scheduled_at: string;
  duration_minutes: number;
  interview_type: InterviewType;
  location: string;
}

export interface CandidateInterview {
  id: string;
  job_title: string;
  interview_type: InterviewType;
  location: string;
  interviewer_name: string;
  scheduled_at: string;
  duration_minutes: number;
}

export interface ScheduleInterviewPayload {
  job_application_id: string;
  interviewer_id: string;
  scheduled_at: string;
  duration_minutes: 30 | 45 | 60 | 90;
  interview_type: InterviewType;
  location: string;
  notes?: string;
}

export interface UpdateInterviewPayload {
  scheduled_at?: string;
  duration_minutes?: 30 | 45 | 60 | 90;
  interview_type?: InterviewType;
  location?: string;
  interviewer_id?: string;
  notes?: string | null;
  status?: InterviewStatus;
}
