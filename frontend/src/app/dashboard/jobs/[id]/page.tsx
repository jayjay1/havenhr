"use client";

import { useState, useEffect, useCallback } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import {
  fetchJobDetail,
  fetchJobApplications,
  transitionJobStatus,
} from "@/lib/jobApi";
import {
  KanbanProvider,
  useKanban,
  type KanbanStage,
} from "@/components/pipeline/KanbanProvider";
import { KanbanBoard } from "@/components/pipeline/KanbanBoard";
import type { JobPosting, JobStatus } from "@/types/job";

const STATUS_BADGE: Record<string, string> = {
  draft: "bg-gray-100 text-gray-700",
  published: "bg-green-100 text-green-700",
  closed: "bg-yellow-100 text-yellow-700",
  archived: "bg-red-100 text-red-700",
};

function formatDate(dateStr: string | null): string {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function formatSalary(min: number | null, max: number | null, currency: string | null): string {
  const cur = currency || "USD";
  const fmt = (n: number) =>
    new Intl.NumberFormat("en-US", { style: "currency", currency: cur, maximumFractionDigits: 0 }).format(n);
  if (min && max) return `${fmt(min)} – ${fmt(max)}`;
  if (min) return `From ${fmt(min)}`;
  if (max) return `Up to ${fmt(max)}`;
  return "Not specified";
}

/** Determine which status transitions are available from the current status. */
function getAvailableTransitions(status: JobStatus): { label: string; target: JobStatus; variant: string }[] {
  switch (status) {
    case "draft":
      return [{ label: "Publish", target: "published", variant: "bg-green-600 hover:bg-green-700 text-white" }];
    case "published":
      return [
        { label: "Unpublish", target: "draft", variant: "bg-gray-600 hover:bg-gray-700 text-white" },
        { label: "Close", target: "closed", variant: "bg-yellow-600 hover:bg-yellow-700 text-white" },
      ];
    case "closed":
      return [
        { label: "Reopen", target: "published", variant: "bg-green-600 hover:bg-green-700 text-white" },
        { label: "Archive", target: "archived", variant: "bg-red-600 hover:bg-red-700 text-white" },
      ];
    default:
      return [];
  }
}

/** Wrapper that loads pipeline data and dispatches it to KanbanProvider. */
function KanbanBoardWrapper({ jobId }: { jobId: string }) {
  const { state, dispatch } = useKanban();
  const { hasPermission } = useAuth();

  const canManage = hasPermission("pipeline.manage");
  const canCustomize = hasPermission("pipeline.manage");

  const loadData = useCallback(async () => {
    dispatch({ type: "SET_LOADING", isLoading: true });
    try {
      const [jobRes, appsRes] = await Promise.all([
        fetchJobDetail(jobId),
        fetchJobApplications(jobId, { per_page: 100 }),
      ]);

      const job = jobRes.data;
      const applications = appsRes.data;
      const totalCandidates = appsRes.meta?.total ?? applications.length;

      const kanbanStages: KanbanStage[] = (job.pipeline_stages || []).map(stage => ({
        id: stage.id,
        name: stage.name,
        color: (stage as unknown as { color?: string | null }).color ?? null,
        sort_order: stage.sort_order,
        applications: applications.filter(app => app.current_stage === stage.name),
      }));

      dispatch({ type: "SET_DATA", stages: kanbanStages, totalCandidates });
    } catch {
      dispatch({ type: "SET_ERROR", error: "Failed to load pipeline data." });
    }
  }, [jobId, dispatch]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  return (
    <KanbanBoard
      jobId={jobId}
      canManage={canManage}
      canCustomize={canCustomize}
      onRetry={loadData}
    />
  );
}

export default function EmployerJobDetailPage() {
  const params = useParams();
  const id = params.id as string;
  const { hasPermission } = useAuth();

  const [job, setJob] = useState<JobPosting | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [actionLoading, setActionLoading] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const jobRes = await fetchJobDetail(id);
      setJob(jobRes.data);
    } catch {
      setError("Failed to load job details.");
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  async function handleTransition(target: JobStatus) {
    setActionLoading(true);
    try {
      await transitionJobStatus(id, target);
      await loadData();
    } catch {
      setError("Failed to update job status.");
    } finally {
      setActionLoading(false);
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="inline-block h-6 w-6 animate-spin rounded-full border-4 border-blue-600 border-r-transparent" role="status" aria-label="Loading" />
        <span className="ml-2 text-sm text-gray-500">Loading job details…</span>
      </div>
    );
  }

  if (error && !job) {
    return (
      <div>
        <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">{error}</div>
        <Link href="/dashboard/jobs" className="mt-4 inline-block text-sm text-blue-600 hover:text-blue-700">← Back to Jobs</Link>
      </div>
    );
  }

  if (!job) return null;

  const transitions = getAvailableTransitions(job.status);

  return (
    <div>
      {/* Back link */}
      <Link href="/dashboard/jobs" className="text-sm text-blue-600 hover:text-blue-700 mb-4 inline-block">
        ← Back to Jobs
      </Link>

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">{error}</div>
      )}

      {/* Job details summary */}
      <div className="bg-white rounded-lg border border-gray-200 p-6 mb-6">
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div className="flex-1">
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-gray-900">{job.title}</h1>
              <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${STATUS_BADGE[job.status]}`}>
                {job.status}
              </span>
            </div>

            <div className="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
              <div>
                <span className="text-gray-500">Location:</span>{" "}
                <span className="font-medium text-gray-900">{job.location}</span>
              </div>
              <div>
                <span className="text-gray-500">Type:</span>{" "}
                <span className="font-medium text-gray-900 capitalize">{job.employment_type.replace("-", " ")}</span>
              </div>
              {job.department && (
                <div>
                  <span className="text-gray-500">Department:</span>{" "}
                  <span className="font-medium text-gray-900">{job.department}</span>
                </div>
              )}
              {job.remote_status && (
                <div>
                  <span className="text-gray-500">Remote:</span>{" "}
                  <span className="font-medium text-gray-900 capitalize">{job.remote_status.replace("-", " ")}</span>
                </div>
              )}
              <div>
                <span className="text-gray-500">Salary:</span>{" "}
                <span className="font-medium text-gray-900">{formatSalary(job.salary_min, job.salary_max, job.salary_currency)}</span>
              </div>
              <div>
                <span className="text-gray-500">Applications:</span>{" "}
                <span className="font-medium text-gray-900">{job.application_count}</span>
              </div>
              <div>
                <span className="text-gray-500">Published:</span>{" "}
                <span className="font-medium text-gray-900">{formatDate(job.published_at)}</span>
              </div>
              <div>
                <span className="text-gray-500">Created:</span>{" "}
                <span className="font-medium text-gray-900">{formatDate(job.created_at)}</span>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="flex flex-wrap gap-2 shrink-0">
            {hasPermission("jobs.update") && job.status !== "archived" && (
              <Link
                href={`/dashboard/jobs/${id}/edit`}
                className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                Edit
              </Link>
            )}
            {hasPermission("jobs.update") && transitions.map((t) => (
              <button
                key={t.target}
                type="button"
                onClick={() => handleTransition(t.target)}
                disabled={actionLoading}
                className={`inline-flex items-center rounded-md px-3 py-1.5 text-sm font-medium disabled:opacity-50 ${t.variant}`}
              >
                {t.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Kanban pipeline board */}
      <div className="mb-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Hiring Pipeline</h2>
        <KanbanProvider>
          <KanbanBoardWrapper jobId={id} />
        </KanbanProvider>
      </div>

      {/* Job description section */}
      <div className="bg-white rounded-lg border border-gray-200 p-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Job Description</h2>
        <div className="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
          {job.description}
        </div>

        {job.requirements && (
          <div className="mt-6">
            <h3 className="text-base font-semibold text-gray-900 mb-2">Requirements</h3>
            <div className="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
              {job.requirements}
            </div>
          </div>
        )}

        {job.benefits && (
          <div className="mt-6">
            <h3 className="text-base font-semibold text-gray-900 mb-2">Benefits</h3>
            <div className="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
              {job.benefits}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
