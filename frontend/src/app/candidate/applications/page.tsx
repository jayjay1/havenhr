"use client";

import { useState, useEffect } from "react";
import { candidateApiClient } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";
import type { JobApplication } from "@/types/candidate";

const STATUS_STYLES: Record<string, string> = {
  submitted: "bg-blue-100 text-blue-700",
  reviewed: "bg-yellow-100 text-yellow-700",
  shortlisted: "bg-green-100 text-green-700",
  rejected: "bg-red-100 text-red-700",
};

export default function ApplicationsPage() {
  const [applications, setApplications] = useState<JobApplication[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    async function fetchApplications() {
      try {
        const response = await candidateApiClient.get<JobApplication[]>(
          "/candidate/applications"
        );
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
    }
    fetchApplications();
  }, []);

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">My Applications</h1>
        <p className="mt-1 text-sm text-gray-500">
          Track the status of your job applications.
        </p>
      </div>

      {loading && (
        <div className="flex items-center justify-center py-12">
          <div
            className="inline-block h-6 w-6 animate-spin rounded-full border-4 border-teal-600 border-r-transparent"
            role="status"
            aria-label="Loading applications"
          />
          <span className="ml-2 text-sm text-gray-500">
            Loading applications…
          </span>
        </div>
      )}

      {error && (
        <div
          role="alert"
          className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700"
        >
          {error}
        </div>
      )}

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
          <h3 className="mt-4 text-sm font-medium text-gray-900">
            No applications yet
          </h3>
          <p className="mt-1 text-sm text-gray-500">
            When you apply to jobs, they&apos;ll appear here.
          </p>
        </div>
      )}

      {!loading && !error && applications.length > 0 && (
        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          {/* Desktop table */}
          <div className="hidden sm:block">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th
                    scope="col"
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Job Posting
                  </th>
                  <th
                    scope="col"
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Status
                  </th>
                  <th
                    scope="col"
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Applied
                  </th>
                  <th
                    scope="col"
                    className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                  >
                    Last Updated
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {applications.map((app) => (
                  <tr key={app.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-sm text-gray-900">
                      <span className="font-mono text-xs text-gray-500">
                        {app.job_posting_id.slice(0, 8)}…
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${
                          STATUS_STYLES[app.status] ?? "bg-gray-100 text-gray-700"
                        }`}
                      >
                        {app.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500">
                      {new Date(app.applied_at).toLocaleDateString()}
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500">
                      {new Date(app.updated_at).toLocaleDateString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile list */}
          <ul className="sm:hidden divide-y divide-gray-200">
            {applications.map((app) => (
              <li key={app.id} className="px-4 py-4">
                <div className="flex items-center justify-between">
                  <span className="font-mono text-xs text-gray-500">
                    Job: {app.job_posting_id.slice(0, 8)}…
                  </span>
                  <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${
                      STATUS_STYLES[app.status] ?? "bg-gray-100 text-gray-700"
                    }`}
                  >
                    {app.status}
                  </span>
                </div>
                <p className="mt-1 text-xs text-gray-500">
                  Applied {new Date(app.applied_at).toLocaleDateString()}
                </p>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
