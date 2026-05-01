"use client";

import React, { createContext, useContext, useReducer } from "react";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface KanbanApplication {
  id: string;
  candidate_name: string;
  candidate_email: string;
  current_stage: string;
  status: string;
  applied_at: string;
}

export interface KanbanStage {
  id: string;
  name: string;
  color: string | null;
  sort_order: number;
  applications: KanbanApplication[];
}

export interface KanbanState {
  stages: KanbanStage[];
  selectedIds: Set<string>;
  searchQuery: string;
  stageFilter: string | null;
  sortBy: "applied_at_desc" | "applied_at_asc" | "candidate_name";
  slideOverAppId: string | null;
  isLoading: boolean;
  error: string | null;
  previousState: KanbanStage[] | null;
  totalCandidates: number;
}

export type KanbanAction =
  | { type: "SET_DATA"; stages: KanbanStage[]; totalCandidates: number }
  | { type: "SET_LOADING"; isLoading: boolean }
  | { type: "SET_ERROR"; error: string | null }
  | {
      type: "MOVE_CARD_OPTIMISTIC";
      appId: string;
      fromStageId: string;
      toStageId: string;
    }
  | { type: "MOVE_CARD_CONFIRMED" }
  | { type: "MOVE_CARD_ROLLBACK" }
  | { type: "BULK_MOVE_OPTIMISTIC"; appIds: string[]; toStageId: string }
  | { type: "BULK_MOVE_CONFIRMED" }
  | { type: "BULK_MOVE_PARTIAL_ROLLBACK"; failedIds: string[] }
  | { type: "TOGGLE_SELECT"; appId: string }
  | { type: "SELECT_ALL_IN_STAGE"; stageId: string }
  | { type: "CLEAR_SELECTION" }
  | { type: "SET_SEARCH"; query: string }
  | { type: "SET_STAGE_FILTER"; stageId: string | null }
  | { type: "SET_SORT"; sortBy: KanbanState["sortBy"] }
  | { type: "OPEN_SLIDE_OVER"; appId: string }
  | { type: "CLOSE_SLIDE_OVER" }
  | {
      type: "UPDATE_STAGE";
      stageId: string;
      name?: string;
      color?: string | null;
    };

// ---------------------------------------------------------------------------
// Initial state
// ---------------------------------------------------------------------------

const initialState: KanbanState = {
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
};

// ---------------------------------------------------------------------------
// Reducer
// ---------------------------------------------------------------------------

