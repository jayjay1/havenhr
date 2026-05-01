import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import React from "react";
import { BulkActionToolbar } from "../BulkActionToolbar";
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
  bulkMoveApplications: vi.fn().mockResolvedValue({
    data: { success_count: 1, failed_count: 0, failed_ids: [] },
  }),
  bulkRejectApplications: vi.fn().mockResolvedValue({
    data: { success_count: 1, failed_count: 0, failed_ids: [] },
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
    applications: [],
  },
  {
    id: "stage-3",
    name: "Rejected",
    color: "#EF4444",
    sort_order: 2,
    applications: [],
  },
];

/**
 * Helper that injects state into KanbanProvider before rendering children.
 */
function StateInjector({
  stages,
  selectedIds,
}: {
  stages: KanbanStage[];
  selectedIds?: string[];
}) {
  const { dispatch } = useKanban();

  React.useEffect(() => {
    dispatch({
      type: "SET_DATA",
      stages,
      totalCandidates: stages.reduce(
        (sum, s) => sum + s.applications.length,
        0
      ),
    });
    if (selectedIds) {
      for (const id of selectedIds) {
        dispatch({ type: "TOGGLE_SELECT", appId: id });
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return null;
}

function renderToolbar({
  stages = sampleStages,
  canManage = true,
  selectedIds,
}: {
  stages?: KanbanStage[];
  canManage?: boolean;
  selectedIds?: string[];
} = {}) {
  return render(
    <KanbanProvider>
      <StateInjector stages={stages} selectedIds={selectedIds} />
      <BulkActionToolbar stages={stages} canManage={canManage} />
    </KanbanProvider>
  );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe("BulkActionToolbar", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("does not render when no items are selected", () => {
    renderToolbar({ selectedIds: [] });

    expect(
      screen.queryByRole("toolbar", { name: /bulk actions/i })
    ).not.toBeInTheDocument();
  });

  it("does not render when canManage is false", () => {
    renderToolbar({ canManage: false, selectedIds: ["app-1"] });

    expect(
      screen.queryByRole("toolbar", { name: /bulk actions/i })
    ).not.toBeInTheDocument();
  });

  it("shows selected count", () => {
    renderToolbar({ selectedIds: ["app-1", "app-2"] });

    expect(screen.getByText("2 selected")).toBeInTheDocument();
  });

  it("shows Move to Stage dropdown with all stages", () => {
    renderToolbar({ selectedIds: ["app-1"] });

    const select = screen.getByRole("combobox", { name: /move to stage/i });
    expect(select).toBeInTheDocument();

    // All stages should be listed as options (plus the placeholder)
    const options = select.querySelectorAll("option");
    // placeholder + 3 stages = 4
    expect(options).toHaveLength(4);
    expect(options[1]).toHaveTextContent("Applied");
    expect(options[2]).toHaveTextContent("Interview");
    expect(options[3]).toHaveTextContent("Rejected");
  });

  it("shows Reject All button", () => {
    renderToolbar({ selectedIds: ["app-1"] });

    expect(
      screen.getByRole("button", { name: /reject all/i })
    ).toBeInTheDocument();
  });

  it("shows Clear Selection button", () => {
    renderToolbar({ selectedIds: ["app-1"] });

    expect(
      screen.getByRole("button", { name: /clear selection/i })
    ).toBeInTheDocument();
  });

  it("Clear Selection dispatches CLEAR_SELECTION", async () => {
    const user = userEvent.setup();

    // Render with a spy component to observe state changes
    let capturedSelectedSize = -1;

    function SelectionSpy() {
      const { state } = useKanban();
      capturedSelectedSize = state.selectedIds.size;
      return null;
    }

    render(
      <KanbanProvider>
        <StateInjector stages={sampleStages} selectedIds={["app-1"]} />
        <BulkActionToolbar stages={sampleStages} canManage={true} />
        <SelectionSpy />
      </KanbanProvider>
    );

    // Verify something is selected initially
    expect(screen.getByText("1 selected")).toBeInTheDocument();

    // Click Clear Selection
    await user.click(screen.getByRole("button", { name: /clear selection/i }));

    // After clearing, the toolbar should disappear (no items selected)
    expect(
      screen.queryByRole("toolbar", { name: /bulk actions/i })
    ).not.toBeInTheDocument();
    expect(capturedSelectedSize).toBe(0);
  });
});
