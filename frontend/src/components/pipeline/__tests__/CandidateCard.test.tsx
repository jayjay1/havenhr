import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import React from "react";
import { CandidateCard } from "../CandidateCard";
import {
  KanbanProvider,
  useKanban,
  type KanbanApplication,
  type KanbanStage,
} from "../KanbanProvider";
import { DndContext } from "@dnd-kit/core";

// Mock the moveApplication API
vi.mock("@/lib/jobApi", () => ({
  moveApplication: vi.fn().mockResolvedValue({ data: {} }),
}));

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const sampleApp: KanbanApplication = {
  id: "app-1",
  candidate_name: "Alice Johnson",
  candidate_email: "alice@example.com",
  current_stage: "stage-1",
  status: "active",
  applied_at: "2024-06-15T12:00:00Z",
};

// Pre-compute the expected formatted date to avoid timezone issues in tests
const expectedDate = new Date("2024-06-15T12:00:00Z").toLocaleDateString(
  "en-US",
  { month: "short", day: "numeric", year: "numeric" }
);

/**
 * Wrapper that provides KanbanProvider + DndContext so useDraggable works.
 * Optionally injects selectedIds into state to simulate bulk selection mode.
 * Optionally injects stages into state for BottomSheet rendering.
 */
function Wrapper({
  selectedIds = [],
  stages = [],
  children,
}: {
  selectedIds?: string[];
  stages?: KanbanStage[];
  children: React.ReactNode;
}) {
  return (
    <KanbanProvider>
      {selectedIds.length > 0 && <SelectInjector ids={selectedIds} />}
      {stages.length > 0 && <StageInjector stages={stages} />}
      <DndContext>{children}</DndContext>
    </KanbanProvider>
  );
}

