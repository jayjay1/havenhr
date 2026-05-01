"use client";

import React, { useCallback, useRef, useState } from "react";
import { useDraggable } from "@dnd-kit/core";
import { useKanban, type KanbanApplication } from "./KanbanProvider";
import { BottomSheet } from "./BottomSheet";
import { moveApplication } from "@/lib/jobApi";

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

/** Minimum horizontal distance (px) to recognize a swipe. */
const SWIPE_THRESHOLD_PX = 50;

/** Maximum touch duration (ms) for a quick swipe. Longer presses are left to @dnd-kit. */
const SWIPE_MAX_DURATION_MS = 300;

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface CandidateCardProps {
  application: KanbanApplication;
  isSelected: boolean;
  isDragging: boolean;
  canManage: boolean;
  onSelect: (appId: string) => void;
  onClick: (appId: string) => void;
}

// ---------------------------------------------------------------------------
// CandidateCard
// ---------------------------------------------------------------------------

export function CandidateCard({
  application,
  isSelected,
  isDragging,
  canManage,
  onSelect,
  onClick,
}: CandidateCardProps) {
  const { state, dispatch } = useKanban();
  const bulkSelectionActive = state.selectedIds.size > 0;

  // ---- Swipe state ----
  const [showBottomSheet, setShowBottomSheet] = useState(false);
  const touchStartRef = useRef<{
    x: number;
    y: number;
    time: number;
  } | null>(null);

  const { attributes, listeners, setNodeRef, transform } = useDraggable({
    id: application.id,
    disabled: !canManage,
  });

  const style: React.CSSProperties = {
    ...(transform
      ? { transform: `translate(${transform.x}px, ${transform.y}px)` }
      : {}),
    opacity: isDragging ? 0.5 : 1,
  };

  const appliedDate = new Date(application.applied_at).toLocaleDateString(
    "en-US",
    { month: "short", day: "numeric", year: "numeric" }
  );

  const handleClick = useCallback(
    (e: React.MouseEvent) => {
      // Don't open slide-over when clicking the checkbox
      if ((e.target as HTMLElement).closest('input[type="checkbox"]')) {
        return;
      }
      onClick(application.id);
    },
    [onClick, application.id]
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        onClick(application.id);
        return;
      }

      // Arrow keys to navigate between cards within the column
      if (e.key === "ArrowDown" || e.key === "ArrowUp") {
        e.preventDefault();
        const currentEl = e.currentTarget as HTMLElement;
        const cards = Array.from(
          currentEl.parentElement?.querySelectorAll('[role="article"]') ?? []
        ) as HTMLElement[];
        const currentIndex = cards.indexOf(currentEl);
        if (currentIndex === -1) return;

        const nextIndex =
          e.key === "ArrowDown" ? currentIndex + 1 : currentIndex - 1;
        if (nextIndex >= 0 && nextIndex < cards.length) {
          cards[nextIndex].focus();
        }
        return;
      }

      // Ctrl+Arrow to move card to next/previous stage
      if (
        canManage &&
        e.ctrlKey &&
        (e.key === "ArrowRight" || e.key === "ArrowLeft")
      ) {
        e.preventDefault();
        const currentStageId = application.current_stage;
        const sortedStages = [...state.stages].sort(
          (a, b) => a.sort_order - b.sort_order
        );
        const currentStageIndex = sortedStages.findIndex(
          (s) => s.id === currentStageId
        );
        if (currentStageIndex === -1) return;

        const targetIndex =
          e.key === "ArrowRight"
            ? currentStageIndex + 1
            : currentStageIndex - 1;
        if (targetIndex < 0 || targetIndex >= sortedStages.length) return;

        const targetStage = sortedStages[targetIndex];

        // Optimistic move
        dispatch({
          type: "MOVE_CARD_OPTIMISTIC",
          appId: application.id,
          fromStageId: currentStageId,
          toStageId: targetStage.id,
        });

        // API call
        moveApplication(application.id, targetStage.id)
          .then(() => {
            dispatch({ type: "MOVE_CARD_CONFIRMED" });
          })
          .catch(() => {
            dispatch({ type: "MOVE_CARD_ROLLBACK" });
          });
        return;
      }
    },
    [onClick, application.id, application.current_stage, canManage, state.stages, dispatch]
  );

  const handleCheckboxChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      e.stopPropagation();
      onSelect(application.id);
    },
    [onSelect, application.id]
  );

  // ---- Swipe gesture handlers ----

  const handleTouchStart = useCallback(
    (e: React.TouchEvent) => {
      if (!canManage) return;
      const touch = e.touches[0];
      touchStartRef.current = {
        x: touch.clientX,
        y: touch.clientY,
        time: Date.now(),
      };
    },
    [canManage]
  );

  const handleTouchEnd = useCallback(
    (e: React.TouchEvent) => {
      if (!canManage || !touchStartRef.current) return;

      const touch = e.changedTouches[0];
      const deltaX = touch.clientX - touchStartRef.current.x;
      const deltaY = touch.clientY - touchStartRef.current.y;
      const elapsed = Date.now() - touchStartRef.current.time;

      touchStartRef.current = null;

      const absDeltaX = Math.abs(deltaX);
      const absDeltaY = Math.abs(deltaY);

      // Only recognize as a swipe if:
      // 1. Horizontal distance exceeds threshold
      // 2. Horizontal distance > vertical distance (not a scroll)
      // 3. Touch duration is short (quick swipe, not a long-press drag)
      if (
        absDeltaX >= SWIPE_THRESHOLD_PX &&
        absDeltaX > absDeltaY &&
        elapsed <= SWIPE_MAX_DURATION_MS
      ) {
        setShowBottomSheet(true);
      }
    },
    [canManage]
  );

  // ---- BottomSheet handlers ----

  const handleCloseBottomSheet = useCallback(() => {
    setShowBottomSheet(false);
  }, []);

  const handleSelectStage = useCallback(
    async (toStageId: string) => {
      setShowBottomSheet(false);

      const fromStageId = application.current_stage;

      // Optimistic update
      dispatch({
        type: "MOVE_CARD_OPTIMISTIC",
        appId: application.id,
        fromStageId,
        toStageId,
      });

      // API call
      try {
        await moveApplication(application.id, toStageId);
        dispatch({ type: "MOVE_CARD_CONFIRMED" });
      } catch {
        dispatch({ type: "MOVE_CARD_ROLLBACK" });
      }
    },
    [application.id, application.current_stage, dispatch]
  );

  return (
    <>
      <div
        ref={setNodeRef}
        style={style}
        {...listeners}
        {...attributes}
        className={`
          bg-white rounded-md border border-gray-200 p-3 shadow-sm
          ${canManage ? "cursor-grab active:cursor-grabbing" : "cursor-default"}
          hover:shadow-md transition-shadow
          min-h-[44px]
          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1
          ${isDragging ? "shadow-lg" : ""}
        `}
        role="article"
        aria-label={`${application.candidate_name}, applied ${appliedDate}`}
        tabIndex={0}
        onClick={handleClick}
        onKeyDown={handleKeyDown}
        onTouchStart={handleTouchStart}
        onTouchEnd={handleTouchEnd}
      >
        <div className="flex items-start gap-2">
          {/* Selection checkbox — visible when bulk selection mode is active */}
          {bulkSelectionActive && (
            <div className="flex items-center pt-0.5 shrink-0">
              <input
                type="checkbox"
                checked={isSelected}
                onChange={handleCheckboxChange}
                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 min-w-[16px] min-h-[16px]"
                aria-label={`Select ${application.candidate_name}`}
                onClick={(e) => e.stopPropagation()}
              />
            </div>
          )}

          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-gray-900 truncate">
              {application.candidate_name}
            </p>
            <p className="text-xs text-gray-500 truncate mt-0.5">
              {application.candidate_email}
            </p>
            <div className="flex items-center justify-between mt-1">
              <p className="text-xs text-gray-400">{appliedDate}</p>
              <a
                href={`/candidates/${application.id}/resume`}
                className="text-xs text-blue-600 hover:text-blue-800 hover:underline focus:outline-none focus:ring-1 focus:ring-blue-500 rounded"
                onClick={(e) => e.stopPropagation()}
                aria-label={`View resume for ${application.candidate_name}`}
              >
                Resume
              </a>
            </div>
          </div>
        </div>
      </div>

      {/* BottomSheet for swipe-to-move stage selection */}
      <BottomSheet
        isOpen={showBottomSheet}
        onClose={handleCloseBottomSheet}
        title="Move to Stage"
        stages={state.stages}
        currentStageId={application.current_stage}
        onSelectStage={handleSelectStage}
      />
    </>
  );
}
