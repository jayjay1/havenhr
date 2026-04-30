"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { candidateApiClient } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";
import { Button } from "@/components/ui/Button";
import type { ResumeSummary } from "@/types/candidate";

export default function ResumesListPage() {
  const [resumes, setResumes] = useState<ResumeSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    async function fetchResumes() {
      try {
        const response =
          await candidateApiClient.get<ResumeSummary[]>("/candidate/resumes");
        setResumes(response.data);
      } catch (err) {
        setError(
          err instanceof ApiRequestError
            ? err.message
            : "Failed to load resumes."
        );
      } finally {
        setLoading(false);
      }
    }
    fetchResumes();
  }, []);

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">My Resumes</h1>
          <p className="mt-1 text-sm text-gray-500">
            Create, manage, and export your resumes.
          </p>
        </div>
        <Link href="/candidate/resumes/new">
          <Button variant="primary">Create New Resume</Button>
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
            Get started by creating your first resume.
          </p>
          <div className="mt-6">
            <Link href="/candidate/resumes/new">
              <Button variant="primary">Create Your First Resume</Button>
            </Link>
          </div>
        </div>
      )}

      {!loading && !error && resumes.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {resumes.map((resume) => (
            <Link
              key={resume.id}
              href={`/candidate/resumes/${resume.id}`}
              className="block bg-white rounded-lg border border-gray-200 p-5 hover:border-teal-300 hover:shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-teal-500"
            >
              {/* Template color bar */}
              <div
                className={`h-1.5 w-full rounded-full mb-4 ${templateColor(resume.template_slug)}`}
                aria-hidden="true"
              />
              <h3 className="text-sm font-semibold text-gray-900 truncate">
                {resume.title}
              </h3>
              <div className="mt-2 flex items-center justify-between">
                <span className="text-xs text-gray-500 capitalize">
                  {resume.template_slug} template
                </span>
                <span
                  className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                    resume.is_complete
                      ? "bg-green-100 text-green-700"
                      : "bg-yellow-100 text-yellow-700"
                  }`}
                >
                  {resume.is_complete ? "Complete" : "Draft"}
                </span>
              </div>
              <p className="mt-2 text-xs text-gray-400">
                Updated {new Date(resume.updated_at).toLocaleDateString()}
              </p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

function templateColor(slug: string): string {
  switch (slug) {
    case "modern":
      return "bg-teal-500";
    case "professional":
      return "bg-blue-500";
    case "creative":
      return "bg-purple-500";
    default:
      return "bg-gray-400";
  }
}
