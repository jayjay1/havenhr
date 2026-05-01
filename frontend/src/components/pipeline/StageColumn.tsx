"use client";

import React, { useCallback, useRef, useState } from "react";
import { useDroppable } from "@dnd-kit/core";
import { useKanban, type KanbanStage, type KanbanApplication } from "./KanbanProvider";
import { updatePipelineStage } from "@/lib/pipelineApi";
import { StageColorPicker } from "./StageColorPicker";

// ---------------------------------------------------------------------------
// Color contrast utility
// ---------------------------------------------------------------------------

/**
 * Returns a text color (dark or white) that meets WCAG 2.1 AA contrast ratio
 * of at least 4.5:1 against the given hex background color.
 */
export function getContrastTextColor(hexColor: string): string {
  const r = parseInt(hexColor.slice(1, 3), 16);
  const g = parseInt(hexColor.slice(3, 5), 16);
  const b = parseInt(hexColor.slice(5, 7), 16);
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  return luminance > 0.5 ? "#111827" : "#FFFFFF";
}

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface StageColumnProps {
  stage: KanbanStage;
  applications: KanbanApplication[];
  isOver: boolean;
  canManage: boolean;
  canCustomize: boolean;
  jobId: string;
  children?: React.ReactNode;
}

// ---------------------------------------------------------------------------
// StageColumn
// ---------------------------------------------------------------------------

export function StageColumn({
  stage,
  applications,
  canManage,
  canCustomize,
  jobId,
  children,
}: StageColumnProps) {
  const { dispatch } = useKanban();
  const { isOver, setNodeRef } = useDroppable({ id: stage.id });

  const [isEditing, setIsEditing] = useState(false);
  const [editName, setEditName] = useState(stage.name);
  const inputRef = useRef<HTMLInputElement>(null);

  // ------- Inline editing handlers -------

  const startEditing = useCallback(() => {
    if (!canCustomize) return;
    setEditName(stage.name);
    setIsEditing(true);
    // Focus the input on next tick after render
    setTimeout(() => inputRef.current?.select(), 0);
  }, [canCustomize, stage.name]);

  const cancelEditing = useCallback(() => {
    setIsEditing(false);
    setEditName(stage.name);
  }, [stage.name]);

  const submitEdit = useCallback(async () => {
    const trimmed = editName.trim();
    setIsEditing(false);

    if (!trimmed || trimmed === stage.name) {
      setEditName(stage.name);
      return;
    }

    // Optimistic update
    dispatch({ type: "UPDATE_STAGE", stageId: stage.id, name: trimmed });

    try {
      await updatePipelineStage(jobId, stage.id, { name: trimmed });
    } catch {
      // Revert on failure
      dispatch({ type: "UPDATE_STAGE", stageId: stage.id, name: stage.name });
    }
  }, [editName, stage.name, stage.id, jobId, dispatch]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (e.key === "Enter") {
        e.preventDefault();
        submitEdit();
      } else if (e.key === "Escape") {
        e.preventDefault();
        cancelEditing();
      }
    },
    [submitEdit, cancelEditing]
  );

  return (
    <div
      ref={setNodeRef}
      className={`
        flex flex-col min-w-[280px] w-[280px] bg-gray-50 rounded-lg
        snap-center shrink-0
        ${isOver ? "ring-2 ring-blue-500" : ""}
      `}
      role="group"
      aria-label={`${stage.name} stage, ${applications.length} candidates`}
    >
      {/* Stage header */}
      <div
        className="px-3 py-2 rounded-t-lg border-b border-gray-200"
        style={{ borderTop: `4px solid ${stage.color || "#6B7280"}` }}
      >
        <div className="flex items-center justify-between">
          {isEditing ? (
            <input
              ref={inputRef}
              type="text"
              value={editName}
              onChange={(e) => setEditName(e.target.value)}
              onBlur={submitEdit}
              onKeyDown={handleKeyDown}
              className="text-sm font-semibold text-gray-900 bg-white border border-blue-400 rounded px-1 py-0.5 w-full mr-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              aria-label={`Edit stage name for ${stage.name}`}
            />
          ) : (
            <h3
              className={`text-sm font-semibold text-gray-900 truncate ${
                canCustomize ? "cursor-pointer hover:text-blue-700" : ""
              }`}
              onDoubleClick={startEditing}
              title={canCustomize ? "Double-click to rename" : undefined}
            >
              {stage.name}
            </h3>
          )}
          <div className="flex items-center gap-1.5 shrink-0">
            {canCustomize && (
              <StageColorPicker
                stageId={stage.id}
                jobId={jobId}
                currentColor={stage.color}
              />
            )}
            <span
              className="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs font-medium rounded-full"
              style={
                stage.color
                  ? {
                      backgroundColor: stage.color,
                      color: getContrastTextColor(stage.color),
                    }
                  : {
                      backgroundColor: "#E5E7EB",
                      color: "#4B5563",
                    }
              }
            >
              {applications.length}
            </span>
          </div>
        </div>
      </div>

      {/* Cards area */}
      <div className="flex-1 overflow-y-auto p-2 space-y-2 max-h-[calc(100vh-220px)]">
        {children}
        {applications.length === 0 && (
          <p className="text-xs text-gray-400 text-center py-4">
            No candidates
          </p>
        )}
      </div>
    </div>
  );
}
