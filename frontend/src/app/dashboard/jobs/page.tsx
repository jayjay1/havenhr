"use client";

import { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { fetchTenantJobs, transitionJobStatus, deleteJobPosting } from "@/lib/jobApi";
import type { JobPostingListItem, JobStatus } from "@/types/job";

const STATUS_TABS: { value: string; label: string }[] = [
  { value: "", label: "All" },
  { value: "draft", label: "Draft" },
  { value: "published", label: "Published" },
  { value: "closed", label: "Closed" },
  { value: "archived", label: "Archived" },
];

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

export default function EmployerJobDashboard() {
  const { hasPermission } = useAuth();
  const [jobs, setJobs] = useState<JobPostingListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  const loadJobs = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const result = await fetchTenantJobs({
        page,
        per_page: 20,
        status: statusFilter || undefined,
        sort: "created_at",
        direction: "desc",
      });
      setJobs(result.data);
      setTotalPages(result.meta.last_page);
    } catch {
      setError("Failed to load jobs.");
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter]);

  useEffect(() => {
    loadJobs();
  }, [loadJobs]);

  useEffect(() => {
    setPage(1);
  }, [statusFilter]);

  async function handleTransition(id: string, status: JobStatus) {
    setActionLoading(id);
    try {
      await transitionJobStatus(id, status);
      await loadJobs();
    } catch {
      setError(`Failed to update job status.`);
    } finally {
      setActionLoading(null);
    }
  }

  async function handleDelete(id: string) {
    if (!confirm("Are you sure you want to delete this draft job posting?")) return;
    setActionLoading(id);
    try {
      await deleteJobPosting(id);
      await loadJobs();
    } catch {
      setError("Failed to delete job posting.");
    } finally {
      setActionLoading(null);
    }
  }

  return (
    <div>
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Job Postings</h1>
          <p className="mt-1 text-sm text-gray-500">Manage your job postings and track applications.</p>
        </div>
        {hasPermission("jobs.create") && (
          <Link
            href="/dashboard/jobs/new"
            className="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 w-full sm:w-auto"
          >
            <svg className="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Create New Job
          </Link>
        )}
      </div>

      {/* Status filter tabs */}
      <div className="border-b border-gray-200 mb-6">
        <nav className="-mb-px flex gap-4 overflow-x-auto" aria-label="Status filter">
          {STATUS_TABS.map((tab) => (
            <button
              key={tab.value}
              type="button"
              onClick={() => setStatusFilter(tab.value)}
              className={`whitespace-nowrap pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
                statusFilter === tab.value
                  ? "border-blue-600 text-blue-600"
                  : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
              }`}
            >
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          {error}
        </div>
      )}

      {loading && (
        <div className="flex items-center justify-center py-12">
          <div
            className="inline-block h-6 w-6 animate-spin rounded-full border-4 border-blue-600 border-r-transparent"
            role="status"
            aria-label="Loading jobs"
          />
          <span className="ml-2 text-sm text-gray-500">Loading jobs…</span>
        </div>
      )}

      {!loading && !error && jobs.length === 0 && (
        <div className="text-center py-12 bg-white rounded-lg border border-gray-200">
          <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
          </svg>
          <h3 className="mt-4 text-sm font-medium text-gray-900">No job postings</h3>
          <p className="mt-1 text-sm text-gray-500">Get started by creating your first job posting.</p>
        </div>
      )}

      {!loading && !error && jobs.length > 0 && (
        <>
          {/* Desktop table */}
          <div className="hidden md:block bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applications</th>
                  <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Published</th>
                  <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {jobs.map((job) => (
                  <tr key={job.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3">
                      <Link href={`/dashboard/jobs/${job.id}`} className="text-sm font-medium text-blue-600 hover:text-blue-700">
                        {job.title}
                      </Link>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${STATUS_BADGE[job.status] ?? "bg-gray-100 text-gray-700"}`}>
                        {job.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500">{job.department || "—"}</td>
                    <td className="px-4 py-3 text-sm text-gray-500">{job.location}</td>
                    <td className="px-4 py-3 text-sm text-gray-500">{job.application_count}</td>
                    <td className="px-4 py-3 text-sm text-gray-500">{formatDate(job.published_at)}</td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-2">
                        {job.status === "draft" && hasPermission("jobs.update") && (
                          <button
                            type="button"
                            onClick={() => handleTransition(job.id, "published")}
                            disabled={actionLoading === job.id}
                            className="text-xs font-medium text-green-600 hover:text-green-700 disabled:opacity-50"
                          >
                            Publish
                          </button>
                        )}
                        {job.status === "published" && hasPermission("jobs.update") && (
                          <button
                            type="button"
                            onClick={() => handleTransition(job.id, "closed")}
                            disabled={actionLoading === job.id}
                            className="text-xs font-medium text-yellow-600 hover:text-yellow-700 disabled:opacity-50"
                          >
                            Close
                          </button>
                        )}
                        {hasPermission("jobs.update") && (
                          <Link
                            href={`/dashboard/jobs/${job.id}/edit`}
                            className="text-xs font-medium text-blue-600 hover:text-blue-700"
                          >
                            Edit
                          </Link>
                        )}
                        {job.status === "draft" && hasPermission("jobs.delete") && (
                          <button
                            type="button"
                            onClick={() => handleDelete(job.id)}
                            disabled={actionLoading === job.id}
                            className="text-xs font-medium text-red-600 hover:text-red-700 disabled:opacity-50"
                          >
                            Delete
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile cards */}
          <div className="md:hidden space-y-3">
            {jobs.map((job) => (
              <div key={job.id} className="bg-white rounded-lg border border-gray-200 p-4">
                <div className="flex items-start justify-between gap-2">
                  <Link href={`/dashboard/jobs/${job.id}`} className="text-sm font-medium text-blue-600 hover:text-blue-700">
                    {job.title}
                  </Link>
                  <span className={`shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_BADGE[job.status] ?? "bg-gray-100 text-gray-700"}`}>
                    {job.status}
                  </span>
                </div>
                <div className="mt-2 text-xs text-gray-500 space-y-1">
                  <p>{job.location} {job.department ? `· ${job.department}` : ""}</p>
                  <p>{job.application_count} application{job.application_count !== 1 ? "s" : ""} · Published {formatDate(job.published_at)}</p>
                </div>
                <div className="mt-3 flex gap-3">
                  {job.status === "draft" && hasPermission("jobs.update") && (
                    <button type="button" onClick={() => handleTransition(job.id, "published")} disabled={actionLoading === job.id} className="text-xs font-medium text-green-600">Publish</button>
                  )}
                  {job.status === "published" && hasPermission("jobs.update") && (
                    <button type="button" onClick={() => handleTransition(job.id, "closed")} disabled={actionLoading === job.id} className="text-xs font-medium text-yellow-600">Close</button>
                  )}
                  {hasPermission("jobs.update") && (
                    <Link href={`/dashboard/jobs/${job.id}/edit`} className="text-xs font-medium text-blue-600">Edit</Link>
                  )}
                  {job.status === "draft" && hasPermission("jobs.delete") && (
                    <button type="button" onClick={() => handleDelete(job.id)} disabled={actionLoading === job.id} className="text-xs font-medium text-red-600">Delete</button>
                  )}
                </div>
              </div>
            ))}
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="mt-6 flex items-center justify-center gap-2">
              <button
                type="button"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page === 1}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <span className="text-sm text-gray-500">Page {page} of {totalPages}</span>
              <button
                type="button"
                onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                disabled={page === totalPages}
                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
