"use client";

import { useState, useEffect, useCallback } from "react";
import { fetchUpcomingInterviews } from "@/lib/interviewApi";
import { ApiRequestError } from "@/lib/api";
import type { UpcomingInterview } from "@/types/interview";

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function formatType(type: string): string {
  return type.replace("_", " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

const TYPE_BADGE: Record<string, string> = {
  phone: "bg-yellow-100 text-yellow-700",
  video: "bg-purple-100 text-purple-700",
  in_person: "bg-teal-100 text-teal-700",
};

export function UpcomingInterviewsWidget() {
  const [interviews, setInterviews] = useState<UpcomingInterview[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const loadInterviews = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await fetchUpcomingInterviews();
      setInterviews(response.data);
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Failed to load upcoming interviews."
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadInterviews();
  }, [loadInterviews]);

  return (
    <div className="rounded-lg border border-gray-200 bg-white">
      <div className="border-b border-gray-200 px-6 py-4">
        <h2 className="text-lg font-semibold text-gray-900">Upcoming Interviews</h2>
      </div>

      {loading && (
        <div className="px-6 py-4 space-y-3 animate-pulse">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-14 bg-gray-100 rounded" />
          ))}
        </div>
      )}

      {!loading && error && (
        <div className="px-6 py-8 text-center">
          <p className="text-sm text-red-600">{error}</p>
        </div>
      )}

      {!loading && !error && interviews.length === 0 && (
        <div className="px-6 py-8 text-center">
          <p className="text-sm text-gray-500">No upcoming interviews this week</p>
        </div>
      )}

      {!loading && !error && interviews.length > 0 && (
        <ul className="divide-y divide-gray-100">
          {interviews.map((interview) => (
            <li key={interview.id} className="px-6 py-3 flex items-center justify-between gap-3">
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-gray-900 truncate">
                  {interview.candidate_name}
                </p>
                <p className="text-xs text-gray-500 truncate">
                  {interview.job_title}
                </p>
              </div>
              <div className="text-right shrink-0">
                <p className="text-xs text-gray-700">
                  {formatDateTime(interview.scheduled_at)}
                </p>
                <span
                  className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium mt-0.5 ${
                    TYPE_BADGE[interview.interview_type] ?? "bg-gray-100 text-gray-700"
                  }`}
                >
                  {formatType(interview.interview_type)}
                </span>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
