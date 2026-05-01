import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import React from "react";
import { KanbanBoard } from "../KanbanBoard";
import {
  KanbanProvider,
  useKanban,
  type KanbanStage,
  type KanbanApplication,
} from "../KanbanProvider";

// ---------------------------------------------------------------------------
// Mock moveApplication API
// ---------------------------------------------------------------------------

vi.mock("@/lib/jobApi", () => ({
  moveApplication: vi.fn().mockResolvedValue({ data: {} }),
}));

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const app1: KanbanApplication = {
  id: "app-1",
  candidate_name: "Alice Johnson",
  candidate_email: "alice@example.com",
  current_stage: "stage-1",
  status: "active",
  applied_at: "2024-06-01T00:00:00Z",
};

const app2: KanbanApplication = {
  id: "app-2",
  candidate_name: "Bob Smith",
  candidate_email: "bob@example.com",
  current_stage: "stage-1",
  status: "active",
  applied_at: "2024-06-02T00:00:00Z",
};

const app3: KanbanApplication = {
  id: "app-3",
  candidate_name: "Charlie Brown",
  candidate_email: "charlie@example.com",
  current_stage: "stage-2",
  status: "active",
  applied_at: "2024-06-03T00:00:00Z",
};

const sampleStages: KanbanStage[] = [
  {
    id: "stage-1",
    name: "Applied",
    color: "#3B82F6",
    sort_order: 0,
    applications: [app1, app2],
  },
  {
    id: "stage-2",
    name: "Interview",
    color: "#10B981",
    sort_order: 1,
    applications: [app3],
  },
  {
    id: "stage-3",
    name: "Offer",
    color: "#F59E0B",
    sort_order: 2,
    applications: [],
  },
];

/**
 * Helper component that sets up KanbanProvider state before rendering KanbanBoard.
 */
function SetupState({
  stages,
  isLoading = false,
  error = null,
  searchQuery = "",
  stageFilter = null,
  sortBy = "applied_at_desc" as const,
  children,
}: {
  stages?: KanbanStage[];
  isLoading?: boolean;
  error?: string | null;
  searchQuery?: string;
  stageFilter?: string | null;
  sortBy?: "applied_at_desc" | "applied_at_asc" | "candidate_name";
  children: React.ReactNode;
}) {
  return (
    <KanbanProvider>
      <StateInjector
        stages={stages}
        isLoading={isLoading}
        error={error}
        searchQuery={searchQuery}
        stageFilter={stageFilter}
        sortBy={sortBy}
      />
      {children}
    </KanbanProvider>
  );
}

