export type OverallRecommendation = "strong_no" | "no" | "mixed" | "yes" | "strong_yes";

export interface ScorecardCriterion {
  id: string;
  question_text: string;
  category: string;
  sort_order: number;
  rating: number;
  notes: string | null;
}

export interface Scorecard {
  id: string;
  interview_id: string;
  submitted_by: string;
  submitter_name: string;
  overall_rating: number;
  overall_recommendation: OverallRecommendation;
  notes: string | null;
  criteria: ScorecardCriterion[];
  submitted_at: string;
  updated_at: string;
}

export interface ScorecardFormCriterion {
  question_text: string;
  category: string;
  sort_order: number;
  scoring_rubric: string | null;
}

export interface ScorecardForm {
  interview_id: string;
  interview_status: string;
  has_kit: boolean;
  criteria: ScorecardFormCriterion[];
}

export interface ScorecardSummary {
  application_id: string;
  total_scorecards: number;
  average_overall_rating: number | null;
  recommendation_distribution: Record<OverallRecommendation, number>;
  criteria_averages: {
    question_text: string;
    category: string;
    average_rating: number;
    rating_count: number;
  }[];
  interviewers: {
    interviewer_id: string;
    interviewer_name: string;
    interview_id: string;
    overall_rating: number;
    overall_recommendation: OverallRecommendation;
    submitted_at: string;
  }[];
}

export interface SubmitScorecardPayload {
  overall_rating: number;
  overall_recommendation: OverallRecommendation;
  notes?: string;
  criteria?: {
    question_text: string;
    category: string;
    sort_order: number;
    rating: number;
    notes?: string;
  }[];
}

export interface UpdateScorecardPayload {
  overall_rating?: number;
  overall_recommendation?: OverallRecommendation;
  notes?: string;
  criteria?: {
    question_text: string;
    category: string;
    sort_order: number;
    rating: number;
    notes?: string;
  }[];
}
