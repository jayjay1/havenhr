"use client";

import { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { fetchApplications } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";
import type { ApplicationListItem } from "@/types/candidate";
import { CandidateInterviewsList } from "@/components/candidate/CandidateInterviewsList";

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const STATUS_OPTIONS = [
  { value: "", label: "All Statuses" },
  { value: "submitted", label: "Submitted" },
  { value: "reviewed", label: "Reviewed" },
  { value: "shortlisted", label: "Shortlisted" },
  { value: "rejected", label: "Rejected" },
];

const SORT_OPTIONS = [
  { value: "applied_at", label: "Applied Date" },
  { value: "job_title", label: "Job Title" },
];

const STATUS_BADGE: Record<string, string> = {
  submitted: "bg-blue-100 text-blue-700",
  reviewed: "bg-yellow-100 text-yellow-700",
  shortlisted: "bg-green-100 text-green-700",
  rejected: "bg-red-100 text-red-700",
};

// ---------------------------------------------------------------------------
// Pipeline Stage Indicator
// ---------------------------------------------------------------------------

function PipelineIndicator({
  allStages,
  currentStage,
}: {
  allStages: ApplicationListItem["all_stages"];
  currentStage: ApplicationListItem["pipeline_stage"];
}) {
  if (!allStages || allStages.length === 0) return null;

  const sorted = [...allStages].sort((a, b) => a.sort_order - b.sort_order);
  const currentIdx = currentStage
    ? sorted.findIndex((s) => s.name === currentStage.name)
    : -1;

  return (
    <div className="flex items-center gap-1" aria-label={`Pipeline stage: ${currentStage?.name ?? "Unknown"}`}>
      {sorted.map((stage, idx) => {
        const isActive = idx <= currentIdx;
        return (
          <div key={stage.name} className="flex items-center gap-1">
            <div
              className={`h-2 w-8 rounded-full transition-colors ${
                isActive ? "bg-teal-500" : "bg-gray-200"
              }`}
              title={stage.name}
            />
          </div>
        );
      })}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Application Card
// ---------------------------------------------------------------------------

function ApplicationCard({ app }: { app: ApplicationListItem }) {
  const appliedDate = new Date(app.applied_at).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });

  return (
    <Link
      href={`/candidate/applications/${app.id}`}
      className="block bg-white rounded-lg border border-gray-200 p-5 hover:border-teal-300 hover:shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-teal-500"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <h3 className="text-sm font-semibold text-gray-900 truncate">
            {app.job_title}
          </h3>
          <p className="text-sm text-gray-600 mt-0.5">{app.company_name}</p>
        </div>
        <span
          className={`shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${
            STATUS_BADGE[app.status] ?? "bg-gray-100 text-gray-700"
          }`}
        >
          {app.status}
        </span>
      </div>

      <div className="mt-3">
        <PipelineIndicator
          allStages={app.all_stages}
          currentStage={app.pipeline_stage}
        />
        {app.pipeline_stage && (
          <p className="text-xs text-gray-500 mt-1">
            Stage: {app.pipeline_stage.name}
          </p>
        )}
      </div>

      <p className="text-xs text-gray-400 mt-3">Applied {appliedDate}</p>
    </Link>
  );
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function ApplicationsDashboardPage() {
  const [applications, setApplications] = useState<ApplicationListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [statusFilter, setStatusFilter] = useState("");
  const [sortBy, setSortBy] = useState("applied_at");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("desc");

  const loadApplications = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const response = await fetchApplications({
        status: statusFilter || undefined,
        sort_by: sortBy,
        sort_dir: sortDir,
      });
      setApplications(response.data);
    } catch (err) {
      setError(
        err instanceof ApiRequestError
          ? err.message
          : "Failed to load applications."
      );
    } finally {
      setLoading(false);
    }
  }, [statusFilter, sortBy, sortDir]);

  useEffect(() => {
    loadApplications();
  }, [loadApplications]);

  return (
    <div>
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">My Applications</h1>
        <p className="mt-1 text-sm text-gray-500">
          Track the status of your job applications.
        </p>
      </div>

      {/* Upcoming Interviews */}
      <div className="mb-6">
        <CandidateInterviewsList />
      </div>

      {/* Filter & Sort Bar */}
      <div className="flex flex-col sm:flex-row gap-3 mb-6">
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          aria-label="Filter by status"
          className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
        >
          {STATUS_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>

        <select
          value={sortBy}
          onChange={(e) => setSortBy(e.target.value)}
          aria-label="Sort by"
          className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
        >
          {SORT_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>

        <button
          type="button"
          onClick={() => setSortDir((d) => (d === "asc" ? "desc" : "asc"))}
          aria-label={`Sort direction: ${sortDir === "asc" ? "ascending" : "descending"}`}
          className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          {sortDir === "asc" ? (
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12" />
            </svg>
          ) : (
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0l-3.75-3.75M17.25 21L21 17.25" />
            </svg>
          )}
          {sortDir === "asc" ? "Ascending" : "Descending"}
        </button>
      </div>

      {/* Loading */}
      {loading && (
        <div className="flex items-center justify-center py-12">
          <div
            className="inline-block h-6 w-6 animate-spin rounded-full border-4 border-teal-600 border-r-transparent"
            role="status"
            aria-label="Loading applications"
          />
          <span className="ml-2 text-sm text-gray-500">Loading applications…</span>
        </div>
      )}

      {/* Error */}
      {!loading && error && (
        <div
          role="alert"
          className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700"
        >
          {error}
        </div>
      )}

      {/* Empty State */}
      {!loading && !error && applications.length === 0 && (
        <div className="text-center py-12 bg-white rounded-lg border border-gray-200">
          <svg
            className="mx-auto h-12 w-12 text-gray-400"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth={1}
            stroke="currentColor"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z"
            />
          </svg>
          <h3 className="mt-4 text-sm font-medium text-gray-900">No applications yet</h3>
          <p className="mt-1 text-sm text-gray-500">
            Browse open positions and apply to get started.
          </p>
          <Link
            href="/candidate/jobs"
            className="mt-4 inline-flex items-center rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500"
          >
            Browse Jobs
          </Link>
        </div>
      )}

      {/* Application Cards */}
      {!loading && !error && applications.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {applications.map((app) => (
            <ApplicationCard key={app.id} app={app} />
          ))}
        </div>
      )}
    </div>
  );
}
