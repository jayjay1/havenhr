"use client";

import React, { useCallback, useEffect, useRef, useState } from "react";
import { fetchTransitionHistory } from "@/lib/jobApi";
import type { StageTransition } from "@/types/job";
import {
  useKanban,
  type KanbanApplication,
  type KanbanStage,
} from "./KanbanProvider";
import { useAuth } from "@/contexts/AuthContext";
import { ScheduleInterviewModal } from "@/components/interviews/ScheduleInterviewModal";
import { InterviewList } from "@/components/interviews/InterviewList";
import { ScorecardSummarySection } from "@/components/pipeline/ScorecardSummarySection";

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface SlideOverPanelProps {
  applicationId: string;
  stages: KanbanStage[];
  onClose: () => void;
  onMoveApplication: (appId: string, stageId: string) => void;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatTimestamp(iso: string): string {
  return new Date(iso).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function findApplication(
  stages: KanbanStage[],
  appId: string
): { app: KanbanApplication; stageId: string } | null {
  for (const stage of stages) {
    const app = stage.applications.find((a) => a.id === appId);
    if (app) return { app, stageId: stage.id };
  }
  return null;
}

// ---------------------------------------------------------------------------
// Loading skeleton
// ---------------------------------------------------------------------------

function PanelSkeleton() {
  return (
    <div className="p-6 space-y-6 animate-pulse" data-testid="panel-skeleton">
      {/* Name */}
      <div className="h-6 w-48 bg-gray-200 rounded" />
      {/* Email */}
      <div className="h-4 w-56 bg-gray-200 rounded" />
      {/* Applied date */}
      <div className="h-4 w-32 bg-gray-200 rounded" />
      {/* Resume link */}
      <div className="h-4 w-24 bg-gray-200 rounded" />
      {/* Notes */}
      <div className="h-20 w-full bg-gray-200 rounded" />
      {/* Timeline */}
      <div className="space-y-3">
        <div className="h-4 w-40 bg-gray-200 rounded" />
        <div className="h-4 w-56 bg-gray-200 rounded" />
        <div className="h-4 w-48 bg-gray-200 rounded" />
      </div>
      {/* Actions */}
      <div className="flex gap-2">
        <div className="h-9 w-28 bg-gray-200 rounded" />
        <div className="h-9 w-20 bg-gray-200 rounded" />
        <div className="h-9 w-24 bg-gray-200 rounded" />
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// SlideOverPanel
// ---------------------------------------------------------------------------

export function SlideOverPanel({
  applicationId,
  stages,
  onClose,
  onMoveApplication,
}: SlideOverPanelProps) {
  const { state } = useKanban();
  const { hasPermission } = useAuth();
  const [transitions, setTransitions] = useState<StageTransition[]>([]);
  const [isLoadingHistory, setIsLoadingHistory] = useState(true);
  const [moveTarget, setMoveTarget] = useState("");
  const [showScheduleModal, setShowScheduleModal] = useState(false);
  const [interviewListKey, setInterviewListKey] = useState(0);

  const canManageApplications = hasPermission("applications.manage");

  const panelRef = useRef<HTMLDivElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const headingId = `slide-over-heading-${applicationId}`;

  // Find the application in the current kanban state
  const found = findApplication(state.stages, applicationId);

  // Fetch transition history on mount
  useEffect(() => {
    let cancelled = false;

    async function loadHistory() {
      setIsLoadingHistory(true);
      try {
        const response = await fetchTransitionHistory(applicationId);
        if (!cancelled) {
          // Sort by moved_at ascending
          const sorted = [...response.data].sort(
            (a, b) =>
              new Date(a.moved_at).getTime() - new Date(b.moved_at).getTime()
          );
          setTransitions(sorted);
        }
      } catch {
        // Silently handle — transitions will be empty
        if (!cancelled) {
          setTransitions([]);
        }
      } finally {
        if (!cancelled) {
          setIsLoadingHistory(false);
        }
      }
    }

    loadHistory();
    return () => {
      cancelled = true;
    };
  }, [applicationId]);

  // Focus the close button on open
  useEffect(() => {
    closeButtonRef.current?.focus();
  }, []);

  // Close on Escape key
  useEffect(() => {
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") {
        onClose();
      }
    }
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [onClose]);

  // Focus trap: Tab cycles within panel
  useEffect(() => {
    function handleTab(e: KeyboardEvent) {
      if (e.key !== "Tab" || !panelRef.current) return;

      const focusable = panelRef.current.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
      );
      if (focusable.length === 0) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }
    document.addEventListener("keydown", handleTab);
    return () => document.removeEventListener("keydown", handleTab);
  }, []);

  // Click outside to close (desktop only)
  const handleBackdropClick = useCallback(
    (e: React.MouseEvent<HTMLDivElement>) => {
      if (e.target === e.currentTarget) {
        onClose();
      }
    },
    [onClose]
  );

  // Quick action handlers
  const handleMoveToStage = useCallback(() => {
    if (moveTarget) {
      onMoveApplication(applicationId, moveTarget);
      setMoveTarget("");
    }
  }, [applicationId, moveTarget, onMoveApplication]);

  const handleReject = useCallback(() => {
    const rejectedStage = stages.find(
      (s) => s.name.toLowerCase() === "rejected"
    );
    if (rejectedStage) {
      onMoveApplication(applicationId, rejectedStage.id);
    }
  }, [applicationId, stages, onMoveApplication]);

  const handleShortlist = useCallback(() => {
    if (!found) return;
    const currentStage = stages.find((s) => s.id === found.stageId);
    if (!currentStage) return;

    // Find the next stage by sort_order
    const sortedStages = [...stages].sort(
      (a, b) => a.sort_order - b.sort_order
    );
    const currentIndex = sortedStages.findIndex(
      (s) => s.id === currentStage.id
    );
    if (currentIndex >= 0 && currentIndex < sortedStages.length - 1) {
      const nextStage = sortedStages[currentIndex + 1];
      onMoveApplication(applicationId, nextStage.id);
    }
  }, [applicationId, found, stages, onMoveApplication]);

  // If application not found, render nothing
  if (!found) {
    return null;
  }

  const { app, stageId: currentStageId } = found;
  const currentStage = stages.find((s) => s.id === currentStageId);
  const otherStages = stages.filter((s) => s.id !== currentStageId);

  // Check if shortlist is possible (there's a next stage)
  const sortedStages = [...stages].sort(
    (a, b) => a.sort_order - b.sort_order
  );
  const currentIndex = sortedStages.findIndex((s) => s.id === currentStageId);
  const hasNextStage =
    currentIndex >= 0 && currentIndex < sortedStages.length - 1;
  const nextStageName = hasNextStage
    ? sortedStages[currentIndex + 1].name
    : null;

  return (
    <>
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/50 z-40"
        onClick={handleBackdropClick}
        data-testid="slide-over-backdrop"
      />

      {/* Panel */}
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={headingId}
        className="fixed z-50 bg-white shadow-xl overflow-y-auto
          inset-0 md:inset-y-0 md:right-0 md:left-auto md:w-[400px]"
      >
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          {/* Mobile: back button */}
          <button
            type="button"
            onClick={onClose}
            className="md:hidden inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900"
            aria-label="Back"
          >
            <svg
              className="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={2}
              stroke="currentColor"
              aria-hidden="true"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M15.75 19.5L8.25 12l7.5-7.5"
              />
            </svg>
            Back
          </button>

          <h2
            id={headingId}
            className="text-lg font-semibold text-gray-900 truncate"
          >
            {app.candidate_name}
          </h2>

          {/* Desktop: close button */}
          <button
            ref={closeButtonRef}
            type="button"
            onClick={onClose}
            className="hidden md:inline-flex items-center justify-center h-8 w-8 rounded-md text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
            aria-label="Close panel"
          >
            <svg
              className="h-5 w-5"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={2}
              stroke="currentColor"
              aria-hidden="true"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        {/* Content */}
        {isLoadingHistory ? (
          <PanelSkeleton />
        ) : (
          <div className="p-6 space-y-6">
            {/* Candidate info */}
            <section>
              <p className="text-sm text-gray-600">{app.candidate_email}</p>
              <p className="text-sm text-gray-500 mt-1">
                Applied:{" "}
                {new Date(app.applied_at).toLocaleDateString("en-US", {
                  month: "short",
                  day: "numeric",
                  year: "numeric",
                })}
              </p>
              {currentStage && (
                <p className="text-sm text-gray-500 mt-1">
                  Current stage:{" "}
                  <span className="font-medium text-gray-700">
                    {currentStage.name}
                  </span>
                </p>
              )}
            </section>

            {/* Resume link */}
            <section>
              <h3 className="text-sm font-medium text-gray-900 mb-1">
                Resume
              </h3>
              <a
                href={`/candidates/${applicationId}/resume`}
                className="text-sm text-blue-600 hover:text-blue-800 underline"
              >
                View resume
              </a>
            </section>

            {/* Notes area */}
            <section>
              <h3 className="text-sm font-medium text-gray-900 mb-1">Notes</h3>
              <textarea
                readOnly
                placeholder="Notes will be available in a future update."
                className="w-full h-20 text-sm border border-gray-300 rounded-md p-2 bg-gray-50 text-gray-500 resize-none"
                aria-label="Notes"
              />
            </section>

            {/* Stage history timeline */}
            <section>
              <h3 className="text-sm font-medium text-gray-900 mb-3">
                Stage History
              </h3>
              {transitions.length === 0 ? (
                <p className="text-sm text-gray-500">
                  No stage transitions recorded.
                </p>
              ) : (
                <ol className="space-y-3" data-testid="transition-timeline">
                  {transitions.map((t) => (
                    <li
                      key={t.id}
                      className="relative pl-6 border-l-2 border-gray-200"
                    >
                      <div className="absolute left-[-5px] top-1 h-2 w-2 rounded-full bg-blue-500" />
                      <p className="text-sm font-medium text-gray-800">
                        {t.from_stage
                          ? `${t.from_stage.name} → ${t.to_stage.name}`
                          : `Initial → ${t.to_stage.name}`}
                      </p>
                      <p className="text-xs text-gray-500">
                        by {t.moved_by.name} · {formatTimestamp(t.moved_at)}
                      </p>
                    </li>
                  ))}
                </ol>
              )}
            </section>

            {/* Quick actions */}
            <section>
              <h3 className="text-sm font-medium text-gray-900 mb-3">
                Quick Actions
              </h3>
              <div className="space-y-3">
                {/* Move to dropdown */}
                <div className="flex items-center gap-2">
                  <select
                    value={moveTarget}
                    onChange={(e) => setMoveTarget(e.target.value)}
                    className="flex-1 text-sm border border-gray-300 rounded-md px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    aria-label="Move to stage"
                  >
                    <option value="">Move to…</option>
                    {otherStages.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                  </select>
                  <button
                    type="button"
                    onClick={handleMoveToStage}
                    disabled={!moveTarget}
                    className="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                  >
                    Move
                  </button>
                </div>

                {/* Reject and Shortlist buttons */}
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={handleReject}
                    className="px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1"
                  >
                    Reject
                  </button>
                  {hasNextStage && (
                    <button
                      type="button"
                      onClick={handleShortlist}
                      className="px-3 py-1.5 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-md hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1"
                    >
                      Shortlist → {nextStageName}
                    </button>
                  )}
                </div>
              </div>
            </section>

            {/* Interviews section */}
            <section>
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-medium text-gray-900">
                  Interviews
                </h3>
                {canManageApplications && (
                  <button
                    type="button"
                    onClick={() => setShowScheduleModal(true)}
                    className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Schedule Interview
                  </button>
                )}
              </div>
              <InterviewList
                key={interviewListKey}
                applicationId={applicationId}
                canManage={canManageApplications}
              />
            </section>

            {/* Scorecard Summary section */}
            <section>
              <h3 className="text-sm font-medium text-gray-900 mb-3">
                Scorecard Summary
              </h3>
              <ScorecardSummarySection applicationId={applicationId} />
            </section>
          </div>
        )}
      </div>

      {/* Schedule Interview Modal */}
      {showScheduleModal && (
        <ScheduleInterviewModal
          applicationId={applicationId}
          onClose={() => setShowScheduleModal(false)}
          onScheduled={() => setInterviewListKey((k) => k + 1)}
        />
      )}
    </>
  );
}
