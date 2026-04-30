"use client";

import { useState, useEffect } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { candidateApiClient } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";
import { Button } from "@/components/ui/Button";
import type { ResumeDetail, ResumeContent } from "@/types/candidate";

// ---------------------------------------------------------------------------
// Resume Content Renderer
// ---------------------------------------------------------------------------

function ResumeRenderer({
  content,
  templateSlug,
}: {
  content: ResumeContent;
  templateSlug: string;
}) {
  const accentColor = templateAccent(templateSlug);

  return (
    <div className="bg-white rounded-lg border border-gray-200 p-6 sm:p-8 shadow-sm">
      {/* Personal Info Header */}
      {content.personal_info && (
        <div className={`border-b-2 pb-4 mb-6 ${accentColor}`}>
          <h2 className="text-xl font-bold text-gray-900">
            {content.personal_info.name}
          </h2>
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
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-2">
            Professional Summary
          </h3>
          <p className="text-sm text-gray-700 leading-relaxed">
            {content.summary}
          </p>
        </div>
      )}

      {/* Work Experience */}
      {content.work_experience && content.work_experience.length > 0 && (
        <div className="mb-6">
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
            Work Experience
          </h3>
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
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
            Education
          </h3>
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
          <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-2">
            Skills
          </h3>
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

      {/* Empty state */}
      {!content.personal_info &&
        !content.summary &&
        (!content.work_experience || content.work_experience.length === 0) &&
        (!content.education || content.education.length === 0) &&
        (!content.skills || content.skills.length === 0) && (
          <p className="text-sm text-gray-500 text-center py-8">
            This resume has no content yet. Edit it to add your information.
          </p>
        )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main Resume Detail Page
// ---------------------------------------------------------------------------

export default function ResumeDetailPage() {
  const params = useParams();
  const router = useRouter();
  const resumeId = params.id as string;

  const [resume, setResume] = useState<ResumeDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [exporting, setExporting] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

  useEffect(() => {
    async function fetchResume() {
      try {
        const response = await candidateApiClient.get<ResumeDetail>(
          `/candidate/resumes/${resumeId}`
        );
        setResume(response.data);
      } catch (err) {
        setError(
          err instanceof ApiRequestError
            ? err.message
            : "Failed to load resume."
        );
      } finally {
        setLoading(false);
      }
    }
    fetchResume();
  }, [resumeId]);

  async function handleExportPdf() {
    setExporting(true);
    try {
      const response = await candidateApiClient.post<{ url: string }>(
        `/candidate/resumes/${resumeId}/export-pdf`
      );
      // Trigger download
      const link = document.createElement("a");
      link.href = response.data.url;
      link.download = `${resume?.title ?? "resume"}.pdf`;
      link.target = "_blank";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (err) {
      alert(
        err instanceof ApiRequestError
          ? err.message
          : "Failed to export PDF."
      );
    } finally {
      setExporting(false);
    }
  }

  async function handleDelete() {
    setDeleting(true);
    try {
      await candidateApiClient.del(`/candidate/resumes/${resumeId}`);
      router.push("/candidate/resumes");
    } catch (err) {
      alert(
        err instanceof ApiRequestError
          ? err.message
          : "Failed to delete resume."
      );
      setDeleting(false);
      setShowDeleteConfirm(false);
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div
          className="inline-block h-6 w-6 animate-spin rounded-full border-4 border-teal-600 border-r-transparent"
          role="status"
          aria-label="Loading resume"
        />
        <span className="ml-2 text-sm text-gray-500">Loading resume…</span>
      </div>
    );
  }

  if (error || !resume) {
    return (
      <div
        role="alert"
        className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700"
      >
        {error || "Resume not found."}
      </div>
    );
  }

  return (
    <div>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{resume.title}</h1>
          <div className="flex items-center gap-3 mt-1 text-sm text-gray-500">
            <span className="capitalize">{resume.template_slug} template</span>
            <span>·</span>
            <span
              className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                resume.is_complete
                  ? "bg-green-100 text-green-700"
                  : "bg-yellow-100 text-yellow-700"
              }`}
            >
              {resume.is_complete ? "Complete" : "Draft"}
            </span>
            <span>·</span>
            <span>
              Updated {new Date(resume.updated_at).toLocaleDateString()}
            </span>
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          <Link href={`/candidate/resumes/${resumeId}/edit`}>
            <Button variant="primary" size="sm">
              Edit
            </Button>
          </Link>
          <Button
            variant="secondary"
            size="sm"
            loading={exporting}
            onClick={handleExportPdf}
          >
            Export PDF
          </Button>
          {resume.public_link_token && resume.public_link_active && (
            <Button
              variant="secondary"
              size="sm"
              onClick={() => {
                const url = `${window.location.origin}/r/${resume.public_link_token}`;
                navigator.clipboard.writeText(url);
                alert("Public link copied to clipboard!");
              }}
            >
              Copy Share Link
            </Button>
          )}
          <Button
            variant="danger"
            size="sm"
            onClick={() => setShowDeleteConfirm(true)}
          >
            Delete
          </Button>
        </div>
      </div>

      {/* Delete confirmation dialog */}
      {showDeleteConfirm && (
        <div className="mb-6 rounded-md bg-red-50 border border-red-200 p-4">
          <p className="text-sm text-red-700 mb-3">
            Are you sure you want to delete &ldquo;{resume.title}&rdquo;? This
            action cannot be undone.
          </p>
          <div className="flex gap-2">
            <Button
              variant="danger"
              size="sm"
              loading={deleting}
              onClick={handleDelete}
            >
              Yes, Delete
            </Button>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setShowDeleteConfirm(false)}
            >
              Cancel
            </Button>
          </div>
        </div>
      )}

      {/* Resume content */}
      <ResumeRenderer
        content={resume.content}
        templateSlug={resume.template_slug}
      />
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
