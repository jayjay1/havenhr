"use client";

import { useState, useEffect, useRef, useCallback } from "react";
import { apiClient, ApiRequestError } from "@/lib/api";
import { scheduleInterview } from "@/lib/interviewApi";
import type { InterviewType, ScheduleInterviewPayload } from "@/types/interview";
import type { PaginatedResponse } from "@/types/api";

interface TeamMember {
  id: string;
  name: string;
  email: string;
}

interface ScheduleInterviewModalProps {
  applicationId: string;
  onClose: () => void;
  onScheduled: () => void;
}

const DURATION_OPTIONS = [
  { value: 30, label: "30 minutes" },
  { value: 45, label: "45 minutes" },
  { value: 60, label: "60 minutes" },
  { value: 90, label: "90 minutes" },
];

const TYPE_OPTIONS: { value: InterviewType; label: string }[] = [
  { value: "phone", label: "Phone" },
  { value: "video", label: "Video" },
  { value: "in_person", label: "In Person" },
];

export function ScheduleInterviewModal({
  applicationId,
  onClose,
  onScheduled,
}: ScheduleInterviewModalProps) {
  const [scheduledAt, setScheduledAt] = useState("");
  const [durationMinutes, setDurationMinutes] = useState<30 | 45 | 60 | 90>(60);
  const [interviewerId, setInterviewerId] = useState("");
  const [interviewType, setInterviewType] = useState<InterviewType>("video");
  const [location, setLocation] = useState("");
  const [notes, setNotes] = useState("");

  const [teamMembers, setTeamMembers] = useState<TeamMember[]>([]);
  const [loadingMembers, setLoadingMembers] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState("");

  const modalRef = useRef<HTMLDivElement>(null);
  const headingId = "schedule-interview-heading";

  // Fetch team members for interviewer dropdown
  useEffect(() => {
    let cancelled = false;
    async function loadMembers() {
      try {
        const response = await apiClient.get<PaginatedResponse<TeamMember>>(
          "/users?per_page=100"
        );
        if (!cancelled) {
          const data = response.data;
          if (Array.isArray(data)) {
            setTeamMembers(data as unknown as TeamMember[]);
          } else if (data && Array.isArray(data.data)) {
            setTeamMembers(data.data);
          }
        }
      } catch {
        // Silently handle
      } finally {
        if (!cancelled) setLoadingMembers(false);
      }
    }
    loadMembers();
    return () => { cancelled = true; };
  }, []);

  // Close on Escape
  useEffect(() => {
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") onClose();
    }
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [onClose]);

  // Focus trap
  useEffect(() => {
    function handleTab(e: KeyboardEvent) {
      if (e.key !== "Tab" || !modalRef.current) return;
      const focusable = modalRef.current.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
      );
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }
    document.addEventListener("keydown", handleTab);
    return () => document.removeEventListener("keydown", handleTab);
  }, []);

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setFieldErrors({});
      setGeneralError("");
      setSubmitting(true);

      try {
        const payload: ScheduleInterviewPayload = {
          job_application_id: applicationId,
          interviewer_id: interviewerId,
          scheduled_at: scheduledAt,
          duration_minutes: durationMinutes,
          interview_type: interviewType,
          location,
          ...(notes ? { notes } : {}),
        };

        await scheduleInterview(payload);
        onScheduled();
        onClose();
      } catch (err) {
        if (err instanceof ApiRequestError && err.status === 422) {
          const details = err.details as
            | { fields?: Record<string, { messages: string[] }> }
            | undefined;
          if (details?.fields) {
            const errors: Record<string, string[]> = {};
            for (const [field, info] of Object.entries(details.fields)) {
              errors[field] = info.messages;
            }
            setFieldErrors(errors);
          } else {
            setGeneralError(err.message);
          }
        } else if (err instanceof ApiRequestError) {
          setGeneralError(err.message);
        } else {
          setGeneralError("An unexpected error occurred.");
        }
      } finally {
        setSubmitting(false);
      }
    },
    [applicationId, interviewerId, scheduledAt, durationMinutes, interviewType, location, notes, onScheduled, onClose]
  );

  return (
    <>
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/50 z-50"
        onClick={onClose}
      />

      {/* Modal */}
      <div
        ref={modalRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={headingId}
        className="fixed z-50 inset-0 flex items-center justify-center p-4"
      >
        <div className="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          {/* Header */}
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 id={headingId} className="text-lg font-semibold text-gray-900">
              Schedule Interview
            </h2>
            <button
              type="button"
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
              aria-label="Close"
            >
              <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit} className="px-6 py-4 space-y-4">
            {generalError && (
              <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                {generalError}
              </div>
            )}

            {/* Date/Time */}
            <div>
              <label htmlFor="scheduled_at" className="block text-sm font-medium text-gray-700 mb-1">
                Date & Time
              </label>
              <input
                id="scheduled_at"
                type="datetime-local"
                required
                value={scheduledAt}
                onChange={(e) => setScheduledAt(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              {fieldErrors.scheduled_at && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.scheduled_at[0]}</p>
              )}
            </div>

            {/* Duration */}
            <div>
              <label htmlFor="duration_minutes" className="block text-sm font-medium text-gray-700 mb-1">
                Duration
              </label>
              <select
                id="duration_minutes"
                required
                value={durationMinutes}
                onChange={(e) => setDurationMinutes(Number(e.target.value) as 30 | 45 | 60 | 90)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                {DURATION_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>{opt.label}</option>
                ))}
              </select>
              {fieldErrors.duration_minutes && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.duration_minutes[0]}</p>
              )}
            </div>

            {/* Interviewer */}
            <div>
              <label htmlFor="interviewer_id" className="block text-sm font-medium text-gray-700 mb-1">
                Interviewer
              </label>
              <select
                id="interviewer_id"
                required
                value={interviewerId}
                onChange={(e) => setInterviewerId(e.target.value)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Select interviewer…</option>
                {loadingMembers ? (
                  <option disabled>Loading…</option>
                ) : (
                  teamMembers.map((member) => (
                    <option key={member.id} value={member.id}>
                      {member.name} ({member.email})
                    </option>
                  ))
                )}
              </select>
              {fieldErrors.interviewer_id && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.interviewer_id[0]}</p>
              )}
            </div>

            {/* Interview Type */}
            <fieldset>
              <legend className="block text-sm font-medium text-gray-700 mb-1">
                Interview Type
              </legend>
              <div className="flex gap-4">
                {TYPE_OPTIONS.map((opt) => (
                  <label key={opt.value} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input
                      type="radio"
                      name="interview_type"
                      value={opt.value}
                      checked={interviewType === opt.value}
                      onChange={() => setInterviewType(opt.value)}
                      className="text-blue-600 focus:ring-blue-500"
                    />
                    {opt.label}
                  </label>
                ))}
              </div>
              {fieldErrors.interview_type && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.interview_type[0]}</p>
              )}
            </fieldset>

            {/* Location */}
            <div>
              <label htmlFor="location" className="block text-sm font-medium text-gray-700 mb-1">
                Location
              </label>
              <input
                id="location"
                type="text"
                required
                maxLength={500}
                value={location}
                onChange={(e) => setLocation(e.target.value)}
                placeholder="e.g., Zoom link, Office Room 3A"
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              {fieldErrors.location && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.location[0]}</p>
              )}
            </div>

            {/* Notes */}
            <div>
              <label htmlFor="notes" className="block text-sm font-medium text-gray-700 mb-1">
                Notes <span className="text-gray-400">(optional)</span>
              </label>
              <textarea
                id="notes"
                maxLength={2000}
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={3}
                placeholder="Any additional notes for this interview…"
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
              />
              {fieldErrors.notes && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.notes[0]}</p>
              )}
            </div>

            {/* Actions */}
            <div className="flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
              >
                {submitting ? "Scheduling…" : "Schedule Interview"}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
