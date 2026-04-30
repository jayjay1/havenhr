"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { useCandidateAuth } from "@/contexts/CandidateAuthContext";
import { candidateApiClient } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";
import { Button } from "@/components/ui/Button";
import type { ResumeSummary } from "@/types/candidate";

export default function CandidateDashboardPage() {
  const { candidate } = useCandidateAuth();
  const [resumes, setResumes] = useState<ResumeSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    async function fetchResumes() {
      try {
        const response = await candidateApiClient.get<ResumeSummary[]>(
          "/candidate/resumes"
        );
        setResumes(response.data);
      } catch (err) {
        if (err instanceof ApiRequestError) {
          setError(err.message || "Failed to load resumes.");
        } else {
          setError("Failed to load resumes.");
        }
      } finally {
        setLoading(false);
      }
    }

    fetchResumes();
  }, []);

  return (
    <div>
      {/* Welcome header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">
          Welcome back{candidate ? `, ${candidate.name}` : ""}
        </h1>
        <p className="mt-1 text-sm text-gray-500">
          Manage your resumes and profile from here.
        </p>
      </div>

      {/* Quick links */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <Link
          href="/candidate/profile"
          className="block p-4 bg-white rounded-lg border border-gray-200 hover:border-teal-300 hover:shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <div className="flex items-center gap-3">
            <div className="flex items-center justify-center h-10 w-10 rounded-lg bg-teal-50 text-teal-600">
              <svg
                className="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={1.5}
                stroke="currentColor"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                />
              </svg>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-900">Edit Profile</p>
              <p className="text-xs text-gray-500">
                Update your personal info and skills
              </p>
            </div>
          </div>
        </Link>

        <Link
          href="/candidate/resumes/new"
          className="block p-4 bg-white rounded-lg border border-gray-200 hover:border-teal-300 hover:shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <div className="flex items-center gap-3">
            <div className="flex items-center justify-center h-10 w-10 rounded-lg bg-teal-50 text-teal-600">
              <svg
                className="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={1.5}
                stroke="currentColor"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M12 4.5v15m7.5-7.5h-15"
                />
              </svg>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-900">
                Create New Resume
              </p>
              <p className="text-xs text-gray-500">
                Start building with AI assistance
              </p>
            </div>
          </div>
        </Link>

        <Link
          href="/candidate/resumes"
          className="block p-4 bg-white rounded-lg border border-gray-200 hover:border-teal-300 hover:shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-teal-500"
        >
          <div className="flex items-center gap-3">
            <div className="flex items-center justify-center h-10 w-10 rounded-lg bg-teal-50 text-teal-600">
              <svg
                className="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                strokeWidth={1.5}
                stroke="currentColor"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                />
              </svg>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-900">All Resumes</p>
              <p className="text-xs text-gray-500">
                View and manage your resumes
              </p>
            </div>
          </div>
        </Link>
      </div>

      {/* Resumes section */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900">Your Resumes</h2>
          <Link href="/candidate/resumes/new">
            <Button variant="primary" size="sm">
              Create New Resume
            </Button>
          </Link>
        </div>

        {loading && (
          <div className="flex items-center justify-center py-12">
            <div
              className="inline-block h-6 w-6 animate-spin rounded-full border-4 border-teal-600 border-r-transparent"
              role="status"
              aria-label="Loading resumes"
            />
            <span className="ml-2 text-sm text-gray-500">
              Loading resumes…
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

        {!loading && !error && resumes.length === 0 && (
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
                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
              />
            </svg>
            <h3 className="mt-4 text-sm font-medium text-gray-900">
              No resumes yet
            </h3>
            <p className="mt-1 text-sm text-gray-500">
              Get started by creating your first resume with AI assistance.
            </p>
            <div className="mt-6">
              <Link href="/candidate/resumes/new">
                <Button variant="primary" size="md">
                  Create Your First Resume
                </Button>
              </Link>
            </div>
          </div>
        )}

        {!loading && !error && resumes.length > 0 && (
          <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <ul role="list" className="divide-y divide-gray-200">
              {resumes.map((resume) => (
                <li key={resume.id}>
                  <Link
                    href={`/candidate/resumes/${resume.id}`}
                    className="block px-4 py-4 hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-inset focus:ring-teal-500"
                  >
                    <div className="flex items-center justify-between">
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-gray-900 truncate">
                          {resume.title}
                        </p>
                        <div className="mt-1 flex items-center gap-3 text-xs text-gray-500">
                          <span className="capitalize">
                            {resume.template_slug} template
                          </span>
                          <span>·</span>
                          <span>
                            Updated{" "}
                            {new Date(resume.updated_at).toLocaleDateString()}
                          </span>
                        </div>
                      </div>
                      <div className="ml-4 flex-shrink-0">
                        <span
                          className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                            resume.is_complete
                              ? "bg-green-100 text-green-700"
                              : "bg-yellow-100 text-yellow-700"
                          }`}
                        >
                          {resume.is_complete ? "Complete" : "Draft"}
                        </span>
                      </div>
                    </div>
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </div>
  );
}
