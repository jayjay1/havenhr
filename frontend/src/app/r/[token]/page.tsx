"use client";

import { useState, useEffect } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import type { ResumeContent } from "@/types/candidate";

const BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8080/api/v1";

interface PublicResumeData {
  title: string;
  template_slug: string;
  content: ResumeContent;
}

export default function PublicResumePage() {
  const params = useParams();
  const token = params.token as string;

  const [resume, setResume] = useState<PublicResumeData | null>(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    async function fetchPublicResume() {
      try {
        const response = await fetch(`${BASE_URL}/public/resumes/${token}`, {
          headers: { Accept: "application/json" },
        });
        if (response.status === 404) {
          setNotFound(true);
          return;
        }
        if (!response.ok) {
          setNotFound(true);
          return;
        }
        const json = await response.json();
        setResume(json.data);
      } catch {
        setNotFound(true);
      } finally {
        setLoading(false);
      }
    }
    fetchPublicResume();
  }, [token]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div
            className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-teal-600 border-r-transparent"
            role="status"
            aria-label="Loading resume"
          />
          <p className="mt-2 text-sm text-gray-500">Loading resume…</p>
        </div>
      </div>
    );
  }

  if (notFound || !resume) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center max-w-md px-4">
          <svg
            className="mx-auto h-16 w-16 text-gray-300"
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
          <h1 className="mt-4 text-xl font-bold text-gray-900">
            Resume Not Found
          </h1>
          <p className="mt-2 text-sm text-gray-500">
            This resume link may have expired or been deactivated by the owner.
          </p>
          <Link
            href="/"
            className="mt-6 inline-block text-sm font-medium text-teal-600 hover:text-teal-800"
          >
            Go to HavenHR →
          </Link>
        </div>
      </div>
    );
  }

  const { content, template_slug } = resume;
  const accentBorder = templateAccent(template_slug);

  return (
    <div className="min-h-screen bg-gray-50 py-8 px-4">
      <div className="max-w-3xl mx-auto">
        {/* Branding */}
        <div className="text-center mb-6">
          <span className="text-sm text-gray-400">
            Shared via{" "}
            <Link href="/" className="text-teal-600 hover:underline">
              HavenHR
            </Link>
          </span>
        </div>

        {/* Resume Card */}
        <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6 sm:p-10">
          {/* Personal Info */}
          {content.personal_info && (
            <div className={`border-b-2 pb-4 mb-6 ${accentBorder}`}>
              <h1 className="text-2xl font-bold text-gray-900">
                {content.personal_info.name}
              </h1>
              <div className="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-sm text-gray-600">
                {content.personal_info.email && (
                  <span>{content.personal_info.email}</span>
                )}
                {content.personal_info.phone && (
                  <span>{content.personal_info.phone}</span>
                )}
                {content.personal_info.location && (
                  <span>{content.personal_info.location}</span>
                )}
              </div>
              <div className="flex flex-wrap gap-x-4 mt-1 text-sm text-gray-500">
                {content.personal_info.linkedin_url && (
                  <a
                    href={content.personal_info.linkedin_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-teal-600 hover:underline"
                  >
                    LinkedIn
                  </a>
                )}
                {content.personal_info.portfolio_url && (
                  <a
                    href={content.personal_info.portfolio_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-teal-600 hover:underline"
                  >
                    Portfolio
                  </a>
                )}
              </div>
            </div>
          )}

          {/* Summary */}
          {content.summary && (
            <div className="mb-6">
              <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-2">
                Professional Summary
              </h2>
              <p className="text-sm text-gray-700 leading-relaxed">
                {content.summary}
              </p>
            </div>
          )}

          {/* Work Experience */}
          {content.work_experience && content.work_experience.length > 0 && (
            <div className="mb-6">
              <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
                Work Experience
              </h2>
              <div className="space-y-4">
                {content.work_experience.map((job, i) => (
                  <div key={i}>
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                      <p className="text-sm font-medium text-gray-900">
                        {job.job_title}
                      </p>
                      <p className="text-xs text-gray-500">
                        {job.start_date} — {job.end_date ?? "Present"}
                      </p>
                    </div>
                    <p className="text-sm text-gray-600">{job.company_name}</p>
                    {job.bullets && job.bullets.length > 0 && (
                      <ul className="mt-1 list-disc list-inside space-y-0.5">
                        {job.bullets.map((bullet, j) => (
                          <li key={j} className="text-sm text-gray-700">
                            {bullet}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Education */}
          {content.education && content.education.length > 0 && (
            <div className="mb-6">
              <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
                Education
              </h2>
              <div className="space-y-3">
                {content.education.map((edu, i) => (
                  <div key={i}>
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                      <p className="text-sm font-medium text-gray-900">
                        {edu.degree} in {edu.field_of_study}
                      </p>
                      <p className="text-xs text-gray-500">
                        {edu.start_date} — {edu.end_date ?? "Present"}
                      </p>
                    </div>
                    <p className="text-sm text-gray-600">
                      {edu.institution_name}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Skills */}
          {content.skills && content.skills.length > 0 && (
            <div>
              <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-2">
                Skills
              </h2>
              <div className="flex flex-wrap gap-2">
                {content.skills.map((skill, i) => (
                  <span
                    key={i}
                    className="inline-block rounded-full bg-gray-100 text-gray-700 px-3 py-1 text-xs"
                  >
                    {skill}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="text-center mt-6">
          <p className="text-xs text-gray-400">
            Built with{" "}
            <Link href="/" className="text-teal-600 hover:underline">
              HavenHR Resume Builder
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}

function templateAccent(slug: string): string {
  switch (slug) {
    case "modern":
      return "border-teal-500";
    case "professional":
      return "border-blue-500";
    case "creative":
      return "border-purple-500";
    default:
      return "border-gray-400";
  }
}
