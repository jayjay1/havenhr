"use client";

import React, { useCallback, useMemo, useRef, useState } from "react";
import {
  DndContext,
  PointerSensor,
  KeyboardSensor,
  TouchSensor,
  useSensors,
  useSensor,
  DragEndEvent,
  DragStartEvent,
} from "@dnd-kit/core";
import {
  useKanban,
  type KanbanStage,
  type KanbanApplication,
} from "./KanbanProvider";
import { StageColumn } from "./StageColumn";
import { CandidateCard } from "./CandidateCard";
import { PipelineSearchBar } from "./PipelineSearchBar";
import { SlideOverPanel } from "./SlideOverPanel";
import { BulkActionToolbar } from "./BulkActionToolbar";
import { moveApplication } from "@/lib/jobApi";

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface KanbanBoardProps {
  jobId: string;
  canManage: boolean;
  canCustomize: boolean;
  onRetry: () => void;
}

// ---------------------------------------------------------------------------
// Client-side filtering helpers
// ---------------------------------------------------------------------------

function filterApplications(
  apps: KanbanApplication[],
  searchQuery: string
): KanbanApplication[] {
  if (!searchQuery.trim()) return apps;
  const q = searchQuery.toLowerCase();
  return apps.filter(
    (a) =>
      a.candidate_name.toLowerCase().includes(q) ||
      a.candidate_email.toLowerCase().includes(q)
  );
}

function sortApplications(
  apps: KanbanApplication[],
  sortBy: "applied_at_desc" | "applied_at_asc" | "candidate_name"
): KanbanApplication[] {
  const sorted = [...apps];
  switch (sortBy) {
    case "applied_at_desc":
      sorted.sort(
        (a, b) =>
          new Date(b.applied_at).getTime() - new Date(a.applied_at).getTime()
      );
      break;
    case "applied_at_asc":
      sorted.sort(
        (a, b) =>
          new Date(a.applied_at).getTime() - new Date(b.applied_at).getTime()
      );
      break;
    case "candidate_name":
      sorted.sort((a, b) =>
        a.candidate_name.localeCompare(b.candidate_name)
      );
      break;
  }
  return sorted;
}

// ---------------------------------------------------------------------------
// Loading skeleton
// ---------------------------------------------------------------------------