function SelectInjector({ ids }: { ids: string[] }) {
  const { dispatch } = useKanban();
  React.useEffect(() => {
    for (const id of ids) {
      dispatch({ type: "TOGGLE_SELECT", appId: id });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return null;
}

function StageInjector({ stages }: { stages: KanbanStage[] }) {
  const { dispatch } = useKanban();
  React.useEffect(() => {
    dispatch({ type: "SET_DATA", stages, totalCandidates: 0 });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return null;
}

const defaultProps = {
  application: sampleApp,
  isSelected: false,
  isDragging: false,
  canManage: true,
  onSelect: vi.fn(),
  onClick: vi.fn(),
};

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe("CandidateCard", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("rendering", () => {
    it("displays candidate name", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      expect(screen.getByText("Alice Johnson")).toBeInTheDocument();
    });

    it("displays candidate email", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      expect(screen.getByText("alice@example.com")).toBeInTheDocument();
    });

    it("displays formatted applied date", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      expect(screen.getByText(expectedDate)).toBeInTheDocument();
    });

    it("displays resume link", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      const link = screen.getByRole("link", {
        name: /view resume for alice johnson/i,
      });
      expect(link).toBeInTheDocument();
      expect(link).toHaveAttribute("href", "/candidates/app-1/resume");
    });

    it("has role=article with aria-label including name and date", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      expect(card).toHaveAttribute(
        "aria-label",
        `Alice Johnson, applied ${expectedDate}`
      );
    });

    it("has min-h-[44px] class for touch target compliance", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      expect(card.className).toContain("min-h-[44px]");
    });
  });

  describe("drag styling", () => {
    it("applies opacity 0.5 and shadow-lg when isDragging is true", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} isDragging={true} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      expect(card.style.opacity).toBe("0.5");
      expect(card.className).toContain("shadow-lg");
    });

    it("has full opacity when not dragging", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} isDragging={false} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      expect(card.style.opacity).toBe("1");
    });
  });

  describe("canManage", () => {
    it("shows grab cursor when canManage is true", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} canManage={true} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      expect(card.className).toContain("cursor-grab");
    });

    it("shows default cursor when canManage is false", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} canManage={false} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      expect(card.className).toContain("cursor-default");
      expect(card.className).not.toContain("cursor-grab");
    });
  });

  describe("click behavior", () => {
    it("calls onClick with application id when card is clicked", () => {
      const onClick = vi.fn();
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} onClick={onClick} />
        </Wrapper>
      );
      // Use fireEvent.click to bypass dnd-kit's pointer event interception
      fireEvent.click(screen.getByRole("article"));
      expect(onClick).toHaveBeenCalledWith("app-1");
    });
  });

  describe("keyboard accessibility", () => {
    it("calls onClick on Enter key", async () => {
      const onClick = vi.fn();
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} onClick={onClick} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      card.focus();
      await userEvent.keyboard("{Enter}");
      expect(onClick).toHaveBeenCalledWith("app-1");
    });

    it("calls onClick on Space key", async () => {
      const onClick = vi.fn();
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} onClick={onClick} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      card.focus();
      await userEvent.keyboard(" ");
      expect(onClick).toHaveBeenCalledWith("app-1");
    });

    it("has tabIndex=0 for keyboard focus", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      expect(card).toHaveAttribute("tabindex", "0");
    });
  });

  describe("bulk selection checkbox", () => {
    it("does not show checkbox when no items are selected (bulk mode inactive)", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      expect(
        screen.queryByRole("checkbox", { name: /select alice johnson/i })
      ).not.toBeInTheDocument();
    });

    it("shows checkbox when bulk selection mode is active", () => {
      render(
        <Wrapper selectedIds={["app-other"]}>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      expect(
        screen.getByRole("checkbox", { name: /select alice johnson/i })
      ).toBeInTheDocument();
    });

    it("checkbox is checked when isSelected is true", () => {
      render(
        <Wrapper selectedIds={["app-1"]}>
          <CandidateCard {...defaultProps} isSelected={true} />
        </Wrapper>
      );
      const checkbox = screen.getByRole("checkbox", {
        name: /select alice johnson/i,
      });
      expect(checkbox).toBeChecked();
    });

    it("calls onSelect when checkbox is clicked", () => {
      const onSelect = vi.fn();
      render(
        <Wrapper selectedIds={["app-other"]}>
          <CandidateCard {...defaultProps} onSelect={onSelect} />
        </Wrapper>
      );
      const checkbox = screen.getByRole("checkbox", {
        name: /select alice johnson/i,
      });
      fireEvent.click(checkbox);
      expect(onSelect).toHaveBeenCalledWith("app-1");
    });

    it("does not call onClick when checkbox is clicked", () => {
      const onClick = vi.fn();
      const onSelect = vi.fn();
      render(
        <Wrapper selectedIds={["app-other"]}>
          <CandidateCard
            {...defaultProps}
            onClick={onClick}
            onSelect={onSelect}
          />
        </Wrapper>
      );
      const checkbox = screen.getByRole("checkbox", {
        name: /select alice johnson/i,
      });
      fireEvent.click(checkbox);
      expect(onClick).not.toHaveBeenCalled();
    });
  });

  describe("focus indicator", () => {
    it("has visible focus ring classes", () => {
      render(
        <Wrapper>
          <CandidateCard {...defaultProps} />
        </Wrapper>
      );
      const card = screen.getByRole("article");
      expect(card.className).toContain("focus:ring-2");
      expect(card.className).toContain("focus:ring-blue-500");
    });
  });

  describe("swipe gesture", () => {
    const sampleStages: KanbanStage[] = [
      {
        id: "stage-1",
        name: "Applied",
        color: "#3B82F6",
        sort_order: 0,
        applications: [sampleApp],
      },
      {
        id: "stage-2",
        name: "Interview",
        color: "#10B981",
        sort_order: 1,
        applications: [],
      },
      {
        id: "stage-3",
        name: "Offer",
        color: "#F59E0B",
        sort_order: 2,
        applications: [],
      },
    ];

    /** Helper to simulate a quick horizontal swipe on the card element. */
    function simulateSwipe(
      card: HTMLElement,
      deltaX: number,
      deltaY = 0,
      durationMs = 100
    ) {
      const startX = 200;
      const startY = 200;

      // Mock Date.now for duration control
      const originalNow = Date.now;
      let currentTime = 1000;
      Date.now = () => currentTime;

      fireEvent.touchStart(card, {
        touches: [{ clientX: startX, clientY: startY }],
      });

      currentTime += durationMs;

      fireEvent.touchEnd(card, {
        changedTouches: [
          { clientX: startX + deltaX, clientY: startY + deltaY },
        ],
      });

      Date.now = originalNow;
    }

    it("opens BottomSheet on horizontal swipe when canManage is true", () => {
      render(
        <Wrapper stages={sampleStages}>
          <CandidateCard {...defaultProps} canManage={true} />
        </Wrapper>
      );

      const card = screen.getByRole("article");
      simulateSwipe(card, 80); // 80px right swipe, quick

      // BottomSheet should be open — it renders a dialog
      expect(screen.getByRole("dialog")).toBeInTheDocument();
      expect(screen.getByText("Move to Stage")).toBeInTheDocument();
    });

    it("does not open BottomSheet when canManage is false", () => {
      render(
        <Wrapper stages={sampleStages}>
          <CandidateCard {...defaultProps} canManage={false} />
        </Wrapper>
      );

      const card = screen.getByRole("article");
      simulateSwipe(card, 80);

      expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    });

    it("does not open BottomSheet for short horizontal swipe (< 50px)", () => {
      render(
        <Wrapper stages={sampleStages}>
          <CandidateCard {...defaultProps} canManage={true} />
        </Wrapper>
      );

      const card = screen.getByRole("article");
      simulateSwipe(card, 30); // Below threshold

      expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    });

    it("does not open BottomSheet for vertical scroll (deltaY > deltaX)", () => {
      render(
        <Wrapper stages={sampleStages}>
          <CandidateCard {...defaultProps} canManage={true} />
        </Wrapper>
      );

      const card = screen.getByRole("article");
      simulateSwipe(card, 30, 100); // Mostly vertical

      expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    });

    it("does not open BottomSheet for slow swipe (> 300ms)", () => {
      render(
        <Wrapper stages={sampleStages}>
          <CandidateCard {...defaultProps} canManage={true} />
        </Wrapper>
      );

      const card = screen.getByRole("article");
      simulateSwipe(card, 80, 0, 500); // 500ms — too slow, let dnd-kit handle

      expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    });

    it("excludes current stage from BottomSheet options", () => {
      render(
        <Wrapper stages={sampleStages}>
          <CandidateCard {...defaultProps} canManage={true} />
        </Wrapper>
      );

      const card = screen.getByRole("article");
      simulateSwipe(card, 80);

      // Current stage is "stage-1" (Applied) — should not appear
      expect(screen.queryByText("Applied")).not.toBeInTheDocument();
      // Other stages should appear
      expect(screen.getByText("Interview")).toBeInTheDocument();
      expect(screen.getByText("Offer")).toBeInTheDocument();
    });

    it("closes BottomSheet on cancel without API call", async () => {
      const { moveApplication } = await import("@/lib/jobApi");

      render(
        <Wrapper stages={sampleStages}>
          <CandidateCard {...defaultProps} canManage={true} />
        </Wrapper>
      );

      const card = screen.getByRole("article");
      simulateSwipe(card, 80);

      // BottomSheet is open
      expect(screen.getByRole("dialog")).toBeInTheDocument();

      // Click backdrop to dismiss
      fireEvent.click(screen.getByTestId("bottom-sheet-backdrop"));

      // BottomSheet should be closed
      expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

      // No API call should have been made
      expect(moveApplication).not.toHaveBeenCalled();
    });

    it("calls move API and dispatches optimistic update on stage selection", async () => {
      const { moveApplication } = await import("@/lib/jobApi");

      render(
        <Wrapper stages={sampleStages}>
          <CandidateCard {...defaultProps} canManage={true} />
        </Wrapper>
      );

      const card = screen.getByRole("article");
      simulateSwipe(card, 80);

      // Select "Interview" stage
      fireEvent.click(screen.getByText("Interview"));

      // BottomSheet should close
      await waitFor(() => {
        expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
      });

      // API should have been called with the application ID and target stage
      expect(moveApplication).toHaveBeenCalledWith("app-1", "stage-2");
    });

    it("works with left swipe as well", () => {
      render(
        <Wrapper stages={sampleStages}>
          <CandidateCard {...defaultProps} canManage={true} />
        </Wrapper>
      );

      const card = screen.getByRole("article");
      simulateSwipe(card, -80); // Left swipe

      expect(screen.getByRole("dialog")).toBeInTheDocument();
    });
  });
});
