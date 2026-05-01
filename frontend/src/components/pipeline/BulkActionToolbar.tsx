"use client";

import React, { useCallback, useEffect, useState } from "react";
import { useKanban, type KanbanStage } from "./KanbanProvider";
import {
  bulkMoveApplications,
  bulkRejectApplications,
} from "@/lib/pipelineApi";

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface BulkActionToolbarProps {
  stages: KanbanStage[];
  canManage: boolean;
}

// ---------------------------------------------------------------------------
// BulkActionToolbar
// ---------------------------------------------------------------------------

export function BulkActionToolbar({
  stages,
  canManage,
}: BulkActionToolbarProps) {
  const { state, dispatch } = useKanban();
  const [toast, setToast] = useState<string | null>(null);
  const [isMoving, setIsMoving] = useState(false);

  // Auto-dismiss toast after 3 seconds
  useEffect(() => {
    if (!toast) return;
    const timer = setTimeout(() => setToast(null), 3000);
    return () => clearTimeout(timer);
  }, [toast]);

  const selectedCount = state.selectedIds.size;

  // Handle "Move to Stage"
  const handleMoveToStage = useCallback(
    async (e: React.ChangeEvent<HTMLSelectElement>) => {
      const toStageId = e.target.value;
      if (!toStageId) return;

      // Reset the select back to placeholder
      e.target.value = "";

      const appIds = Array.from(state.selectedIds);
      setIsMoving(true);

      // Optimistic update
      dispatch({
        type: "BULK_MOVE_OPTIMISTIC",
        appIds,
        toStageId,
      });

      try {
        const response = await bulkMoveApplications(appIds, toStageId);
        const result = response.data;

        if (result.failed_count === 0) {
          dispatch({ type: "BULK_MOVE_CONFIRMED" });
        } else if (result.success_count === 0) {
          dispatch({ type: "MOVE_CARD_ROLLBACK" });
          setToast(`Failed to move ${result.failed_count} candidates.`);
        } else {
          dispatch({
            type: "BULK_MOVE_PARTIAL_ROLLBACK",
            failedIds: result.failed_ids,
          });
          setToast(
            `Moved ${result.success_count} candidates. ${result.failed_count} failed.`
          );
        }
      } catch {
        dispatch({ type: "MOVE_CARD_ROLLBACK" });
        setToast("Failed to move candidates. Please try again.");
      } finally {
        setIsMoving(false);
      }
    },
    [state.selectedIds, dispatch]
  );

  // Handle "Reject All"
  const handleRejectAll = useCallback(async () => {
    const rejectedStage = stages.find(
      (s) => s.name.toLowerCase() === "rejected"
    );
    if (!rejectedStage) {
      setToast("No Rejected stage found.");
      return;
    }

    const appIds = Array.from(state.selectedIds);
    setIsMoving(true);

    // Optimistic update — move to Rejected stage
    dispatch({
      type: "BULK_MOVE_OPTIMISTIC",
      appIds,
      toStageId: rejectedStage.id,
    });

    try {
      const response = await bulkRejectApplications(appIds);
      const result = response.data;

      if (result.failed_count === 0) {
        dispatch({ type: "BULK_MOVE_CONFIRMED" });
      } else if (result.success_count === 0) {
        dispatch({ type: "MOVE_CARD_ROLLBACK" });
        setToast(`Failed to reject ${result.failed_count} candidates.`);
      } else {
        dispatch({
          type: "BULK_MOVE_PARTIAL_ROLLBACK",
          failedIds: result.failed_ids,
        });
        setToast(
          `Moved ${result.success_count} candidates. ${result.failed_count} failed.`
        );
      }
    } catch {
      dispatch({ type: "MOVE_CARD_ROLLBACK" });
      setToast("Failed to reject candidates. Please try again.");
    } finally {
      setIsMoving(false);
    }
  }, [state.selectedIds, stages, dispatch]);

  // Handle "Clear Selection"
  const handleClearSelection = useCallback(() => {
    dispatch({ type: "CLEAR_SELECTION" });
  }, [dispatch]);

  // Don't render toolbar when user lacks permission or nothing is selected
  if (!canManage || selectedCount === 0) {
    return toast ? (
      <div
        role="status"
        aria-live="polite"
        className="
          fixed bottom-4 left-1/2 -translate-x-1/2 z-[60]
          bg-gray-900 text-white text-sm
          px-4 py-2 rounded-lg shadow-lg
        "
      >
        {toast}
      </div>
    ) : null;
  }

  return (
    <>
      {/* Toolbar */}
      <div
        role="toolbar"
        aria-label="Bulk actions"
        className="
          fixed bottom-0 left-0 right-0 z-50
          md:static md:z-auto
          bg-white border-t md:border md:rounded-lg
          shadow-lg md:shadow-md
          px-4 py-3
          flex items-center gap-3 flex-wrap
        "
      >
        {/* Selected count */}
        <span className="text-sm font-medium text-gray-700 whitespace-nowrap">
          {selectedCount} selected
        </span>

        {/* Move to Stage dropdown */}
        <select
          aria-label="Move to stage"
          onChange={handleMoveToStage}
          disabled={isMoving}
          defaultValue=""
          className="
            text-sm border border-gray-300 rounded-md
            px-2 py-1.5
            bg-white text-gray-700
            focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
            disabled:opacity-50 disabled:cursor-not-allowed
          "
        >
          <option value="" disabled>
            Move to Stage
          </option>
          {stages.map((stage) => (
            <option key={stage.id} value={stage.id}>
              {stage.name}
            </option>
          ))}
        </select>

        {/* Reject All button */}
        <button
          type="button"
          onClick={handleRejectAll}
          disabled={isMoving}
          className="
            text-sm font-medium
            px-3 py-1.5 rounded-md
            text-red-700 bg-red-50 border border-red-200
            hover:bg-red-100
            focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1
            disabled:opacity-50 disabled:cursor-not-allowed
            transition-colors
          "
        >
          Reject All
        </button>

        {/* Clear Selection button */}
        <button
          type="button"
          onClick={handleClearSelection}
          disabled={isMoving}
          className="
            text-sm font-medium
            px-3 py-1.5 rounded-md
            text-gray-600 bg-gray-50 border border-gray-200
            hover:bg-gray-100
            focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-1
            disabled:opacity-50 disabled:cursor-not-allowed
            transition-colors
          "
        >
          Clear Selection
        </button>
      </div>

      {/* Toast notification */}
      {toast && (
        <div
          role="status"
          aria-live="polite"
          className="
            fixed bottom-16 md:bottom-4 left-1/2 -translate-x-1/2 z-[60]
            bg-gray-900 text-white text-sm
            px-4 py-2 rounded-lg shadow-lg
          "
        >
          {toast}
        </div>
      )}
    </>
  );
}
