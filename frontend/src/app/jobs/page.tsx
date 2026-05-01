"use client";

import { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { fetchPublicJobs } from "@/lib/jobApi";
import type { PublicJobListItem } from "@/types/job";

const EMPLOYMENT_TYPES = [
  { value: "full-time", label: "Full-time" },
  { value: "part-time", label: "Part-time" },
  { value: "contract", label: "Contract" },
  { value: "internship", label: "Internship" },
];

const REMOTE_OPTIONS = [
  { value: "remote", label: "Remote" },
  { value: "on-site", label: "On-site" },
  { value: "hybrid", label: "Hybrid" },
];

const SORT_OPTIONS = [
  { value: "published_at:desc", label: "Newest first" },
  { value: "title:asc", label: "Title A–Z" },
];

function formatSalary(min: number | null, max: number | null, currency: string | null): string {
  const cur = currency || "USD";
  const fmt = (n: number) =>
    new Intl.NumberFormat("en-US", { style: "currency", currency: cur, maximumFractionDigits: 0 }).format(n);

  if (min && max) return `${fmt(min)} – ${fmt(max)}`;
  if (min) return `From ${fmt(min)}`;
  if (max) return `Up to ${fmt(max)}`;
  return "";
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

export default function JobBoardPage() {
  const [jobs, setJobs] = useState<PublicJobListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [selectedTypes, setSelectedTypes] = useState<string[]>([]);
  const [selectedRemote, setSelectedRemote] = useState<string[]>([]);
  const [sort, setSort] = useState("published_at:desc");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [filtersOpen, setFiltersOpen] = useState(false);

  const loadJobs = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [sortField, sortDir] = sort.split(":");
      const result = await fetchPublicJobs({
        page,
        per_page: 12,
        q: search || undefined,
        employment_type: selectedTypes.length ? selectedTypes.join(",") : undefined,
        remote_status: selectedRemote.length ? selectedRemote.join(",") : undefined,
        sort: sortField,
        direction: sortDir,
      });
      setJobs(result.data);
      setTotalPages(result.meta.last_page);
    } catch {
      setError("Failed to load jobs. Please try again.");
    } finally {
      setLoading(false);
    }
  }, [search, selectedTypes, selectedRemote, sort, page]);

  useEffect(() => {
    loadJobs();
  }, [loadJobs]);

  // Reset to page 1 when filters change
  useEffect(() => {
    setPage(1);
  }, [search, selectedTypes, selectedRemote, sort]);

  function toggleType(value: string) {
    setSelectedTypes((prev) =>
      prev.includes(value) ? prev.filter((t) => t !== value) : [...prev, value]
    );
  }

  function toggleRemote(value: string) {
    setSelectedRemote((prev) =>
      prev.includes(value) ? prev.filter((r) => r !== value) : [...prev, value]
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <div>
              <Link href="/" className="text-2xl font-bold text-blue-600">
                HavenHR
              </Link>
              <h1 className="mt-2 text-xl font-semibold text-gray-900">Job Board</h1>
              <p className="text-sm text-gray-500">Find your next opportunity</p>
            </div>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {/* Search and sort bar */}
        <div className="flex flex-col sm:flex-row gap-3 mb-6">
          <div className="flex-1 relative">
            <input
              type="search"
              placeholder="Search jobs by title, department, or location…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full rounded-md border border-gray-300 px-4 py-2 pl-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600"
              aria-label="Search jobs"
            />
            <svg
              className="absolute left-3 top-2.5 h-4 w-4 text-gray-400"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={2}
              stroke="currentColor"
              aria-hidden="true"
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
          </div>

          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600"
            aria-label="Sort jobs"
          >
            {SORT_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>

          <button
            type="button"
            onClick={() => setFiltersOpen(!filtersOpen)}
            className="sm:hidden rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            aria-expanded={filtersOpen}
          >
            Filters {(selectedTypes.length + selectedRemote.length) > 0 && `(${selectedTypes.length + selectedRemote.length})`}
          </button>
        </div>

        <div className="flex flex-col lg:flex-row gap-6">
          {/* Filters sidebar */}
          <aside
            className={`lg:w-56 shrink-0 ${filtersOpen ? "block" : "hidden lg:block"}`}
            aria-label="Job filters"
          >
            <div className="bg-white rounded-lg border border-gray-200 p-4 space-y-5">
              <div>
                <h3 className="text-sm font-medium text-gray-900 mb-2">Employment Type</h3>
                <div className="space-y-2">
                  {EMPLOYMENT_TYPES.map((type) => (
                    <label key={type.value} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={selectedTypes.includes(type.value)}
                        onChange={() => toggleType(type.value)}
                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                      />
                      {type.label}
                    </label>
                  ))}
                </div>
              </div>

              <div>
                <h3 className="text-sm font-medium text-gray-900 mb-2">Remote Status</h3>
                <div className="space-y-2">
                  {REMOTE_OPTIONS.map((opt) => (
                    <label key={opt.value} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={selectedRemote.includes(opt.value)}
                        onChange={() => toggleRemote(opt.value)}
                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                      />
                      {opt.label}
                    </label>
                  ))}
                </div>
              </div>

              {(selectedTypes.length > 0 || selectedRemote.length > 0) && (
                <button
                  type="button"
                  onClick={() => {
                    setSelectedTypes([]);
                    setSelectedRemote([]);
                  }}
                  className="text-sm text-blue-600 hover:text-blue-700"
                >
                  Clear all filters
                </button>
              )}
            </div>
          </aside>

          {/* Job cards grid */}
          <div className="flex-1">
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

            {error && (
              <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                {error}
              </div>
            )}

            {!loading && !error && jobs.length === 0 && (
              <div className="text-center py-12 bg-white rounded-lg border border-gray-200">
                <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
                </svg>
                <h3 className="mt-4 text-sm font-medium text-gray-900">No jobs found</h3>
                <p className="mt-1 text-sm text-gray-500">Try adjusting your search or filters.</p>
              </div>
            )}

            {!loading && !error && jobs.length > 0 && (
              <>
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                  {jobs.map((job) => (
                    <Link
                      key={job.id}
                      href={`/jobs/${job.slug}`}
                      className="block bg-white rounded-lg border border-gray-200 p-5 hover:shadow-md hover:border-blue-200 transition-all"
                    >
                      <div className="flex items-start justify-between gap-2">
                        <h2 className="text-base font-semibold text-gray-900 line-clamp-2">
                          {job.title}
                        </h2>
                        {job.application_count > 0 && (
                          <span className="shrink-0 inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                            {job.application_count} applicant{job.application_count !== 1 ? "s" : ""}
                          </span>
                        )}
                      </div>
                      <p className="mt-1 text-sm text-gray-600">{job.company_name}</p>
                      <div className="mt-3 flex flex-wrap gap-2 text-xs text-gray-500">
                        <span className="inline-flex items-center gap-1">
                          <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                          </svg>
                          {job.location}
                        </span>
                        <span className="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 capitalize">
                          {job.employment_type.replace("-", " ")}
                        </span>
                        {job.remote_status && (
                          <span className="inline-flex items-center rounded-full bg-green-50 text-green-700 px-2 py-0.5 capitalize">
                            {job.remote_status.replace("-", " ")}
                          </span>
                        )}
                      </div>
                      {(job.salary_min || job.salary_max) && (
                        <p className="mt-2 text-sm font-medium text-gray-700">
                          {formatSalary(job.salary_min, job.salary_max, job.salary_currency)}
                        </p>
                      )}
                      <p className="mt-2 text-xs text-gray-400">
                        Posted {formatDate(job.published_at)}
                      </p>
                    </Link>
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
                    <span className="text-sm text-gray-500">
                      Page {page} of {totalPages}
                    </span>
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
        </div>
      </main>
    </div>
  );
}
