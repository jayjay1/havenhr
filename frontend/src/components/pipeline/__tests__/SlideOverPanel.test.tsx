import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import React from "react";
import { SlideOverPanel, type SlideOverPanelProps } from "../SlideOverPanel";
import {
  KanbanProvider,
  useKanban,
  type KanbanStage,
} from "../KanbanProvider";

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

vi.mock("@/lib/jobApi", () => ({
  fetchTransitionHistory: vi.fn(),
  moveApplication: vi.fn().mockResolvedValue({ data: {} }),
}));

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const sampleStages: KanbanStage[] = [
  {
    id: "stage-1",
    name: "Applied",
    color: "#3B82F6",
    sort_order: 0,
    applications: [
      {
        id: "app-1",
        candidate_name: "Alice Johnson",
        candidate_email: "alice@example.com",
        current_stage: "stage-1",
        status: "active",
        applied_at: "2024-06-15T12:00:00Z",
      },
    ],
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
  {
    id: "stage-rejected",
    name: "Rejected",
    color: "#EF4444",
    sort_order: 3,
    applications: [],
  },
];

const sampleTransitions = [
  {
    id: "t-1",
    from_stage: null,
    to_stage: { id: "stage-1", name: "Applied" },
    moved_by: { id: "user-1", name: "HR Bot" },
    moved_at: "2024-06-15T12:00:00Z",
  },
  {
    id: "t-2",
    from_stage: { id: "stage-1", name: "Applied" },
    to_stage: { id: "stage-2", name: "Interview" },
    moved_by: { id: "user-2", name: "Jane Doe" },
    moved_at: "2024-06-20T14:30:00Z",
  },
];

/**
 * Wrapper that provides KanbanProvider and injects stages into state.
 */
function Wrapper({
  stages = sampleStages,
  children,
}: {
  stages?: KanbanStage[];
  children: React.ReactNode;
}) {
  return (
    <KanbanProvider>
      <StageInjector stages={stages} />
      {children}
    </KanbanProvider>
  );
}

function StageInjector({ stages }: { stages: KanbanStage[] }) {
  const { dispatch } = useKanban();
  React.useEffect(() => {
    dispatch({ type: "SET_DATA", stages, totalCandidates: 1 });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return null;
}

function renderPanel(overrides: Partial<SlideOverPanelProps> = {}) {
  const defaultProps: SlideOverPanelProps = {
    applicationId: "app-1",
    stages: sampleStages,
    onClose: vi.fn(),
    onMoveApplication: vi.fn(),
    ...overrides,
  };

  return {
    ...render(
      <Wrapper>
        <SlideOverPanel {...defaultProps} />
      </Wrapper>
    ),
    props: defaultProps,
  };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe("SlideOverPanel", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("rendering", () => {
    it("renders nothing when applicationId is not found in state", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      const { container } = render(
        <Wrapper>
          <SlideOverPanel
            applicationId="non-existent"
            stages={sampleStages}
            onClose={vi.fn()}
            onMoveApplication={vi.fn()}
          />
        </Wrapper>
      );

      // Should render nothing (no dialog)
      expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
      // The container should only have the StageInjector (empty)
      expect(container.querySelector('[role="dialog"]')).toBeNull();
    });

    it("renders candidate info when open", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      renderPanel();

      await waitFor(() => {
        expect(screen.queryByTestId("panel-skeleton")).not.toBeInTheDocument();
      });

      expect(screen.getByText("Alice Johnson")).toBeInTheDocument();
      expect(screen.getByText("alice@example.com")).toBeInTheDocument();
      expect(screen.getByText(/Applied:/)).toBeInTheDocument();
      expect(screen.getByText("View resume")).toBeInTheDocument();
    });

    it("renders transition history timeline", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: sampleTransitions,
      });

      renderPanel();

      await waitFor(() => {
        expect(screen.queryByTestId("panel-skeleton")).not.toBeInTheDocument();
      });

      // Check timeline entries
      expect(screen.getByText("Initial → Applied")).toBeInTheDocument();
      expect(screen.getByText("Applied → Interview")).toBeInTheDocument();
      expect(screen.getByText(/HR Bot/)).toBeInTheDocument();
      expect(screen.getByText(/Jane Doe/)).toBeInTheDocument();
    });

    it("shows loading skeleton while fetching", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      // Never resolve to keep loading state
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockReturnValue(
        new Promise(() => {})
      );

      renderPanel();

      expect(screen.getByTestId("panel-skeleton")).toBeInTheDocument();
    });
  });

  describe("ARIA attributes", () => {
    it('has role="dialog" and aria-modal="true"', async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      renderPanel();

      const dialog = screen.getByRole("dialog");
      expect(dialog).toBeInTheDocument();
      expect(dialog).toHaveAttribute("aria-modal", "true");
    });

    it("has aria-labelledby pointing to candidate name heading", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      renderPanel();

      const dialog = screen.getByRole("dialog");
      const labelledBy = dialog.getAttribute("aria-labelledby");
      expect(labelledBy).toBeTruthy();

      // The heading with that id should contain the candidate name
      const heading = document.getElementById(labelledBy!);
      expect(heading).toBeInTheDocument();
      expect(heading?.textContent).toBe("Alice Johnson");
    });
  });

  describe("close mechanisms", () => {
    it("calls onClose on × button click", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      const onClose = vi.fn();
      renderPanel({ onClose });

      const closeButton = screen.getByLabelText("Close panel");
      fireEvent.click(closeButton);

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    it("calls onClose on Escape key", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      const onClose = vi.fn();
      renderPanel({ onClose });

      fireEvent.keyDown(document, { key: "Escape" });

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    it("calls onClose when clicking backdrop", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      const onClose = vi.fn();
      renderPanel({ onClose });

      const backdrop = screen.getByTestId("slide-over-backdrop");
      fireEvent.click(backdrop);

      expect(onClose).toHaveBeenCalledTimes(1);
    });
  });

  describe("quick actions", () => {
    it('"Move to" dropdown contains all stages except current', async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      renderPanel();

      await waitFor(() => {
        expect(screen.queryByTestId("panel-skeleton")).not.toBeInTheDocument();
      });

      const select = screen.getByLabelText("Move to stage");
      const options = select.querySelectorAll("option");

      // Should have placeholder + 3 other stages (Interview, Offer, Rejected)
      expect(options).toHaveLength(4);
      expect(options[0].textContent).toBe("Move to…");
      expect(options[1].textContent).toBe("Interview");
      expect(options[2].textContent).toBe("Offer");
      expect(options[3].textContent).toBe("Rejected");
    });

    it("calls onMoveApplication when Move button is clicked with a selected stage", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      const onMoveApplication = vi.fn();
      renderPanel({ onMoveApplication });

      await waitFor(() => {
        expect(screen.queryByTestId("panel-skeleton")).not.toBeInTheDocument();
      });

      const select = screen.getByLabelText("Move to stage");
      fireEvent.change(select, { target: { value: "stage-2" } });

      const moveButton = screen.getByRole("button", { name: "Move" });
      fireEvent.click(moveButton);

      expect(onMoveApplication).toHaveBeenCalledWith("app-1", "stage-2");
    });

    it("Reject button calls onMoveApplication with Rejected stage", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      const onMoveApplication = vi.fn();
      renderPanel({ onMoveApplication });

      await waitFor(() => {
        expect(screen.queryByTestId("panel-skeleton")).not.toBeInTheDocument();
      });

      const rejectButton = screen.getByRole("button", { name: "Reject" });
      fireEvent.click(rejectButton);

      expect(onMoveApplication).toHaveBeenCalledWith(
        "app-1",
        "stage-rejected"
      );
    });

    it("Shortlist button calls onMoveApplication with next stage", async () => {
      const { fetchTransitionHistory } = await import("@/lib/jobApi");
      (fetchTransitionHistory as ReturnType<typeof vi.fn>).mockResolvedValue({
        data: [],
      });

      const onMoveApplication = vi.fn();
      renderPanel({ onMoveApplication });

      await waitFor(() => {
        expect(screen.queryByTestId("panel-skeleton")).not.toBeInTheDocument();
      });

      // Current stage is Applied (sort_order 0), next is Interview (sort_order 1)
      const shortlistButton = screen.getByRole("button", {
        name: /Shortlist → Interview/,
      });
      fireEvent.click(shortlistButton);

      expect(onMoveApplication).toHaveBeenCalledWith("app-1", "stage-2");
    });
  });
});
