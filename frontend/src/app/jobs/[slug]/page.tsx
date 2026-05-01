"use client";

import { useState, useEffect } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import Image from "next/image";
import { fetchPublicJobBySlug } from "@/lib/jobApi";
import type { PublicJobDetail } from "@/types/job";

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
    month: "long",
    day: "numeric",
    year: "numeric",
  });
}

export default function JobDetailPage() {
  const params = useParams();
  const slug = params.slug as string;

  const [job, setJob] = useState<PublicJobDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    async function load() {
      try {
        const response = await fetchPublicJobBySlug(slug);
        setJob(response.data);
      } catch {
        setError("Job not found or no longer available.");
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [slug]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div
            className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-r-transparent"
            role="status"
            aria-label="Loading"
          />
          <p className="mt-2 text-sm text-gray-500">Loading job details…</p>
        </div>
      </div>
    );
  }

  if (error || !job) {
    return (
      <div className="min-h-screen bg-gray-50">
        <header className="bg-white border-b border-gray-200">
          <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <Link href="/jobs" className="text-blue-600 hover:text-blue-700 text-sm font-medium">
              ← Back to Job Board
            </Link>
          </div>
        </header>
        <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
          <div className="text-center">
            <h1 className="text-2xl font-bold text-gray-900">Job Not Found</h1>
            <p className="mt-2 text-gray-500">{error || "This job posting is no longer available."}</p>
            <Link
              href="/jobs"
              className="mt-4 inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
              Browse All Jobs
            </Link>
          </div>
        </main>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white border-b border-gray-200">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <Link href="/jobs" className="text-blue-600 hover:text-blue-700 text-sm font-medium">
            ← Back to Job Board
          </Link>
        </div>
      </header>

      <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Job header */}
        <div className="bg-white rounded-lg border border-gray-200 p-6 sm:p-8">
          <div className="flex flex-col sm:flex-row sm:items-start gap-4">
            {/* Company logo */}
            {job.company_logo_url ? (
              <Image
                src={job.company_logo_url}
                alt={`${job.company_name} logo`}
                width={64}
                height={64}
                className="h-16 w-16 rounded-lg object-contain border border-gray-200"
              />
            ) : (
              <div className="h-16 w-16 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                <span className="text-xl font-bold text-blue-600">
                  {job.company_name.charAt(0).toUpperCase()}
                </span>
              </div>
            )}

            <div className="flex-1">
              <h1 className="text-2xl font-bold text-gray-900">{job.title}</h1>
              <p className="mt-1 text-lg text-gray-600">{job.company_name}</p>

              <div className="mt-3 flex flex-wrap gap-2 text-sm">
                <span className="inline-flex items-center gap-1 text-gray-600">
                  <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                  </svg>
                  {job.location}
                </span>
                <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 capitalize">
                  {job.employment_type.replace("-", " ")}
                </span>
                {job.remote_status && (
                  <span className="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 capitalize">
                    {job.remote_status.replace("-", " ")}
                  </span>
                )}
                {job.department && (
                  <span className="inline-flex items-center rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">
                    {job.department}
                  </span>
                )}
              </div>

              {(job.salary_min || job.salary_max) && (
                <p className="mt-2 text-base font-semibold text-gray-800">
                  {formatSalary(job.salary_min, job.salary_max, job.salary_currency)}
                </p>
              )}

              <div className="mt-3 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                <span>Posted {formatDate(job.published_at)}</span>
                {job.application_count > 0 && (
                  <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                    {job.application_count} applicant{job.application_count !== 1 ? "s" : ""}
                  </span>
                )}
              </div>
            </div>

            {/* Apply button */}
            <div className="sm:shrink-0">
              <Link
                href="/candidate/login"
                className="inline-flex items-center justify-center rounded-md bg-blue-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 w-full sm:w-auto"
              >
                Apply Now
              </Link>
            </div>
          </div>
        </div>

        {/* Job content */}
        <div className="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main content */}
          <div className="lg:col-span-2 space-y-6">
            <section className="bg-white rounded-lg border border-gray-200 p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Job Description</h2>
              <div className="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
                {job.description}
              </div>
            </section>

            {job.requirements && (
              <section className="bg-white rounded-lg border border-gray-200 p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Requirements</h2>
                <div className="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
                  {job.requirements}
                </div>
              </section>
            )}

            {job.benefits && (
              <section className="bg-white rounded-lg border border-gray-200 p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Benefits</h2>
                <div className="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
                  {job.benefits}
                </div>
              </section>
            )}
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <h3 className="text-sm font-semibold text-gray-900 mb-3">Job Overview</h3>
              <dl className="space-y-3 text-sm">
                <div>
                  <dt className="text-gray-500">Company</dt>
                  <dd className="font-medium text-gray-900">{job.company_name}</dd>
                </div>
                <div>
                  <dt className="text-gray-500">Location</dt>
                  <dd className="font-medium text-gray-900">{job.location}</dd>
                </div>
                <div>
                  <dt className="text-gray-500">Type</dt>
                  <dd className="font-medium text-gray-900 capitalize">{job.employment_type.replace("-", " ")}</dd>
                </div>
                {job.remote_status && (
                  <div>
                    <dt className="text-gray-500">Work Mode</dt>
                    <dd className="font-medium text-gray-900 capitalize">{job.remote_status.replace("-", " ")}</dd>
                  </div>
                )}
                {job.department && (
                  <div>
                    <dt className="text-gray-500">Department</dt>
                    <dd className="font-medium text-gray-900">{job.department}</dd>
                  </div>
                )}
                {(job.salary_min || job.salary_max) && (
                  <div>
                    <dt className="text-gray-500">Salary</dt>
                    <dd className="font-medium text-gray-900">
                      {formatSalary(job.salary_min, job.salary_max, job.salary_currency)}
                    </dd>
                  </div>
                )}
              </dl>
            </div>

            <div className="bg-white rounded-lg border border-gray-200 p-6">
              <Link
                href="/candidate/login"
                className="block w-full text-center rounded-md bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
              >
                Apply Now
              </Link>
              <p className="mt-2 text-xs text-center text-gray-500">
                You&apos;ll need to sign in or create an account to apply.
              </p>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
