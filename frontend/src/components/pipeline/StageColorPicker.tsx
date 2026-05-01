"use client";

import React, { useCallback, useEffect, useRef, useState } from "react";
import { useKanban } from "./KanbanProvider";
import { updatePipelineStage } from "@/lib/pipelineApi";

// ---------------------------------------------------------------------------
// Preset color palette
// ---------------------------------------------------------------------------

const PRESET_COLORS = [
  { hex: "#3B82F6", label: "Blue" },
  { hex: "#10B981", label: "Green" },
  { hex: "#F59E0B", label: "Amber" },
  { hex: "#EF4444", label: "Red" },
  { hex: "#8B5CF6", label: "Purple" },
  { hex: "#EC4899", label: "Pink" },
  { hex: "#06B6D4", label: "Cyan" },
  { hex: "#F97316", label: "Orange" },
  { hex: "#6B7280", label: "Gray" },
  { hex: "#84CC16", label: "Lime" },
] as const;

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface StageColorPickerProps {
  stageId: string;
  jobId: string;
  currentColor: string | null;
}

// ---------------------------------------------------------------------------
// StageColorPicker
// ---------------------------------------------------------------------------

export function StageColorPicker({
  stageId,
  jobId,
  currentColor,
}: StageColorPickerProps) {
  const { dispatch } = useKanban();
  const [isOpen, setIsOpen] = useState(false);
  const popoverRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);

  // Close popover on click outside
  useEffect(() => {
    if (!isOpen) return;

    function handleClickOutside(e: MouseEvent) {
      if (
        popoverRef.current &&
        !popoverRef.current.contains(e.target as Node) &&
        buttonRef.current &&
        !buttonRef.current.contains(e.target as Node)
      ) {
        setIsOpen(false);
      }
    }

    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [isOpen]);

  // Close popover on Escape key
  useEffect(() => {
    if (!isOpen) return;

    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") {
        setIsOpen(false);
        buttonRef.current?.focus();
      }
    }

    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [isOpen]);

  const handleSelectColor = useCallback(
    async (color: string | null) => {
      const previousColor = currentColor;
      setIsOpen(false);

      // Optimistic update
      dispatch({ type: "UPDATE_STAGE", stageId, color });

      try {
        await updatePipelineStage(jobId, stageId, { color });
      } catch {
        // Revert on failure
        dispatch({ type: "UPDATE_STAGE", stageId, color: previousColor });
      }
    },
    [stageId, jobId, currentColor, dispatch]
  );

  return (
    <div className="relative">
      <button
        ref={buttonRef}
        type="button"
        onClick={() => setIsOpen((prev) => !prev)}
        className="flex items-center justify-center w-5 h-5 rounded-full border border-gray-300 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 shrink-0"
        style={{ backgroundColor: currentColor || "#E5E7EB" }}
        aria-label="Pick stage color"
        aria-expanded={isOpen}
        aria-haspopup="true"
      />

      {isOpen && (
        <div
          ref={popoverRef}
          className="absolute top-full left-0 mt-1 z-50 bg-white rounded-lg shadow-lg border border-gray-200 p-2 w-[180px]"
          role="dialog"
          aria-label="Stage color picker"
        >
          <div className="grid grid-cols-5 gap-1.5">
            {PRESET_COLORS.map(({ hex, label }) => (
              <button
                key={hex}
                type="button"
                onClick={() => handleSelectColor(hex)}
                className="w-6 h-6 rounded-full border border-gray-200 hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 flex items-center justify-center"
                style={{ backgroundColor: hex }}
                aria-label={`${label}${currentColor === hex ? " (selected)" : ""}`}
                title={label}
              >
                {currentColor === hex && (
                  <svg
                    className="w-3 h-3 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={3}
                    aria-hidden="true"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                )}
              </button>
            ))}
          </div>

          {/* No color option */}
          <button
            type="button"
            onClick={() => handleSelectColor(null)}
            className="mt-2 w-full flex items-center gap-2 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 rounded transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
            aria-label={`No color${currentColor === null ? " (selected)" : ""}`}
          >
            <span className="w-4 h-4 rounded-full border-2 border-dashed border-gray-300 shrink-0" />
            <span>No color</span>
            {currentColor === null && (
              <svg
                className="w-3 h-3 text-gray-500 ml-auto"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                strokeWidth={3}
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M5 13l4 4L19 7"
                />
              </svg>
            )}
          </button>
        </div>
      )}
    </div>
  );
}