export function kanbanReducer(
  state: KanbanState,
  action: KanbanAction
): KanbanState {
  switch (action.type) {
    case "SET_DATA":
      return {
        ...state,
        stages: action.stages,
        totalCandidates: action.totalCandidates,
        isLoading: false,
        error: null,
      };

    case "SET_LOADING":
      return { ...state, isLoading: action.isLoading };

    case "SET_ERROR":
      return { ...state, error: action.error, isLoading: false };

    case "MOVE_CARD_OPTIMISTIC": {
      const { appId, fromStageId, toStageId } = action;

      // Find the application in the source stage
      const fromStage = state.stages.find((s) => s.id === fromStageId);
      const app = fromStage?.applications.find((a) => a.id === appId);
      if (!app) return state;

      const updatedStages = state.stages.map((stage) => {
        if (stage.id === fromStageId) {
          return {
            ...stage,
            applications: stage.applications.filter((a) => a.id !== appId),
          };
        }
        if (stage.id === toStageId) {
          return {
            ...stage,
            applications: [
              ...stage.applications,
              { ...app, current_stage: toStageId },
            ],
          };
        }
        return stage;
      });

      return {
        ...state,
        previousState: state.stages,
        stages: updatedStages,
      };
    }

    case "MOVE_CARD_CONFIRMED":
      return { ...state, previousState: null };

    case "MOVE_CARD_ROLLBACK":
      return {
        ...state,
        stages: state.previousState ?? state.stages,
        previousState: null,
      };

    case "BULK_MOVE_OPTIMISTIC": {
      const { appIds, toStageId } = action;
      const appIdSet = new Set(appIds);

      // Collect all apps being moved
      const movedApps: KanbanApplication[] = [];
      const stagesWithRemoved = state.stages.map((stage) => {
        const kept: KanbanApplication[] = [];
        for (const app of stage.applications) {
          if (appIdSet.has(app.id)) {
            movedApps.push({ ...app, current_stage: toStageId });
          } else {
            kept.push(app);
          }
        }
        return { ...stage, applications: kept };
      });

      // Add moved apps to the target stage
      const updatedStages = stagesWithRemoved.map((stage) => {
        if (stage.id === toStageId) {
          return {
            ...stage,
            applications: [...stage.applications, ...movedApps],
          };
        }
        return stage;
      });

      return {
        ...state,
        previousState: state.stages,
        stages: updatedStages,
      };
    }

    case "BULK_MOVE_CONFIRMED":
      return {
        ...state,
        previousState: null,
        selectedIds: new Set<string>(),
      };

    case "BULK_MOVE_PARTIAL_ROLLBACK": {
      const { failedIds } = action;
      if (!state.previousState) return state;

      const failedIdSet = new Set(failedIds);

      // Build a map of where each failed app was in the previous state
      const previousLocationMap = new Map<string, string>();
      for (const stage of state.previousState) {
        for (const app of stage.applications) {
          if (failedIdSet.has(app.id)) {
            previousLocationMap.set(app.id, stage.id);
          }
        }
      }

      // Collect the failed apps from current state and remove them
      const failedApps: KanbanApplication[] = [];
      const stagesWithRemoved = state.stages.map((stage) => {
        const kept: KanbanApplication[] = [];
        for (const app of stage.applications) {
          if (failedIdSet.has(app.id)) {
            failedApps.push(app);
          } else {
            kept.push(app);
          }
        }
        return { ...stage, applications: kept };
      });

      // Place failed apps back in their original stages
      const restoredStages = stagesWithRemoved.map((stage) => {
        const appsToRestore = failedApps.filter(
          (app) => previousLocationMap.get(app.id) === stage.id
        );
        if (appsToRestore.length === 0) return stage;
        // Restore original current_stage value
        const restored = appsToRestore.map((app) => ({
          ...app,
          current_stage: stage.id,
        }));
        return {
          ...stage,
          applications: [...stage.applications, ...restored],
        };
      });

      return {
        ...state,
        stages: restoredStages,
        previousState: null,
        selectedIds: new Set<string>(),
      };
    }

    case "TOGGLE_SELECT": {
      const next = new Set(state.selectedIds);
      if (next.has(action.appId)) {
        next.delete(action.appId);
      } else {
        next.add(action.appId);
      }
      return { ...state, selectedIds: next };
    }

    case "SELECT_ALL_IN_STAGE": {
      const stage = state.stages.find((s) => s.id === action.stageId);
      if (!stage) return state;
      const next = new Set(state.selectedIds);
      for (const app of stage.applications) {
        next.add(app.id);
      }
      return { ...state, selectedIds: next };
    }

    case "CLEAR_SELECTION":
      return { ...state, selectedIds: new Set<string>() };

    case "SET_SEARCH":
      return { ...state, searchQuery: action.query };

    case "SET_STAGE_FILTER":
      return { ...state, stageFilter: action.stageId };

    case "SET_SORT":
      return { ...state, sortBy: action.sortBy };

    case "OPEN_SLIDE_OVER":
      return { ...state, slideOverAppId: action.appId };

    case "CLOSE_SLIDE_OVER":
      return { ...state, slideOverAppId: null };

    case "UPDATE_STAGE": {
      const updatedStages = state.stages.map((stage) => {
        if (stage.id !== action.stageId) return stage;
        return {
          ...stage,
          ...(action.name !== undefined && { name: action.name }),
          ...(action.color !== undefined && { color: action.color }),
        };
      });
      return { ...state, stages: updatedStages };
    }

    default:
      return state;
  }
}

// ---------------------------------------------------------------------------
// Context
// ---------------------------------------------------------------------------

interface KanbanContextValue {
  state: KanbanState;
  dispatch: React.Dispatch<KanbanAction>;
}

const KanbanContext = createContext<KanbanContextValue | null>(null);

// ---------------------------------------------------------------------------
// Provider
// ---------------------------------------------------------------------------

export function KanbanProvider({ children }: { children: React.ReactNode }) {
  const [state, dispatch] = useReducer(kanbanReducer, initialState);

  return (
    <KanbanContext.Provider value={{ state, dispatch }}>
      {children}
    </KanbanContext.Provider>
  );
}

// ---------------------------------------------------------------------------
// Hook
// ---------------------------------------------------------------------------

export function useKanban(): KanbanContextValue {
  const ctx = useContext(KanbanContext);
  if (!ctx) {
    throw new Error("useKanban must be used within a KanbanProvider");
  }
  return ctx;
}