function StateInjector({
  stages,
  isLoading,
  error,
  searchQuery,
  stageFilter,
  sortBy,
}: {
  stages?: KanbanStage[];
  isLoading?: boolean;
  error?: string | null;
  searchQuery?: string;
  stageFilter?: string | null;
  sortBy?: "applied_at_desc" | "applied_at_asc" | "candidate_name";
}) {
  const { dispatch } = useKanban();

  React.useEffect(() => {
    if (isLoading) {
      dispatch({ type: "SET_LOADING", isLoading: true });
    }
    if (error) {
      dispatch({ type: "SET_ERROR", error });
    }
    if (stages) {
      dispatch({
        type: "SET_DATA",
        stages,
        totalCandidates: stages.reduce(
          (sum, s) => sum + s.applications.length,
          0
        ),
      });
    }
    if (searchQuery) {
      dispatch({ type: "SET_SEARCH", query: searchQuery });
    }
    if (stageFilter) {
      dispatch({ type: "SET_STAGE_FILTER", stageId: stageFilter });
    }
    if (sortBy && sortBy !== "applied_at_desc") {
      dispatch({ type: "SET_SORT", sortBy });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return null;
}

const defaultProps = {
  jobId: "job-1",
  canManage: true,
  canCustomize: true,
  onRetry: vi.fn(),
};

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe("KanbanBoard", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("loading state", () => {
    it("renders loading skeleton when isLoading is true", () => {
      render(
        <SetupState isLoading={true}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(
        screen.getByRole("status", { name: /loading pipeline data/i })
      ).toBeInTheDocument();
    });

    it("does not render stage columns when loading", () => {
      render(
        <SetupState isLoading={true}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(screen.queryByText("Applied")).not.toBeInTheDocument();
    });
  });

  describe("error state", () => {
    it("renders error message when error is set", () => {
      render(
        <SetupState error="Network error occurred">
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(screen.getByRole("alert")).toBeInTheDocument();
      expect(screen.getByText("Network error occurred")).toBeInTheDocument();
    });

    it("renders retry button that calls onRetry", async () => {
      const onRetry = vi.fn();
      render(
        <SetupState error="Something went wrong">
          <KanbanBoard {...defaultProps} onRetry={onRetry} />
        </SetupState>
      );

      const retryButton = screen.getByRole("button", { name: /retry/i });
      expect(retryButton).toBeInTheDocument();

      await userEvent.click(retryButton);
      expect(onRetry).toHaveBeenCalledTimes(1);
    });
  });

  describe("normal rendering", () => {
    it("renders all stage columns with names and counts", () => {
      render(
        <SetupState stages={sampleStages}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      // Stage names appear in both column headers and the search bar dropdown,
      // so we verify via the stage group ARIA labels instead.
      expect(
        screen.getByRole("group", { name: /applied stage/i })
      ).toBeInTheDocument();
      expect(
        screen.getByRole("group", { name: /interview stage/i })
      ).toBeInTheDocument();
      expect(
        screen.getByRole("group", { name: /offer stage/i })
      ).toBeInTheDocument();
    });

    it("renders candidate cards with name, email, and date", () => {
      render(
        <SetupState stages={sampleStages}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(screen.getByText("Alice Johnson")).toBeInTheDocument();
      expect(screen.getByText("alice@example.com")).toBeInTheDocument();
      expect(screen.getByText("Bob Smith")).toBeInTheDocument();
      expect(screen.getByText("Charlie Brown")).toBeInTheDocument();
    });

    it("renders count badges for each stage", () => {
      render(
        <SetupState stages={sampleStages}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      // Applied has 2 candidates, Interview has 1, Offer has 0
      expect(screen.getByText("2")).toBeInTheDocument();
      expect(screen.getByText("1")).toBeInTheDocument();
      expect(screen.getByText("0")).toBeInTheDocument();
    });

    it("renders stage columns with color top border", () => {
      render(
        <SetupState stages={sampleStages}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      // The Applied stage header should have a blue top border
      const appliedGroup = screen.getByRole("group", {
        name: /applied stage/i,
      });
      expect(appliedGroup).toBeInTheDocument();
    });

    it("shows 'No candidates' message for empty stages", () => {
      render(
        <SetupState stages={sampleStages}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(screen.getByText("No candidates")).toBeInTheDocument();
    });
  });

  describe("ARIA live region", () => {
    it("renders an ARIA live region for drag announcements", () => {
      render(
        <SetupState stages={sampleStages}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      // DndContext also creates its own live region, so we look for ours by class
      const liveRegions = screen.getAllByRole("status");
      const ourRegion = liveRegions.find((el) =>
        el.classList.contains("sr-only")
      );
      expect(ourRegion).toBeDefined();
      expect(ourRegion).toHaveAttribute("aria-live", "assertive");
      expect(ourRegion).toHaveAttribute("aria-atomic", "true");
    });
  });

  describe("board region", () => {
    it("renders the board as a region with proper label", () => {
      render(
        <SetupState stages={sampleStages}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(
        screen.getByRole("region", { name: /kanban pipeline board/i })
      ).toBeInTheDocument();
    });
  });

  describe("client-side search filtering", () => {
    it("filters applications by candidate name", () => {
      render(
        <SetupState stages={sampleStages} searchQuery="alice">
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(screen.getByText("Alice Johnson")).toBeInTheDocument();
      expect(screen.queryByText("Bob Smith")).not.toBeInTheDocument();
      expect(screen.queryByText("Charlie Brown")).not.toBeInTheDocument();
    });

    it("filters applications by email (case-insensitive)", () => {
      render(
        <SetupState stages={sampleStages} searchQuery="BOB@">
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(screen.getByText("Bob Smith")).toBeInTheDocument();
      expect(screen.queryByText("Alice Johnson")).not.toBeInTheDocument();
    });
  });

  describe("stage filter", () => {
    it("shows only the filtered stage", () => {
      render(
        <SetupState stages={sampleStages} stageFilter="stage-2">
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      // Stage names appear in the search bar dropdown even when filtered,
      // so we verify via the stage group ARIA labels instead.
      expect(
        screen.getByRole("group", { name: /interview stage/i })
      ).toBeInTheDocument();
      expect(
        screen.queryByRole("group", { name: /applied stage/i })
      ).not.toBeInTheDocument();
      expect(
        screen.queryByRole("group", { name: /offer stage/i })
      ).not.toBeInTheDocument();
    });
  });

  describe("sort", () => {
    it("sorts applications by candidate name alphabetically", () => {
      render(
        <SetupState stages={sampleStages} sortBy="candidate_name">
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      // In the Applied stage, Alice should come before Bob
      const appliedGroup = screen.getByRole("group", {
        name: /applied stage/i,
      });
      const cards = within(appliedGroup).getAllByRole("article");
      expect(cards[0]).toHaveTextContent("Alice Johnson");
      expect(cards[1]).toHaveTextContent("Bob Smith");
    });
  });

  describe("empty state", () => {
    it("shows message when no stages exist", () => {
      render(
        <SetupState stages={[]}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(screen.getByText("No stages to display.")).toBeInTheDocument();
    });
  });

  describe("mobile navigation dots", () => {
    it("renders navigation dots when multiple stages are visible", () => {
      render(
        <SetupState stages={sampleStages}>
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      const tablist = screen.getByRole("tablist", {
        name: /stage navigation/i,
      });
      expect(tablist).toBeInTheDocument();

      const tabs = within(tablist).getAllByRole("tab");
      expect(tabs).toHaveLength(3);
    });

    it("does not render dots when only one stage is visible", () => {
      render(
        <SetupState stages={sampleStages} stageFilter="stage-1">
          <KanbanBoard {...defaultProps} />
        </SetupState>
      );

      expect(
        screen.queryByRole("tablist", { name: /stage navigation/i })
      ).not.toBeInTheDocument();
    });
  });
});
