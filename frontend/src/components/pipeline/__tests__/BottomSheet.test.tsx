import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, fireEvent } from "@testing-library/react";
import React from "react";
import { BottomSheet } from "../BottomSheet";
import type { KanbanStage } from "../KanbanProvider";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const mockStages: KanbanStage[] = [
  { id: "s1", name: "Applied", color: "#3B82F6", sort_order: 0, applications: [] },
  { id: "s2", name: "Phone Screen", color: "#10B981", sort_order: 1, applications: [] },
  { id: "s3", name: "Interview", color: "#F59E0B", sort_order: 2, applications: [] },
  { id: "s4", name: "Offer", color: null, sort_order: 3, applications: [] },
];

const defaultProps = {
  isOpen: true,
  onClose: vi.fn(),
  title: "Move to Stage",
  stages: mockStages,
  currentStageId: "s1",
  onSelectStage: vi.fn(),
};

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe("BottomSheet", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("rendering", () => {
    it("renders nothing when isOpen is false", () => {
      const { container } = render(
        <BottomSheet {...defaultProps} isOpen={false} />
      );
      expect(container.innerHTML).toBe("");
    });

    it("renders stage list when open", () => {
      render(<BottomSheet {...defaultProps} />);

      expect(screen.getByText("Phone Screen")).toBeInTheDocument();
      expect(screen.getByText("Interview")).toBeInTheDocument();
      expect(screen.getByText("Offer")).toBeInTheDocument();
    });

    it("renders the title", () => {
      render(<BottomSheet {...defaultProps} />);
      expect(screen.getByText("Move to Stage")).toBeInTheDocument();
    });

    it("renders color indicators for each stage", () => {
      render(<BottomSheet {...defaultProps} />);

      const greenIndicator = screen.getByTestId("stage-color-s2");
      expect(greenIndicator.style.backgroundColor).toBe("rgb(16, 185, 129)");

      // Null color should fall back to gray
      const grayIndicator = screen.getByTestId("stage-color-s4");
      expect(grayIndicator.style.backgroundColor).toBe("rgb(156, 163, 175)");
    });
  });

  describe("stage exclusion", () => {
    it("excludes current stage from list", () => {
      render(<BottomSheet {...defaultProps} currentStageId="s1" />);

      expect(screen.queryByText("Applied")).not.toBeInTheDocument();
      expect(screen.getByText("Phone Screen")).toBeInTheDocument();
      expect(screen.getByText("Interview")).toBeInTheDocument();
      expect(screen.getByText("Offer")).toBeInTheDocument();
    });

    it("shows all stages when currentStageId is undefined", () => {
      render(
        <BottomSheet {...defaultProps} currentStageId={undefined} />
      );

      expect(screen.getByText("Applied")).toBeInTheDocument();
      expect(screen.getByText("Phone Screen")).toBeInTheDocument();
      expect(screen.getByText("Interview")).toBeInTheDocument();
      expect(screen.getByText("Offer")).toBeInTheDocument();
    });

    it("excludes a different current stage", () => {
      render(<BottomSheet {...defaultProps} currentStageId="s3" />);

      expect(screen.getByText("Applied")).toBeInTheDocument();
      expect(screen.getByText("Phone Screen")).toBeInTheDocument();
      expect(screen.queryByText("Interview")).not.toBeInTheDocument();
      expect(screen.getByText("Offer")).toBeInTheDocument();
    });
  });

  describe("interactions", () => {
    it("calls onSelectStage when a stage is clicked", () => {
      render(<BottomSheet {...defaultProps} />);

      fireEvent.click(screen.getByText("Phone Screen"));
      expect(defaultProps.onSelectStage).toHaveBeenCalledWith("s2");
    });

    it("calls onClose when backdrop is clicked", () => {
      render(<BottomSheet {...defaultProps} />);

      fireEvent.click(screen.getByTestId("bottom-sheet-backdrop"));
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });

    it("calls onClose on Escape key", () => {
      render(<BottomSheet {...defaultProps} />);

      fireEvent.keyDown(document, { key: "Escape" });
      expect(defaultProps.onClose).toHaveBeenCalledTimes(1);
    });
  });

  describe("accessibility", () => {
    it('has role="dialog" and aria-modal="true"', () => {
      render(<BottomSheet {...defaultProps} />);

      const dialog = screen.getByRole("dialog");
      expect(dialog).toBeInTheDocument();
      expect(dialog).toHaveAttribute("aria-modal", "true");
    });

    it("has aria-labelledby pointing to the title", () => {
      render(<BottomSheet {...defaultProps} />);

      const dialog = screen.getByRole("dialog");
      const labelledBy = dialog.getAttribute("aria-labelledby");
      expect(labelledBy).toBeTruthy();

      const titleEl = document.getElementById(labelledBy!);
      expect(titleEl).toBeInTheDocument();
      expect(titleEl?.textContent).toBe("Move to Stage");
    });

    it("traps focus within the sheet (Tab wraps from last to first)", () => {
      render(<BottomSheet {...defaultProps} />);

      const buttons = screen.getAllByRole("button");
      const lastButton = buttons[buttons.length - 1];

      // Focus the last button
      lastButton.focus();
      expect(document.activeElement).toBe(lastButton);

      // Tab should wrap to first button
      fireEvent.keyDown(document, { key: "Tab" });
      expect(document.activeElement).toBe(buttons[0]);
    });

    it("traps focus within the sheet (Shift+Tab wraps from first to last)", () => {
      render(<BottomSheet {...defaultProps} />);

      const buttons = screen.getAllByRole("button");
      const firstButton = buttons[0];
      const lastButton = buttons[buttons.length - 1];

      // Focus the first button
      firstButton.focus();
      expect(document.activeElement).toBe(firstButton);

      // Shift+Tab should wrap to last button
      fireEvent.keyDown(document, { key: "Tab", shiftKey: true });
      expect(document.activeElement).toBe(lastButton);
    });
  });
});
