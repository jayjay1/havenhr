import { describe, it, expect } from "vitest";
import { renderHook, act } from "@testing-library/react";
import React from "react";
import {
  kanbanReducer,
  KanbanProvider,
  useKanban,
  type KanbanState,
  type KanbanStage,
} from "../KanbanProvider";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeStage(overrides: Partial<KanbanStage> = {}): KanbanStage {
  return {
    id: "stage-1",
    name: "Applied",
    color: null,
    sort_order: 0,
    applications: [],
    ...overrides,
  };
}

function baseState(overrides: Partial<KanbanState> = {}): KanbanState {
  return {
    stages: [],
    selectedIds: new Set<string>(),
    searchQuery: "",
    stageFilter: null,
    sortBy: "applied_at_desc",
    slideOverAppId: null,
    isLoading: false,
    error: null,
    previousState: null,
    totalCandidates: 0,
    ...overrides,
  };
}

const app1 = {
  id: "app-1",
  candidate_name: "Alice",
  candidate_email: "alice@example.com",
  current_stage: "stage-1",
  status: "active",
  applied_at: "2024-01-01T00:00:00Z",
};

const app2 = {
  id: "app-2",
  candidate_name: "Bob",
  candidate_email: "bob@example.com",
  current_stage: "stage-1",
  status: "active",
  applied_at: "2024-01-02T00:00:00Z",
};

const app3 = {
  id: "app-3",
  candidate_name: "Charlie",
  candidate_email: "charlie@example.com",
  current_stage: "stage-2",
  status: "active",
  applied_at: "2024-01-03T00:00:00Z",
};

// ---------------------------------------------------------------------------
// Reducer tests
// ---------------------------------------------------------------------------

