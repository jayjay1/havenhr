"use client";

import { useState, useEffect, useCallback } from "react";
import { listInterviewsForApplication, updateInterview, cancelInterview } from "@/lib/interviewApi";
import { listScorecardsForInterview } from "@/lib/scorecardApi";
import { ApiRequestError } from "@/lib/api";
import { useAuth } from "@/contexts/AuthContext";
import { ScorecardSubmissionModal } from "./ScorecardSubmissionModal";
import type { InterviewListItem, InterviewStatus } from "@/types/interview";

interface InterviewListProps {
  applicationId: string;
  canManage: boolean;
}

const STATUS_BADGE: Record<InterviewStatus, string> = {
  scheduled: "bg-blue-100 text-blue-700",
  completed: "bg-green-100 text-green-700",
  cancelled: "bg-gray-100 text-gray-500",
  no_show: "bg-red-100 text-red-700",
};

const TYPE_BADGE: Record<string, string> = {
  phone: "bg-yellow-100 text-yellow-700",
  video: "bg-purple-100 text-purple-700",
  in_person: "bg-teal-100 text-teal-700",
};

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function formatType(type: string): string {
  return type.replace("_", " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

export function InterviewList({ applicationId, canManage }: InterviewListProps) {
  const { user } = useAuth();
  const [interviews, setInterviews] = useState<InterviewListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [confirmCancelId, setConfirmCancelId] = useState<string | null>(null);
  const [scorecardModalInterviewId, setScorecardModalInterviewId] = useState<string | null>(null);
  const [interviewsWithMyScorecard, setInterviewsWithMyScorecard] = useState<Set<string>>(new Set());

  const loadInterviews = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await listInterviewsForApplication(applicationId);
      setInterviews(response.data);

      // Check which completed interviews already have a scorecard from the current user
      const completedInterviews = response.data.filter((i) => i.status === "completed");
      const scorecardChecks = await Promise.allSettled(
        completedInterviews.map((i) => listScorecardsForInterview(i.id))
      );

      const withScorecard = new Set<string>();
      completedInterviews.forEach((interview, idx) => {
        const result = scorecardChecks[idx];
        if (result.status === "fulfilled") {
          const hasMyScorecard = result.value.data.some(
            (sc) => sc.submitted_by === user?.id
          );
          if (hasMyScorecard) {
            withScorecard.add(interview.id);
          }
        }
      });
      setInterviewsWithMyScorecard(withScorecard);
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Failed to load interviews."
      );
    } finally {
      setLoading(false);
    }
  }, [applicationId, user?.id]);

  useEffect(() => {
    loadInterviews();
  }, [loadInterviews]);

  const handleStatusChange = useCallback(
    async (interviewId: string, status: InterviewStatus) => {
      try {
        if (status === "cancelled") {
          await cancelInterview(interviewId);
        } else {
          await updateInterview(interviewId, { status });
        }
        await loadInterviews();
      } catch (err) {
        setError(
          err instanceof ApiRequestError ? err.message : "Failed to update interview."
        );
      }
      setConfirmCancelId(null);
    },
    [loadInterviews]
  );

  // Loading skeleton
  if (loading) {
    return (
      <div className="space-y-3 animate-pulse">
        {[1, 2].map((i) => (
          <div key={i} className="h-24 bg-gray-100 rounded-lg" />
        ))}
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

  if (interviews.length === 0) {
    return (
      <p className="text-sm text-gray-500 py-4 text-center">No interviews scheduled</p>
    );
  }

  return (
    <div className="space-y-3">
      {interviews.map((interview) => (
        <div
          key={interview.id}
          className="rounded-lg border border-gray-200 bg-white p-4"
        >
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0 flex-1">
              <p className="text-sm font-medium text-gray-900">
                {interview.interviewer_name}
              </p>
              <p className="text-xs text-gray-500 mt-0.5">
                {formatDateTime(interview.scheduled_at)} · {interview.duration_minutes} min
              </p>
              <p className="text-xs text-gray-500 mt-0.5">{interview.location}</p>
            </div>
            <div className="flex gap-1.5 shrink-0">
              <span
                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                  TYPE_BADGE[interview.interview_type] ?? "bg-gray-100 text-gray-700"
                }`}
              >
                {formatType(interview.interview_type)}
              </span>
              <span
                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ${
                  STATUS_BADGE[interview.status]
                }`}
              >
                {interview.status.replace("_", " ")}
              </span>
            </div>
          </div>

          {/* Status actions */}
          {canManage && interview.status === "scheduled" && (
            <div className="flex gap-2 mt-3 pt-3 border-t border-gray-100">
              <button
                type="button"
                onClick={() => handleStatusChange(interview.id, "completed")}
                className="px-2.5 py-1 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500"
              >
                Mark Completed
              </button>
              {confirmCancelId === interview.id ? (
                <div className="flex items-center gap-2">
                  <span className="text-xs text-gray-600">Cancel this interview?</span>
                  <button
                    type="button"
                    onClick={() => handleStatusChange(interview.id, "cancelled")}
                    className="px-2.5 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                  >
                    Confirm
                  </button>
                  <button
                    type="button"
                    onClick={() => setConfirmCancelId(null)}
                    className="px-2.5 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400"
                  >
                    No
                  </button>
                </div>
              ) : (
                <button
                  type="button"
                  onClick={() => setConfirmCancelId(interview.id)}
                  className="px-2.5 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500"
                >
                  Cancel
                </button>
              )}
              <button
                type="button"
                onClick={() => handleStatusChange(interview.id, "no_show")}
                className="px-2.5 py-1 text-xs font-medium text-orange-700 bg-orange-50 border border-orange-200 rounded hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
              >
                No-Show
              </button>
            </div>
          )}

          {/* Scorecard actions for completed interviews */}
          {interview.status === "completed" && (
            <div className="flex gap-2 mt-3 pt-3 border-t border-gray-100">
              {interviewsWithMyScorecard.has(interview.id) ? (
                <span className="text-xs text-green-600 font-medium">
                  ✓ Scorecard submitted
                </span>
              ) : (
                <button
                  type="button"
                  onClick={() => setScorecardModalInterviewId(interview.id)}
                  className="px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  Submit Scorecard
                </button>
              )}
            </div>
          )}
        </div>
      ))}

      {/* Scorecard Submission Modal */}
      {scorecardModalInterviewId && (
        <ScorecardSubmissionModal
          interviewId={scorecardModalInterviewId}
          onClose={() => setScorecardModalInterviewId(null)}
          onSubmitted={() => {
            setScorecardModalInterviewId(null);
            loadInterviews();
          }}
        />
      )}
    </div>
  );
}