function LoadingSkeleton() {
  const skeletonColumns = Array.from({ length: 5 }, (_, i) => i);
  const skeletonCards = Array.from({ length: 3 }, (_, i) => i);

  return (
    <div
      className="flex gap-4 overflow-x-auto pb-4 px-1"
      role="status"
      aria-label="Loading pipeline data"
    >
      {skeletonColumns.map((col) => (
        <div
          key={col}
          className="min-w-[280px] w-[280px] bg-gray-50 rounded-lg shrink-0"
        >
          {/* Header skeleton */}
          <div className="px-3 py-2 border-b border-gray-200 rounded-t-lg border-t-4 border-t-gray-300">
            <div className="flex items-center justify-between">
              <div className="h-4 w-24 bg-gray-200 rounded animate-pulse" />
              <div className="h-5 w-6 bg-gray-200 rounded-full animate-pulse" />
            </div>
          </div>
          {/* Card skeletons */}
          <div className="p-2 space-y-2">
            {skeletonCards.map((card) => (
              <div
                key={card}
                className="bg-white rounded-md border border-gray-200 p-3"
              >
                <div className="h-4 w-32 bg-gray-200 rounded animate-pulse" />
                <div className="h-3 w-40 bg-gray-200 rounded animate-pulse mt-1.5" />
                <div className="h-3 w-20 bg-gray-200 rounded animate-pulse mt-1.5" />
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Error state
// ---------------------------------------------------------------------------

interface ErrorStateProps {
  error: string;
  onRetry: () => void;
}

function ErrorState({ error, onRetry }: ErrorStateProps) {
  return (
    <div
      className="rounded-lg border border-red-200 bg-red-50 p-4"
      role="alert"
    >
      <div className="flex items-start gap-3">
        <svg
          className="h-5 w-5 text-red-600 mt-0.5 shrink-0"
          fill="none"
          viewBox="0 0 24 24"
          strokeWidth={1.5}
          stroke="currentColor"
          aria-hidden="true"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
          />
        </svg>
        <div className="flex-1">
          <h3 className="text-sm font-medium text-red-800">
            Failed to load pipeline
          </h3>
          <p className="text-sm text-red-700 mt-1">{error}</p>
          <button
            type="button"
            onClick={onRetry}
            className="mt-3 inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 transition-colors"
          >
            Retry
          </button>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Mobile navigation dots
// ---------------------------------------------------------------------------

interface StageDotsProps {
  stages: KanbanStage[];
  activeIndex: number;
  onDotClick: (index: number) => void;
}

function StageDots({ stages, activeIndex, onDotClick }: StageDotsProps) {
  return (
    <div
      className="flex justify-center gap-2 py-2 md:hidden"
      role="tablist"
      aria-label="Stage navigation"
    >
      {stages.map((stage, index) => (
        <button
          key={stage.id}
          type="button"
          role="tab"
          aria-selected={index === activeIndex}
          aria-label={`${stage.name} stage`}
          onClick={() => onDotClick(index)}
          className={`
            h-2 w-2 rounded-full transition-colors
            ${index === activeIndex ? "bg-blue-600" : "bg-gray-300"}
          `}
        />
      ))}
    </div>
  );
}

// ---------------------------------------------------------------------------
// KanbanBoard
// ---------------------------------------------------------------------------

export function KanbanBoard({
  jobId,
  canManage,
  canCustomize,
  onRetry,
}: KanbanBoardProps) {
  const { state, dispatch } = useKanban();
  const [draggingId, setDraggingId] = useState<string | null>(null);
  const [announcement, setAnnouncement] = useState("");
  const [activeStageIndex, setActiveStageIndex] = useState(0);
  const scrollContainerRef = useRef<HTMLDivElement>(null);

  // Sensors
  const pointerSensor = useSensor(PointerSensor, {
    activationConstraint: { distance: 8 },
  });
  const keyboardSensor = useSensor(KeyboardSensor);
  const touchSensor = useSensor(TouchSensor, {
    activationConstraint: { delay: 200, tolerance: 5 },
  });
  const sensors = useSensors(pointerSensor, keyboardSensor, touchSensor);

  // Apply client-side filtering
  const visibleStages = useMemo(() => {
    let stages = state.stages;

    // Stage filter
    if (state.stageFilter) {
      stages = stages.filter((s) => s.id === state.stageFilter);
    }

    // Apply search and sort to applications within each stage
    return stages.map((stage) => {
      let apps = filterApplications(stage.applications, state.searchQuery);
      apps = sortApplications(apps, state.sortBy);
      return { ...stage, applications: apps };
    });
  }, [state.stages, state.stageFilter, state.searchQuery, state.sortBy]);

  // Drag handlers
  const handleDragStart = useCallback(
    (event: DragStartEvent) => {
      const appId = String(event.active.id);
      setDraggingId(appId);

      // Find the app name for announcement
      for (const stage of state.stages) {
        const app = stage.applications.find((a) => a.id === appId);
        if (app) {
          setAnnouncement(
            `Picked up ${app.candidate_name} from ${stage.name}`
          );
          break;
        }
      }
    },
    [state.stages]
  );

  const handleDragEnd = useCallback(
    async (event: DragEndEvent) => {
      const { active, over } = event;
      setDraggingId(null);

      if (!over) {
        setAnnouncement("Drag cancelled");
        return;
      }

      const appId = String(active.id);
      const toStageId = String(over.id);

      // Find which stage the app is currently in
      let fromStageId: string | null = null;
      let appName = "";
      for (const stage of state.stages) {
        const app = stage.applications.find((a) => a.id === appId);
        if (app) {
          fromStageId = stage.id;
          appName = app.candidate_name;
          break;
        }
      }

      if (!fromStageId || fromStageId === toStageId) {
        setAnnouncement("Drag cancelled");
        return;
      }

      // Find target stage name for announcement
      const targetStage = state.stages.find((s) => s.id === toStageId);
      const targetStageName = targetStage?.name || "unknown stage";

      // Optimistic update
      dispatch({
        type: "MOVE_CARD_OPTIMISTIC",
        appId,
        fromStageId,
        toStageId,
      });
      setAnnouncement(`Moved ${appName} to ${targetStageName}`);

      // API call
      try {
        await moveApplication(appId, toStageId);
        dispatch({ type: "MOVE_CARD_CONFIRMED" });
      } catch {
        dispatch({ type: "MOVE_CARD_ROLLBACK" });
        setAnnouncement(
          `Failed to move ${appName}. Returned to original stage.`
        );
      }
    },
    [state.stages, dispatch]
  );

  const handleDragCancel = useCallback(() => {
    setDraggingId(null);
    setAnnouncement("Drag cancelled");
  }, []);

  // Mobile dot navigation
  const handleDotClick = useCallback((index: number) => {
    setActiveStageIndex(index);
    if (scrollContainerRef.current) {
      const columns = scrollContainerRef.current.children;
      if (columns[index]) {
        (columns[index] as HTMLElement).scrollIntoView({
          behavior: "smooth",
          inline: "center",
          block: "nearest",
        });
      }
    }
  }, []);

  // Handle scroll to update active dot
  const handleScroll = useCallback(() => {
    if (!scrollContainerRef.current) return;
    const container = scrollContainerRef.current;
    const scrollLeft = container.scrollLeft;
    const columnWidth = 280 + 16; // min-w + gap
    const newIndex = Math.round(scrollLeft / columnWidth);
    setActiveStageIndex(
      Math.min(Math.max(newIndex, 0), visibleStages.length - 1)
    );
  }, [visibleStages.length]);

  // Loading state
  if (state.isLoading) {
    return <LoadingSkeleton />;
  }

  // Error state
  if (state.error) {
    return <ErrorState error={state.error} onRetry={onRetry} />;
  }

  return (
    <div>
      {/* Search, filter, and sort bar */}
      <PipelineSearchBar jobId={jobId} stages={state.stages} />

      {/* ARIA live region for drag announcements */}
      <div
        role="status"
        aria-live="assertive"
        aria-atomic="true"
        className="sr-only"
      >
        {announcement}
      </div>

      <DndContext
        sensors={canManage ? sensors : undefined}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
        onDragCancel={handleDragCancel}
      >
        {/* Board container — horizontal scroll with snap on mobile */}
        <div
          ref={scrollContainerRef}
          onScroll={handleScroll}
          className="flex gap-4 overflow-x-auto pb-4 px-1 snap-x snap-mandatory md:snap-none"
          role="region"
          aria-label="Kanban pipeline board"
        >
          {visibleStages.map((stage) => (
            <StageColumn
              key={stage.id}
              stage={stage}
              applications={stage.applications}
              isOver={false}
              canManage={canManage}
              canCustomize={canCustomize}
              jobId={jobId}
            >
              {stage.applications.map((app) => (
                <CandidateCard
                  key={app.id}
                  application={app}
                  isSelected={state.selectedIds.has(app.id)}
                  isDragging={draggingId === app.id}
                  canManage={canManage}
                  onSelect={(appId) =>
                    dispatch({ type: "TOGGLE_SELECT", appId })
                  }
                  onClick={(appId) =>
                    dispatch({ type: "OPEN_SLIDE_OVER", appId })
                  }
                />
              ))}
            </StageColumn>
          ))}
          {visibleStages.length === 0 && (
            <p className="text-sm text-gray-500 py-8 text-center w-full">
              No stages to display.
            </p>
          )}
        </div>
      </DndContext>

      {/* Bulk action toolbar */}
      <BulkActionToolbar stages={state.stages} canManage={canManage} />

      {/* Mobile stage navigation dots */}
      {visibleStages.length > 1 && (
        <StageDots
          stages={visibleStages}
          activeIndex={activeStageIndex}
          onDotClick={handleDotClick}
        />
      )}

      {/* Slide-over panel */}
      {state.slideOverAppId && (
        <SlideOverPanel
          applicationId={state.slideOverAppId}
          stages={state.stages}
          onClose={() => dispatch({ type: "CLOSE_SLIDE_OVER" })}
          onMoveApplication={async (appId, toStageId) => {
            // Find the current stage of the application
            let fromStageId: string | null = null;
            for (const stage of state.stages) {
              if (stage.applications.find((a) => a.id === appId)) {
                fromStageId = stage.id;
                break;
              }
            }
            if (!fromStageId || fromStageId === toStageId) return;

            // Optimistic update
            dispatch({
              type: "MOVE_CARD_OPTIMISTIC",
              appId,
              fromStageId,
              toStageId,
            });

            // API call
            try {
              await moveApplication(appId, toStageId);
              dispatch({ type: "MOVE_CARD_CONFIRMED" });
            } catch {
              dispatch({ type: "MOVE_CARD_ROLLBACK" });
            }
          }}
        />
      )}
    </div>
  );
}
