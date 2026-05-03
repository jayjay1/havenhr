export type QuestionCategory = "technical" | "behavioral" | "cultural" | "experience";

export interface InterviewKitQuestion {
  id: string;
  text: string;
  category: QuestionCategory;
  sort_order: number;
  scoring_rubric: string | null;
}

export interface InterviewKit {
  id: string;
  pipeline_stage_id: string;
  name: string;
  description: string | null;
  questions: InterviewKitQuestion[];
  created_at: string;
  updated_at: string;
}

export interface InterviewKitListItem {
  id: string;
  name: string;
  description: string | null;
  question_count: number;
  created_at: string;
}

export interface StageKits {
  stage_id: string;
  stage_name: string;
  kits: InterviewKitListItem[];
}

export interface InterviewKitTemplate {
  key: string;
  name: string;
  description: string;
  questions: Omit<InterviewKitQuestion, "id" | "sort_order">[];
}

export interface CreateInterviewKitPayload {
  name: string;
  description?: string;
  questions: {
    text: string;
    category: QuestionCategory;
    sort_order: number;
    scoring_rubric?: string;
  }[];
}

export interface UpdateInterviewKitPayload {
  name?: string;
  description?: string;
  questions?: {
    text: string;
    category: QuestionCategory;
    sort_order: number;
    scoring_rubric?: string;
  }[];
}
