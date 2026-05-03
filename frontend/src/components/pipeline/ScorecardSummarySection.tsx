"use client";

import { useState, useEffect, useCallback } from "react";
import { ApiRequestError } from "@/lib/api";
import { getScorecardSummary, getScorecardDetail } from "@/lib/scorecardApi";
import { ScorecardDetailView } from "@/components/interviews/ScorecardDetailView";
import { ScorecardSubmissionModal } from "@/components/interviews/ScorecardSubmissionModal";
import type { ScorecardSummary, Scorecard, OverallRecommendation } from "@/types/scorecard";
import { useAuth } from "@/contexts/AuthContext";

interface ScorecardSummarySectionProps {
  applicationId: string;
}

const RECOMMENDATION_BADGE: Record<string, string> = {
  strong_no: "bg-red-100 text-red-700",
  no: "bg-orange-100 text-orange-700",
  mixed: "bg-yellow-100 text-yellow-700",
  yes: "bg-green-100 text-green-700",
  strong_yes: "bg-emerald-100 text-emerald-700",
};

function formatRecommendation(rec: string): string {
  return rec.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

function StarDisplay({ rating }: { rating: number }) {
  const rounded = Math.round(rating);
  return (
    <div className="flex items-center gap-0.5" aria-label={`${rating} out of 5 stars`}>
      {[1, 2, 3, 4, 5].map((star) => (
        <span
          key={star}
          className={`inline-block h-4 w-4 text-center text-xs leading-4 rounded-full ${
            star <= rounded
              ? "bg-yellow-400 text-yellow-900"
              : "bg-gray-200 text-gray-400"
          }`}
        >
          {star}
        </span>
      ))}
    </div>
  );
}

export function ScorecardSummarySection({ applicationId }: ScorecardSummarySectionProps) {
  const { user } = useAuth();
  const [summary, setSummary] = useState<ScorecardSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [expandedInterviewer, setExpandedInterviewer] = useState<string | null>(null);
  const [expandedScorecard, setExpandedScorecard] = useState<Scorecard | null>(null);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [editScorecard, setEditScorecard] = useState<Scorecard | null>(null);

  const loadSummary = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const res = await getScorecardSummary(applicationId);
      setSummary(res.data);
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Failed to load scorecard summary."
      );
    } finally {
      setLoading(false);
    }
  }, [applicationId]);

  useEffect(() => {
    loadSummary();
  }, [loadSummary]);

  const handleExpandInterviewer = async (interviewerId: string, interviewId: string) => {
    if (expandedInterviewer === interviewerId) {
      setExpandedInterviewer(null);
      setExpandedScorecard(null);
      return;
    }

    setExpandedInterviewer(interviewerId);
    setLoadingDetail(true);

    try {
      // Find the scorecard for this interviewer — we need to search by interview
      // The summary has interviewer entries but not scorecard IDs, so we list scorecards for the interview
      const { listScorecardsForInterview } = await import("@/lib/scorecardApi");
      const res = await listScorecardsForInterview(interviewId);
      const sc = res.data.find((s) => s.submitted_by === interviewerId);
      setExpandedScorecard(sc ?? null);
    } catch {
      setExpandedScorecard(null);
    } finally {
      setLoadingDetail(false);
    }
  };

  // Loading skeleton
  if (loading) {
    return (
      <div className="space-y-3 animate-pulse">
        <div className="h-4 w-32 bg-gray-200 rounded" />
        <div className="h-8 bg-gray-100 rounded" />
        <div className="h-8 bg-gray-100 rounded" />
      </div>
    );
  }

  if (error) {
    return (
      <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
        {error}
      </div>
    );
  }

  if (!summary || summary.total_scorecards === 0) {
    return (
      <p className="text-sm text-gray-500 py-2">No evaluations submitted yet.</p>
    );
  }

  const recommendations = Object.entries(summary.recommendation_distribution).filter(
    ([, count]) => count > 0
  );

  return (
    <div className="space-y-3">
      {/* Summary stats */}
      <div className="flex items-center gap-3">
        {summary.average_overall_rating !== null && (
          <div className="flex items-center gap-2">
            <StarDisplay rating={summary.average_overall_rating} />
            <span className="text-sm text-gray-600">
              {summary.average_overall_rating.toFixed(1)} avg
            </span>
          </div>
        )}
        <span className="text-xs text-gray-500">
          {summary.total_scorecards} scorecard{summary.total_scorecards !== 1 ? "s" : ""}
        </span>
      </div>

      {/* Recommendation distribution */}
      {recommendations.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {recommendations.map(([rec, count]) => (
            <span
              key={rec}
              className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                RECOMMENDATION_BADGE[rec] ?? "bg-gray-100 text-gray-700"
              }`}
            >
              {formatRecommendation(rec)}: {count}
            </span>
          ))}
        </div>
      )}

      {/* Interviewer entries */}
      <div className="space-y-2">
        {summary.interviewers.map((interviewer) => (
          <div key={`${interviewer.interviewer_id}-${interviewer.interview_id}`}>
            <button
              type="button"
              onClick={() =>
                handleExpandInterviewer(interviewer.interviewer_id, interviewer.interview_id)
              }
              className="w-full flex items-center justify-between bg-gray-50 rounded-md px-3 py-2 hover:bg-gray-100 text-left"
            >
              <div>
                <p className="text-sm font-medium text-gray-900">
                  {interviewer.interviewer_name}
                </p>
                <p className="text-xs text-gray-500">
                  {new Date(interviewer.submitted_at).toLocaleDateString("en-US", {
                    month: "short",
                    day: "numeric",
                  })}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <StarDisplay rating={interviewer.overall_rating} />
                <span
                  className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                    RECOMMENDATION_BADGE[interviewer.overall_recommendation] ?? "bg-gray-100 text-gray-700"
                  }`}
                >
                  {formatRecommendation(interviewer.overall_recommendation)}
                </span>
                <svg
                  className={`h-4 w-4 text-gray-400 transition-transform ${
                    expandedInterviewer === interviewer.interviewer_id ? "rotate-180" : ""
                  }`}
                  fill="none"
                  viewBox="0 0 24 24"
                  strokeWidth={2}
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
              </div>
            </button>

            {/* Expanded detail */}
            {expandedInterviewer === interviewer.interviewer_id && (
              <div className="mt-2 ml-3 pl-3 border-l-2 border-gray-200">
                {loadingDetail ? (
                  <div className="animate-pulse space-y-2 py-2">
                    <div className="h-4 w-48 bg-gray-200 rounded" />
                    <div className="h-4 w-32 bg-gray-200 rounded" />
                  </div>
                ) : expandedScorecard ? (
                  <ScorecardDetailView
                    scorecard={expandedScorecard}
                    canEdit={user?.id === expandedScorecard.submitted_by}
                    onEdit={() => setEditScorecard(expandedScorecard)}
                  />
                ) : (
                  <p className="text-xs text-gray-500 py-2">Could not load scorecard details.</p>
                )}
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Edit modal */}
      {editScorecard && (
        <ScorecardSubmissionModal
          interviewId={editScorecard.interview_id}
          existingScorecard={editScorecard}
          onClose={() => setEditScorecard(null)}
          onSubmitted={() => {
            setEditScorecard(null);
            setExpandedInterviewer(null);
            setExpandedScorecard(null);
            loadSummary();
          }}
        />
      )}
    </div>
  );
}
