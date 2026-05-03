"use client";

import { useState, useEffect, useCallback } from "react";
import { fetchCandidateInterviews } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";
import type { CandidateInterview } from "@/types/interview";

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString("en-US", {
    weekday: "short",
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

const TYPE_BADGE: Record<string, string> = {
  phone: "bg-yellow-100 text-yellow-700",
  video: "bg-purple-100 text-purple-700",
  in_person: "bg-teal-100 text-teal-700",
};

export function CandidateInterviewsList() {
  const [interviews, setInterviews] = useState<CandidateInterview[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const loadInterviews = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await fetchCandidateInterviews();
      setInterviews(response.data);
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Failed to load interviews."
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
          {[1, 2].map((i) => (
            <div key={i} className="h-16 bg-gray-100 rounded" />
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
          <svg
            className="mx-auto h-10 w-10 text-gray-400"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth={1}
            stroke="currentColor"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"
            />
          </svg>
          <p className="mt-2 text-sm text-gray-500">No upcoming interviews</p>
        </div>
      )}

      {!loading && !error && interviews.length > 0 && (
        <ul className="divide-y divide-gray-100">
          {interviews.map((interview) => (
            <li key={interview.id} className="px-6 py-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-gray-900">
                    {interview.job_title}
                  </p>
                  <p className="text-xs text-gray-500 mt-0.5">
                    Interviewer: {interview.interviewer_name}
                  </p>
                  <p className="text-xs text-gray-500 mt-0.5">
                    {interview.location}
                  </p>
                </div>
                <div className="text-right shrink-0">
                  <p className="text-xs font-medium text-gray-700">
                    {formatDateTime(interview.scheduled_at)}
                  </p>
                  <p className="text-xs text-gray-500 mt-0.5">
                    {interview.duration_minutes} min
                  </p>
                  <span
                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium mt-1 ${
                      TYPE_BADGE[interview.interview_type] ?? "bg-gray-100 text-gray-700"
                    }`}
                  >
                    {formatType(interview.interview_type)}
                  </span>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