describe("kanbanReducer", () => {
  it("SET_DATA replaces stages and totalCandidates", () => {
    const stages = [makeStage({ id: "s1" })];
    const result = kanbanReducer(baseState({ isLoading: true }), {
      type: "SET_DATA",
      stages,
      totalCandidates: 42,
    });
    expect(result.stages).toBe(stages);
    expect(result.totalCandidates).toBe(42);
    expect(result.isLoading).toBe(false);
    expect(result.error).toBeNull();
  });

  it("SET_LOADING updates isLoading", () => {
    const result = kanbanReducer(baseState(), {
      type: "SET_LOADING",
      isLoading: true,
    });
    expect(result.isLoading).toBe(true);
  });

  it("SET_ERROR sets error and clears loading", () => {
    const result = kanbanReducer(baseState({ isLoading: true }), {
      type: "SET_ERROR",
      error: "Something went wrong",
    });
    expect(result.error).toBe("Something went wrong");
    expect(result.isLoading).toBe(false);
  });

  describe("MOVE_CARD_OPTIMISTIC", () => {
    it("moves an app from one stage to another and saves previousState", () => {
      const stages = [
        makeStage({ id: "stage-1", applications: [app1, app2] }),
        makeStage({ id: "stage-2", applications: [app3] }),
      ];
      const state = baseState({ stages });

      const result = kanbanReducer(state, {
        type: "MOVE_CARD_OPTIMISTIC",
        appId: "app-1",
        fromStageId: "stage-1",
        toStageId: "stage-2",
      });

      expect(result.previousState).toBe(stages);
      const s1 = result.stages.find((s) => s.id === "stage-1")!;
      const s2 = result.stages.find((s) => s.id === "stage-2")!;
      expect(s1.applications).toHaveLength(1);
      expect(s2.applications).toHaveLength(2);
      expect(s2.applications.find((a) => a.id === "app-1")?.current_stage).toBe(
        "stage-2"
      );
    });

    it("returns unchanged state if app not found", () => {
      const state = baseState({
        stages: [makeStage({ id: "stage-1", applications: [] })],
      });
      const result = kanbanReducer(state, {
        type: "MOVE_CARD_OPTIMISTIC",
        appId: "nonexistent",
        fromStageId: "stage-1",
        toStageId: "stage-2",
      });
      expect(result).toBe(state);
    });
  });

  it("MOVE_CARD_CONFIRMED clears previousState", () => {
    const state = baseState({ previousState: [makeStage()] });
    const result = kanbanReducer(state, { type: "MOVE_CARD_CONFIRMED" });
    expect(result.previousState).toBeNull();
  });

  it("MOVE_CARD_ROLLBACK restores previousState", () => {
    const original = [makeStage({ id: "original" })];
    const state = baseState({
      stages: [makeStage({ id: "modified" })],
      previousState: original,
    });
    const result = kanbanReducer(state, { type: "MOVE_CARD_ROLLBACK" });
    expect(result.stages).toBe(original);
    expect(result.previousState).toBeNull();
  });

  describe("BULK_MOVE_OPTIMISTIC", () => {
    it("moves multiple apps to target stage", () => {
      const stages = [
        makeStage({ id: "stage-1", applications: [app1, app2] }),
        makeStage({ id: "stage-2", applications: [app3] }),
      ];
      const state = baseState({ stages });

      const result = kanbanReducer(state, {
        type: "BULK_MOVE_OPTIMISTIC",
        appIds: ["app-1", "app-2"],
        toStageId: "stage-2",
      });

      expect(result.previousState).toBe(stages);
      const s1 = result.stages.find((s) => s.id === "stage-1")!;
      const s2 = result.stages.find((s) => s.id === "stage-2")!;
      expect(s1.applications).toHaveLength(0);
      expect(s2.applications).toHaveLength(3);
    });
  });

  it("BULK_MOVE_CONFIRMED clears previousState and selectedIds", () => {
    const state = baseState({
      previousState: [makeStage()],
      selectedIds: new Set(["app-1"]),
    });
    const result = kanbanReducer(state, { type: "BULK_MOVE_CONFIRMED" });
    expect(result.previousState).toBeNull();
    expect(result.selectedIds.size).toBe(0);
  });

  describe("BULK_MOVE_PARTIAL_ROLLBACK", () => {
    it("rolls back only failed apps to their original stages", () => {
      const originalStages = [
        makeStage({ id: "stage-1", applications: [app1, app2] }),
        makeStage({ id: "stage-2", applications: [app3] }),
      ];
      // After bulk move, app1 and app2 are in stage-2
      const movedApp1 = { ...app1, current_stage: "stage-2" };
      const movedApp2 = { ...app2, current_stage: "stage-2" };
      const currentStages = [
        makeStage({ id: "stage-1", applications: [] }),
        makeStage({
          id: "stage-2",
          applications: [app3, movedApp1, movedApp2],
        }),
      ];

      const state = baseState({
        stages: currentStages,
        previousState: originalStages,
        selectedIds: new Set(["app-1", "app-2"]),
      });

      // app-2 failed, should go back to stage-1
      const result = kanbanReducer(state, {
        type: "BULK_MOVE_PARTIAL_ROLLBACK",
        failedIds: ["app-2"],
      });

      const s1 = result.stages.find((s) => s.id === "stage-1")!;
      const s2 = result.stages.find((s) => s.id === "stage-2")!;
      expect(s1.applications).toHaveLength(1);
      expect(s1.applications[0].id).toBe("app-2");
      expect(s2.applications).toHaveLength(2); // app3 + movedApp1
      expect(result.previousState).toBeNull();
      expect(result.selectedIds.size).toBe(0);
    });

    it("returns state unchanged if no previousState", () => {
      const state = baseState({ previousState: null });
      const result = kanbanReducer(state, {
        type: "BULK_MOVE_PARTIAL_ROLLBACK",
        failedIds: ["app-1"],
      });
      expect(result).toBe(state);
    });
  });

  describe("selection actions", () => {
    it("TOGGLE_SELECT adds an id", () => {
      const result = kanbanReducer(baseState(), {
        type: "TOGGLE_SELECT",
        appId: "app-1",
      });
      expect(result.selectedIds.has("app-1")).toBe(true);
    });

    it("TOGGLE_SELECT removes an already-selected id", () => {
      const state = baseState({ selectedIds: new Set(["app-1"]) });
      const result = kanbanReducer(state, {
        type: "TOGGLE_SELECT",
        appId: "app-1",
      });
      expect(result.selectedIds.has("app-1")).toBe(false);
    });

    it("SELECT_ALL_IN_STAGE adds all app ids from the stage", () => {
      const stages = [
        makeStage({ id: "stage-1", applications: [app1, app2] }),
      ];
      const state = baseState({ stages });
      const result = kanbanReducer(state, {
        type: "SELECT_ALL_IN_STAGE",
        stageId: "stage-1",
      });
      expect(result.selectedIds.has("app-1")).toBe(true);
      expect(result.selectedIds.has("app-2")).toBe(true);
    });

    it("CLEAR_SELECTION empties selectedIds", () => {
      const state = baseState({ selectedIds: new Set(["app-1", "app-2"]) });
      const result = kanbanReducer(state, { type: "CLEAR_SELECTION" });
      expect(result.selectedIds.size).toBe(0);
    });
  });

  describe("filter and sort actions", () => {
    it("SET_SEARCH updates searchQuery", () => {
      const result = kanbanReducer(baseState(), {
        type: "SET_SEARCH",
        query: "alice",
      });
      expect(result.searchQuery).toBe("alice");
    });

    it("SET_STAGE_FILTER updates stageFilter", () => {
      const result = kanbanReducer(baseState(), {
        type: "SET_STAGE_FILTER",
        stageId: "stage-1",
      });
      expect(result.stageFilter).toBe("stage-1");
    });

    it("SET_STAGE_FILTER can clear filter with null", () => {
      const state = baseState({ stageFilter: "stage-1" });
      const result = kanbanReducer(state, {
        type: "SET_STAGE_FILTER",
        stageId: null,
      });
      expect(result.stageFilter).toBeNull();
    });

    it("SET_SORT updates sortBy", () => {
      const result = kanbanReducer(baseState(), {
        type: "SET_SORT",
        sortBy: "candidate_name",
      });
      expect(result.sortBy).toBe("candidate_name");
    });
  });

  describe("slide-over actions", () => {
    it("OPEN_SLIDE_OVER sets slideOverAppId", () => {
      const result = kanbanReducer(baseState(), {
        type: "OPEN_SLIDE_OVER",
        appId: "app-1",
      });
      expect(result.slideOverAppId).toBe("app-1");
    });

    it("CLOSE_SLIDE_OVER clears slideOverAppId", () => {
      const state = baseState({ slideOverAppId: "app-1" });
      const result = kanbanReducer(state, { type: "CLOSE_SLIDE_OVER" });
      expect(result.slideOverAppId).toBeNull();
    });
  });

  describe("UPDATE_STAGE", () => {
    it("updates stage name", () => {
      const stages = [makeStage({ id: "stage-1", name: "Applied" })];
      const result = kanbanReducer(baseState({ stages }), {
        type: "UPDATE_STAGE",
        stageId: "stage-1",
        name: "Screening",
      });
      expect(result.stages[0].name).toBe("Screening");
    });

    it("updates stage color", () => {
      const stages = [makeStage({ id: "stage-1", color: null })];
      const result = kanbanReducer(baseState({ stages }), {
        type: "UPDATE_STAGE",
        stageId: "stage-1",
        color: "#FF5733",
      });
      expect(result.stages[0].color).toBe("#FF5733");
    });

    it("can set color to null", () => {
      const stages = [makeStage({ id: "stage-1", color: "#FF5733" })];
      const result = kanbanReducer(baseState({ stages }), {
        type: "UPDATE_STAGE",
        stageId: "stage-1",
        color: null,
      });
      expect(result.stages[0].color).toBeNull();
    });

    it("does not modify other stages", () => {
      const stages = [
        makeStage({ id: "stage-1", name: "Applied" }),
        makeStage({ id: "stage-2", name: "Interview" }),
      ];
      const result = kanbanReducer(baseState({ stages }), {
        type: "UPDATE_STAGE",
        stageId: "stage-1",
        name: "Screening",
      });
      expect(result.stages[1].name).toBe("Interview");
    });
  });
});

// ---------------------------------------------------------------------------
// Provider + hook tests
// ---------------------------------------------------------------------------

describe("KanbanProvider and useKanban", () => {
  it("provides state and dispatch via context", () => {
    const wrapper = ({ children }: { children: React.ReactNode }) => (
      <KanbanProvider>{children}</KanbanProvider>
    );

    const { result } = renderHook(() => useKanban(), { wrapper });

    expect(result.current.state.stages).toEqual([]);
    expect(result.current.state.isLoading).toBe(false);
    expect(typeof result.current.dispatch).toBe("function");
  });

  it("dispatching SET_LOADING updates state", () => {
    const wrapper = ({ children }: { children: React.ReactNode }) => (
      <KanbanProvider>{children}</KanbanProvider>
    );

    const { result } = renderHook(() => useKanban(), { wrapper });

    act(() => {
      result.current.dispatch({ type: "SET_LOADING", isLoading: true });
    });

    expect(result.current.state.isLoading).toBe(true);
  });

  it("throws when useKanban is used outside provider", () => {
    expect(() => {
      renderHook(() => useKanban());
    }).toThrow("useKanban must be used within a KanbanProvider");
  });
});
