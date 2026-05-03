"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { fetchApplicationDetail } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";
import type { ApplicationDetail, ResumeContent } from "@/types/candidate";

// ---------------------------------------------------------------------------
// Pipeline Progress Bar
// ---------------------------------------------------------------------------

function PipelineProgressBar({
  allStages,
  currentStage,
}: {
  allStages: ApplicationDetail["all_stages"];
  currentStage: ApplicationDetail["pipeline_stage"];
}) {
  if (!allStages || allStages.length === 0) return null;

  const sorted = [...allStages].sort((a, b) => a.sort_order - b.sort_order);
  const currentIdx = currentStage
    ? sorted.findIndex((s) => s.name === currentStage.name)
    : -1;

  return (
    <div className="w-full" aria-label={`Pipeline progress: ${currentStage?.name ?? "Unknown"}`}>
      <div className="flex items-center gap-1">
        {sorted.map((stage, idx) => {
          const isActive = idx <= currentIdx;
          const isCurrent = idx === currentIdx;
          return (
            <div key={stage.name} className="flex-1 flex flex-col items-center gap-1">
              <div
                className={`h-2 w-full rounded-full ${
                  isActive ? "bg-teal-500" : "bg-gray-200"
                }`}
              />
              <span
                className={`text-xs truncate max-w-full ${
                  isCurrent ? "font-semibold text-teal-700" : "text-gray-500"
                }`}
              >
                {stage.name}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Stage Timeline
// ---------------------------------------------------------------------------

function StageTimeline({
  transitions,
}: {
  transitions: ApplicationDetail["transitions"];
}) {
  if (!transitions || transitions.length === 0) {
    return (
      <p className="text-sm text-gray-500">No stage transitions recorded yet.</p>
    );
  }

  return (
    <ol className="relative border-l-2 border-gray-200 ml-3 space-y-4">
      {transitions.map((t, idx) => (
        <li key={idx} className="ml-6">
          <span className="absolute -left-2 flex h-4 w-4 items-center justify-center rounded-full bg-teal-500 ring-4 ring-white" />
          <div>
            <p className="text-sm font-medium text-gray-900">
              {t.from_stage} → {t.to_stage}
            </p>
            <time className="text-xs text-gray-500">
              {new Date(t.moved_at).toLocaleDateString("en-US", {
                year: "numeric",
                month: "short",
                day: "numeric",
                hour: "2-digit",
                minute: "2-digit",
              })}
            </time>
          </div>
        </li>
      ))}
    </ol>
  );
}

// ---------------------------------------------------------------------------
// Resume Snapshot
// ---------------------------------------------------------------------------

function ResumeSnapshot({ content }: { content: ResumeContent }) {
  return (
    <div className="space-y-4 text-sm">
      {content.summary && (
        <div>
          <h4 className="font-medium text-gray-700 mb-1">Summary</h4>
          <p className="text-gray-600">{content.summary}</p>
        </div>
      )}

      {content.work_experience && content.work_experience.length > 0 && (
        <div>
          <h4 className="font-medium text-gray-700 mb-2">Work Experience</h4>
          <ul className="space-y-2">
            {content.work_experience.map((w, i) => (
              <li key={i} className="border-l-2 border-gray-200 pl-3">
                <p className="font-medium text-gray-900">{w.job_title}</p>
                <p className="text-gray-600">{w.company_name}</p>
                <p className="text-xs text-gray-500">
                  {w.start_date} — {w.end_date ?? "Present"}
                </p>
                {w.bullets && w.bullets.length > 0 && (
                  <ul className="list-disc list-inside mt-1 text-gray-600 text-xs">
                    {w.bullets.map((b, j) => (
                      <li key={j}>{b}</li>
                    ))}
                  </ul>
                )}
              </li>
            ))}
          </ul>
        </div>
      )}

      {content.education && content.education.length > 0 && (
        <div>
          <h4 className="font-medium text-gray-700 mb-2">Education</h4>
          <ul className="space-y-2">
            {content.education.map((e, i) => (
              <li key={i} className="border-l-2 border-gray-200 pl-3">
                <p className="font-medium text-gray-900">
                  {e.degree} in {e.field_of_study}
                </p>
                <p className="text-gray-600">{e.institution_name}</p>
                <p className="text-xs text-gray-500">
                  {e.start_date} — {e.end_date ?? "Present"}
                </p>
              </li>
            ))}
          </ul>
        </div>
      )}

      {content.skills && content.skills.length > 0 && (
        <div>
          <h4 className="font-medium text-gray-700 mb-2">Skills</h4>
          <div className="flex flex-wrap gap-2">
            {content.skills.map((s, i) => (
              <span
                key={i}
                className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700"
              >
                {s}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function ApplicationDetailPage() {
  const params = useParams();
  const id = params.id as string;

  const [application, setApplication] = useState<ApplicationDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    async function load() {
      try {
        const response = await fetchApplicationDetail(id);
        setApplication(response.data);
      } catch (err) {
        setError(
          err instanceof ApiRequestError
            ? err.message
            : "Failed to load application details."
        );
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [id]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div
          className="inline-block h-6 w-6 animate-spin rounded-full border-4 border-teal-600 border-r-transparent"
          role="status"
          aria-label="Loading application details"
        />
        <span className="ml-2 text-sm text-gray-500">Loading application details…</span>
      </div>
    );
  }

  if (error || !application) {
    return (
      <div>
        <Link
          href="/candidate/applications"
          className="inline-flex items-center gap-1 text-sm text-teal-600 hover:text-teal-800 mb-4"
        >
          ← Back to Applications
        </Link>
        <div
          role="alert"
          className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700"
        >
          {error || "Application not found."}
        </div>
      </div>
    );
  }

  const appliedDate = new Date(application.applied_at).toLocaleDateString(
    "en-US",
    { year: "numeric", month: "long", day: "numeric" }
  );

  return (
    <div>
      {/* Back link */}
      <Link
        href="/candidate/applications"
        className="inline-flex items-center gap-1 text-sm text-teal-600 hover:text-teal-800 mb-6"
      >
        ← Back to Applications
      </Link>

      {/* Job Info */}
      <section className="bg-white rounded-lg border border-gray-200 p-6 mb-6">
        <h1 className="text-xl font-bold text-gray-900">{application.job_title}</h1>
        <p className="text-sm text-gray-600 mt-1">{application.company_name}</p>
        <div className="flex flex-wrap gap-3 mt-3 text-xs text-gray-500">
          {application.location && (
            <span className="inline-flex items-center gap-1">
              <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
              </svg>
              {application.location}
            </span>
          )}
          {application.employment_type && (
            <span className="inline-flex items-center gap-1 capitalize">
              <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
              </svg>
              {application.employment_type.replace("-", " ")}
            </span>
          )}
          <span>Applied {appliedDate}</span>
        </div>
      </section>

      {/* Pipeline Progress */}
      <section className="bg-white rounded-lg border border-gray-200 p-6 mb-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Pipeline Progress</h2>
        <PipelineProgressBar
          allStages={application.all_stages}
          currentStage={application.pipeline_stage}
        />
      </section>

      {/* Stage Timeline */}
      <section className="bg-white rounded-lg border border-gray-200 p-6 mb-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Stage History</h2>
        <StageTimeline transitions={application.transitions} />
      </section>

      {/* Resume Snapshot */}
      {application.resume_snapshot && (
        <section className="bg-white rounded-lg border border-gray-200 p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Submitted Resume</h2>
          <ResumeSnapshot content={application.resume_snapshot} />
        </section>
      )}
    </div>
  );
}
