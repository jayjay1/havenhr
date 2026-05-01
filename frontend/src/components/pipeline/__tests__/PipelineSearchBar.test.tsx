import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, within, act } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import React from "react";
import { PipelineSearchBar } from "../PipelineSearchBar";
import {
  KanbanProvider,
  useKanban,
  type KanbanStage,
  type KanbanApplication,
} from "../KanbanProvider";

// ---------------------------------------------------------------------------
// Mock pipelineApi
// ---------------------------------------------------------------------------

vi.mock("@/lib/pipelineApi", () => ({
  fetchJobApplicationsWithSearch: vi.fn().mockResolvedValue({
    data: [],
    meta: { current_page: 1, per_page: 25, total: 0, last_page: 1 },
  }),
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
 * Helper component that sets up KanbanProvider state before rendering.
 */
function SetupState({
  stages,
  searchQuery = "",
  totalCandidates,
  children,
}: {
  stages: KanbanStage[];
  searchQuery?: string;
  totalCandidates?: number;
  children: React.ReactNode;
}) {
  return (
    <KanbanProvider>
      <StateInjector
        stages={stages}
        searchQuery={searchQuery}
        totalCandidates={totalCandidates}
      />
      {children}
    </KanbanProvider>
  );
}

function StateInjector({
  stages,
  searchQuery,
  totalCandidates,
}: {
  stages: KanbanStage[];
  searchQuery?: string;
  totalCandidates?: number;
}) {
  const { dispatch } = useKanban();

  React.useEffect(() => {
    const total =
      totalCandidates ?? stages.reduce((sum, s) => sum + s.applications.length, 0);
    dispatch({ type: "SET_DATA", stages, totalCandidates: total });
    if (searchQuery) {
      dispatch({ type: "SET_SEARCH", query: searchQuery });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return null;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe("PipelineSearchBar", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers({ shouldAdvanceTime: true });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  describe("search input", () => {
    it("renders search input with placeholder", () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      const input = screen.getByPlaceholderText("Search candidates...");
      expect(input).toBeInTheDocument();
      expect(input).toHaveAttribute("type", "text");
    });

    it("has a search icon", () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      // The SVG search icon is aria-hidden
      const svg = document.querySelector('svg[aria-hidden="true"]');
      expect(svg).toBeInTheDocument();
    });

    it("has accessible label", () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      expect(
        screen.getByRole("textbox", { name: /search candidates/i })
      ).toBeInTheDocument();
    });
  });

  describe("stage filter dropdown", () => {
    it("renders stage filter with All Stages default", () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      const select = screen.getByRole("combobox", { name: /filter by stage/i });
      expect(select).toBeInTheDocument();

      const options = within(select).getAllByRole("option");
      expect(options[0]).toHaveTextContent("All Stages");
      expect(options[0]).toHaveValue("");
    });

    it("lists all stages as options", () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      const select = screen.getByRole("combobox", { name: /filter by stage/i });
      const options = within(select).getAllByRole("option");
      // All Stages + 3 stages
      expect(options).toHaveLength(4);
      expect(options[1]).toHaveTextContent("Applied");
      expect(options[2]).toHaveTextContent("Interview");
      expect(options[3]).toHaveTextContent("Offer");
    });

    it("dispatches SET_STAGE_FILTER on change", async () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      const select = screen.getByRole("combobox", { name: /filter by stage/i });
      await userEvent.selectOptions(select, "stage-2");
      expect(select).toHaveValue("stage-2");
    });
  });

  describe("sort selector", () => {
    it("renders sort selector with correct options", () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      const select = screen.getByRole("combobox", { name: /sort candidates/i });
      expect(select).toBeInTheDocument();

      const options = within(select).getAllByRole("option");
      expect(options).toHaveLength(3);
      expect(options[0]).toHaveTextContent("Applied: Newest");
      expect(options[0]).toHaveValue("applied_at_desc");
      expect(options[1]).toHaveTextContent("Applied: Oldest");
      expect(options[1]).toHaveValue("applied_at_asc");
      expect(options[2]).toHaveTextContent("Name: A-Z");
      expect(options[2]).toHaveValue("candidate_name");
    });

    it("dispatches SET_SORT on change", async () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      const select = screen.getByRole("combobox", { name: /sort candidates/i });
      await userEvent.selectOptions(select, "candidate_name");
      expect(select).toHaveValue("candidate_name");
    });
  });

  describe("match count and clear button", () => {
    it("does not show match count when search is empty", () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      expect(screen.queryByText(/match/i)).not.toBeInTheDocument();
      expect(
        screen.queryByRole("button", { name: /clear/i })
      ).not.toBeInTheDocument();
    });

    it("shows match count and clear button when search is active", () => {
      render(
        <SetupState stages={sampleStages} searchQuery="alice">
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      expect(screen.getByText("1 match")).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: /clear/i })
      ).toBeInTheDocument();
    });

    it("shows plural matches text", () => {
      render(
        <SetupState stages={sampleStages} searchQuery="example.com">
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      expect(screen.getByText("3 matches")).toBeInTheDocument();
    });

    it("clears search, filter, and sort on Clear click", async () => {
      render(
        <SetupState stages={sampleStages} searchQuery="alice">
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      const clearButton = screen.getByRole("button", { name: /clear/i });
      await userEvent.click(clearButton);

      // After clearing, the match count and clear button should disappear
      expect(screen.queryByText(/match/i)).not.toBeInTheDocument();
      expect(
        screen.queryByRole("button", { name: /clear/i })
      ).not.toBeInTheDocument();

      // Search input should be empty
      const input = screen.getByPlaceholderText("Search candidates...");
      expect(input).toHaveValue("");

      // Sort should be reset to default
      const sortSelect = screen.getByRole("combobox", {
        name: /sort candidates/i,
      });
      expect(sortSelect).toHaveValue("applied_at_desc");
    });
  });

  describe("debounced search", () => {
    it("debounces search input by 300ms", async () => {
      render(
        <SetupState stages={sampleStages}>
          <PipelineSearchBar jobId="job-1" stages={sampleStages} />
        </SetupState>
      );

      const input = screen.getByPlaceholderText("Search candidates...");
      await userEvent.type(input, "alice");

      // Before debounce fires, match count should not appear yet
      // (search query hasn't been dispatched to state)
      expect(screen.queryByText(/match/i)).not.toBeInTheDocument();

      // Advance timers past the 300ms debounce
      await act(async () => {
        vi.advanceTimersByTime(300);
      });

      // Now the search should be active
      expect(screen.getByText("1 match")).toBeInTheDocument();
    });
  });
});
