"use client";

import React, { useEffect, useRef, useCallback } from "react";
import type { KanbanStage } from "./KanbanProvider";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface BottomSheetProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  stages: KanbanStage[];
  currentStageId?: string;
  onSelectStage: (stageId: string) => void;
}

// ---------------------------------------------------------------------------
// BottomSheet
// ---------------------------------------------------------------------------

export function BottomSheet({
  isOpen,
  onClose,
  title,
  stages,
  currentStageId,
  onSelectStage,
}: BottomSheetProps) {
  const sheetRef = useRef<HTMLDivElement>(null);
  const titleId = "bottom-sheet-title";

  // Touch tracking for swipe-to-dismiss
  const touchStartY = useRef<number | null>(null);

  // -----------------------------------------------------------------------
  // Focus trap
  // -----------------------------------------------------------------------

  const getFocusableElements = useCallback((): HTMLElement[] => {
    if (!sheetRef.current) return [];
    const elements = sheetRef.current.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    return Array.from(elements);
  }, []);

  // Focus the first stage button when the sheet opens
  useEffect(() => {
    if (!isOpen) return;

    // Small delay to allow the DOM to render
    const timer = setTimeout(() => {
      const focusable = getFocusableElements();
      if (focusable.length > 0) {
        focusable[0].focus();
      }
    }, 50);

    return () => clearTimeout(timer);
  }, [isOpen, getFocusableElements]);

  // -----------------------------------------------------------------------
  // Keyboard handling (Escape + focus trap)
  // -----------------------------------------------------------------------

  useEffect(() => {
    if (!isOpen) return;

    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") {
        e.preventDefault();
        onClose();
        return;
      }

      if (e.key === "Tab") {
        const focusable = getFocusableElements();
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
    }

    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [isOpen, onClose, getFocusableElements]);

  // -----------------------------------------------------------------------
  // Touch handlers for swipe-to-dismiss
  // -----------------------------------------------------------------------

  const handleTouchStart = useCallback((e: React.TouchEvent) => {
    touchStartY.current = e.touches[0].clientY;
  }, []);

  const handleTouchEnd = useCallback(
    (e: React.TouchEvent) => {
      if (touchStartY.current === null) return;
      const deltaY = e.changedTouches[0].clientY - touchStartY.current;
      touchStartY.current = null;

      // Swipe down > 100px → dismiss
      if (deltaY > 100) {
        onClose();
      }
    },
    [onClose]
  );

  // -----------------------------------------------------------------------
  // Render
  // -----------------------------------------------------------------------

  if (!isOpen) return null;

  const filteredStages = stages.filter((s) => s.id !== currentStageId);

  return (
    <>
      {/* Backdrop */}
      <div
        data-testid="bottom-sheet-backdrop"
        onClick={onClose}
        style={{
          position: "fixed",
          inset: 0,
          backgroundColor: "rgba(0, 0, 0, 0.5)",
          zIndex: 40,
        }}
      />

      {/* Sheet */}
      <div
        ref={sheetRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        onTouchStart={handleTouchStart}
        onTouchEnd={handleTouchEnd}
        style={{
          position: "fixed",
          bottom: 0,
          left: 0,
          right: 0,
          backgroundColor: "#fff",
          borderTopLeftRadius: 16,
          borderTopRightRadius: 16,
          zIndex: 50,
          maxHeight: "80vh",
          overflowY: "auto",
          transform: "translateY(0)",
          transition: "transform 0.3s ease-out",
        }}
      >
        {/* Drag handle indicator */}
        <div
          style={{
            display: "flex",
            justifyContent: "center",
            padding: "12px 0 4px",
          }}
        >
          <div
            style={{
              width: 40,
              height: 4,
              borderRadius: 2,
              backgroundColor: "#D1D5DB",
            }}
          />
        </div>

        {/* Title */}
        <h2
          id={titleId}
          style={{
            fontSize: 18,
            fontWeight: 600,
            padding: "8px 16px 12px",
            margin: 0,
          }}
        >
          {title}
        </h2>

        {/* Stage list */}
        <ul
          style={{
            listStyle: "none",
            margin: 0,
            padding: "0 0 16px",
          }}
        >
          {filteredStages.map((stage) => (
            <li key={stage.id}>
              <button
                onClick={() => onSelectStage(stage.id)}
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: 12,
                  width: "100%",
                  padding: "12px 16px",
                  border: "none",
                  background: "none",
                  cursor: "pointer",
                  fontSize: 16,
                  textAlign: "left",
                  minHeight: 44,
                }}
              >
                {/* Color indicator */}
                <span
                  data-testid={`stage-color-${stage.id}`}
                  style={{
                    display: "inline-block",
                    width: 12,
                    height: 12,
                    borderRadius: "50%",
                    backgroundColor: stage.color ?? "#9CA3AF",
                    flexShrink: 0,
                  }}
                />
                <span>{stage.name}</span>
              </button>
            </li>
          ))}
        </ul>
      </div>
    </>
  );
}
