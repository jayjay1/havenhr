"use client";

import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useKanban, type KanbanStage } from "./KanbanProvider";
import { fetchJobApplicationsWithSearch } from "@/lib/pipelineApi";

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface PipelineSearchBarProps {
  jobId: string;
  stages: KanbanStage[];
}

// ---------------------------------------------------------------------------
// Sort options
// ---------------------------------------------------------------------------

const SORT_OPTIONS = [
  { value: "applied_at_desc" as const, label: "Applied: Newest" },
  { value: "applied_at_asc" as const, label: "Applied: Oldest" },
  { value: "candidate_name" as const, label: "Name: A-Z" },
];

// ---------------------------------------------------------------------------
// PipelineSearchBar
// ---------------------------------------------------------------------------

export function PipelineSearchBar({ jobId, stages }: PipelineSearchBarProps) {
  const { state, dispatch } = useKanban();
  const [localQuery, setLocalQuery] = useState(state.searchQuery);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Sync local query when state is cleared externally
  useEffect(() => {
    if (state.searchQuery === "" && localQuery !== "") {
      setLocalQuery("");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state.searchQuery]);

  // Server-side search for large pipelines
  const handleServerSearch = useCallback(
    async (query: string) => {
      try {
        const response = await fetchJobApplicationsWithSearch(jobId, {
          q: query,
        });
        // Group applications by stage
        const stageMap = new Map<string, KanbanStage>(
          state.stages.map((s) => [s.id, { ...s, applications: [] }])
        );
        for (const app of response.data) {
          const stage = stageMap.get(app.current_stage);
          if (stage) {
            stage.applications.push(app);
          }
        }
        dispatch({
          type: "SET_DATA",
          stages: Array.from(stageMap.values()),
          totalCandidates: state.totalCandidates,
        });
      } catch {
        // Fall back to client-side filtering on error
        dispatch({ type: "SET_SEARCH", query });
      }
    },
    [jobId, state.stages, state.totalCandidates, dispatch]
  );

  // Debounced search handler
  const handleSearchChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const query = e.target.value;
      setLocalQuery(query);

      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }

      debounceRef.current = setTimeout(() => {
        if (state.totalCandidates > 200 && query.trim()) {
          handleServerSearch(query.trim());
        } else {
          dispatch({ type: "SET_SEARCH", query });
        }
      }, 300);
    },
    [state.totalCandidates, dispatch, handleServerSearch]
  );

  // Cleanup debounce on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, []);

  // Stage filter handler
  const handleStageFilterChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      const value = e.target.value;
      dispatch({
        type: "SET_STAGE_FILTER",
        stageId: value === "" ? null : value,
      });
    },
    [dispatch]
  );

  // Sort handler
  const handleSortChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      dispatch({
        type: "SET_SORT",
        sortBy: e.target.value as "applied_at_desc" | "applied_at_asc" | "candidate_name",
      });
    },
    [dispatch]
  );

  // Clear all filters
  const handleClear = useCallback(() => {
    setLocalQuery("");
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }
    dispatch({ type: "SET_SEARCH", query: "" });
    dispatch({ type: "SET_STAGE_FILTER", stageId: null });
    dispatch({ type: "SET_SORT", sortBy: "applied_at_desc" });
  }, [dispatch]);

  // Compute match count when search is active
  const matchCount = useMemo(() => {
    if (!state.searchQuery.trim()) return 0;
    const q = state.searchQuery.toLowerCase();
    let count = 0;
    const stagesToSearch = state.stageFilter
      ? state.stages.filter((s) => s.id === state.stageFilter)
      : state.stages;
    for (const stage of stagesToSearch) {
      for (const app of stage.applications) {
        if (
          app.candidate_name.toLowerCase().includes(q) ||
          app.candidate_email.toLowerCase().includes(q)
        ) {
          count++;
        }
      }
    }
    return count;
  }, [state.searchQuery, state.stages, state.stageFilter]);

  const isSearchActive = state.searchQuery.trim().length > 0;

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4 mb-4">
      {/* Search input */}
      <div className="relative flex-1">
        <svg
          className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none"
          fill="none"
          viewBox="0 0 24 24"
          strokeWidth={1.5}
          stroke="currentColor"
          aria-hidden="true"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
          />
        </svg>
        <input
          type="text"
          value={localQuery}
          onChange={handleSearchChange}
          placeholder="Search candidates..."
          aria-label="Search candidates"
          className="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>

      {/* Stage filter dropdown */}
      <select
        value={state.stageFilter ?? ""}
        onChange={handleStageFilterChange}
        aria-label="Filter by stage"
        className="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
      >
        <option value="">All Stages</option>
        {stages.map((stage) => (
          <option key={stage.id} value={stage.id}>
            {stage.name}
          </option>
        ))}
      </select>

      {/* Sort selector */}
      <select
        value={state.sortBy}
        onChange={handleSortChange}
        aria-label="Sort candidates"
        className="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
      >
        {SORT_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>

      {/* Match count and Clear button */}
      {isSearchActive && (
        <div className="flex items-center gap-2 text-sm">
          <span className="text-gray-600 whitespace-nowrap">
            {matchCount} {matchCount === 1 ? "match" : "matches"}
          </span>
          <button
            type="button"
            onClick={handleClear}
            className="px-2 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-colors"
          >
            Clear
          </button>
        </div>
      )}
    </div>
  );
}
