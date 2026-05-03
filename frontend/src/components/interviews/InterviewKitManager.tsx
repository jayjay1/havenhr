"use client";

import { useState, useEffect, useCallback } from "react";
import { ApiRequestError } from "@/lib/api";
import {
  listInterviewKitsForJob,
  deleteInterviewKit,
  getInterviewKitDetail,
} from "@/lib/interviewKitApi";
import { InterviewKitForm } from "./InterviewKitForm";
import type { StageKits, InterviewKit } from "@/types/interviewKit";

interface InterviewKitManagerProps {
  jobId: string;
  stages: { id: string; name: string }[];
}

export function InterviewKitManager({ jobId, stages }: InterviewKitManagerProps) {
  const [stageKits, setStageKits] = useState<StageKits[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [formState, setFormState] = useState<{
    stageId: string;
    kit?: InterviewKit;
  } | null>(null);
  const [confirmDeleteId, setConfirmDeleteId] = useState<string | null>(null);

  const loadKits = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const res = await listInterviewKitsForJob(jobId);
      setStageKits(res.data);
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Failed to load interview kits."
      );
    } finally {
      setLoading(false);
    }
  }, [jobId]);

  useEffect(() => {
    loadKits();
  }, [loadKits]);

  const handleDelete = async (kitId: string) => {
    try {
      await deleteInterviewKit(kitId);
      setConfirmDeleteId(null);
      await loadKits();
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Failed to delete interview kit."
      );
    }
  };

  const handleEdit = async (kitId: string, stageId: string) => {
    try {
      const res = await getInterviewKitDetail(kitId);
      setFormState({ stageId, kit: res.data });
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Failed to load kit details."
      );
    }
  };

  if (formState) {
    return (
      <InterviewKitForm
        jobId={jobId}
        stageId={formState.stageId}
        existingKit={formState.kit}
        onSaved={() => {
          setFormState(null);
          loadKits();
        }}
        onCancel={() => setFormState(null)}
      />
    );
  }

  if (loading) {
    return (
      <div className="space-y-4 animate-pulse">
        {[1, 2].map((i) => (
          <div key={i} className="h-20 bg-gray-100 rounded-lg" />
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
        {error}
      </div>
    );
  }

  // Merge API data with provided stages (in case some stages have no kits yet)
  const mergedStages = stages.map((stage) => {
    const found = stageKits.find((sk) => sk.stage_id === stage.id);
    return {
      stage_id: stage.id,
      stage_name: stage.name,
      kits: found?.kits ?? [],
    };
  });

  return (
    <div className="space-y-4">
      {mergedStages.map((stage) => (
        <div key={stage.stage_id} className="border border-gray-200 rounded-lg p-4">
          <div className="flex items-center justify-between mb-3">
            <h4 className="text-sm font-semibold text-gray-900">{stage.stage_name}</h4>
            <button
              type="button"
              onClick={() => setFormState({ stageId: stage.stage_id })}
              className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              Add Kit
            </button>
          </div>

          {stage.kits.length === 0 ? (
            <p className="text-xs text-gray-500">No interview kits for this stage.</p>
          ) : (
            <div className="space-y-2">
              {stage.kits.map((kit) => (
                <div
                  key={kit.id}
                  className="flex items-center justify-between bg-gray-50 rounded-md px-3 py-2"
                >
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-gray-900 truncate">{kit.name}</p>
                    <p className="text-xs text-gray-500">
                      {kit.question_count} question{kit.question_count !== 1 ? "s" : ""}
                      {kit.description && ` · ${kit.description}`}
                    </p>
                  </div>
                  <div className="flex items-center gap-1 shrink-0 ml-2">
                    <button
                      type="button"
                      onClick={() => handleEdit(kit.id, stage.stage_id)}
                      className="px-2 py-1 text-xs font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-200 rounded"
                    >
                      Edit
                    </button>
                    {confirmDeleteId === kit.id ? (
                      <div className="flex items-center gap-1">
                        <button
                          type="button"
                          onClick={() => handleDelete(kit.id)}
                          className="px-2 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700"
                        >
                          Confirm
                        </button>
                        <button
                          type="button"
                          onClick={() => setConfirmDeleteId(null)}
                          className="px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200 rounded"
                        >
                          No
                        </button>
                      </div>
                    ) : (
                      <button
                        type="button"
                        onClick={() => setConfirmDeleteId(kit.id)}
                        className="px-2 py-1 text-xs font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded"
                      >
                        Delete
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ))}
    </div>
  );
}
