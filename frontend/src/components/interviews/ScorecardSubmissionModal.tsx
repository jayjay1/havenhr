"use client";

import { useState, useEffect, useRef, useCallback } from "react";
import { ApiRequestError } from "@/lib/api";
import { getScorecardForm, submitScorecard, updateScorecard } from "@/lib/scorecardApi";
import type { Scorecard, ScorecardFormCriterion, OverallRecommendation } from "@/types/scorecard";

interface ScorecardSubmissionModalProps {
  interviewId: string;
  existingScorecard?: Scorecard;
  onClose: () => void;
  onSubmitted: () => void;
}

const RECOMMENDATIONS: { value: OverallRecommendation; label: string; color: string }[] = [
  { value: "strong_no", label: "Strong No", color: "bg-red-100 text-red-700 border-red-300" },
  { value: "no", label: "No", color: "bg-orange-100 text-orange-700 border-orange-300" },
  { value: "mixed", label: "Mixed", color: "bg-yellow-100 text-yellow-700 border-yellow-300" },
  { value: "yes", label: "Yes", color: "bg-green-100 text-green-700 border-green-300" },
  { value: "strong_yes", label: "Strong Yes", color: "bg-emerald-100 text-emerald-700 border-emerald-300" },
];

function StarRating({
  value,
  onChange,
  label,
}: {
  value: number;
  onChange: (v: number) => void;
  label: string;
}) {
  return (
    <div className="flex items-center gap-1" role="radiogroup" aria-label={label}>
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          onClick={() => onChange(star)}
          className={`h-7 w-7 rounded-full text-sm font-medium border focus:outline-none focus:ring-2 focus:ring-blue-500 ${
            star <= value
              ? "bg-yellow-400 text-yellow-900 border-yellow-500"
              : "bg-gray-100 text-gray-400 border-gray-300 hover:bg-gray-200"
          }`}
          role="radio"
          aria-checked={star === value}
          aria-label={`${star} star${star !== 1 ? "s" : ""}`}
        >
          {star}
        </button>
      ))}
    </div>
  );
}

export function ScorecardSubmissionModal({
  interviewId,
  existingScorecard,
  onClose,
  onSubmitted,
}: ScorecardSubmissionModalProps) {
  const [formCriteria, setFormCriteria] = useState<ScorecardFormCriterion[]>([]);
  const [hasKit, setHasKit] = useState(false);
  const [loading, setLoading] = useState(!existingScorecard);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState(false);

  // Form state
  const [overallRating, setOverallRating] = useState(existingScorecard?.overall_rating ?? 0);
  const [recommendation, setRecommendation] = useState<OverallRecommendation | "">(
    existingScorecard?.overall_recommendation ?? ""
  );
  const [notes, setNotes] = useState(existingScorecard?.notes ?? "");
  const [criteriaRatings, setCriteriaRatings] = useState<
    { rating: number; notes: string }[]
  >(
    existingScorecard?.criteria.map((c) => ({ rating: c.rating, notes: c.notes ?? "" })) ?? []
  );

  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});

  const panelRef = useRef<HTMLDivElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);

  // Fetch form criteria
  useEffect(() => {
    if (existingScorecard) {
      setFormCriteria(
        existingScorecard.criteria.map((c) => ({
          question_text: c.question_text,
          category: c.category,
          sort_order: c.sort_order,
          scoring_rubric: null,
        }))
      );
      setHasKit(existingScorecard.criteria.length > 0);
      return;
    }

    let cancelled = false;
    async function loadForm() {
      try {
        const res = await getScorecardForm(interviewId);
        if (cancelled) return;
        setFormCriteria(res.data.criteria);
        setHasKit(res.data.has_kit);
        setCriteriaRatings(res.data.criteria.map(() => ({ rating: 0, notes: "" })));
      } catch (err) {
        if (!cancelled) {
          setError(
            err instanceof ApiRequestError ? err.message : "Failed to load scorecard form."
          );
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    loadForm();
    return () => { cancelled = true; };
  }, [interviewId, existingScorecard]);

  // Focus trap and escape
  useEffect(() => {
    closeButtonRef.current?.focus();

    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") onClose();
      if (e.key === "Tab" && panelRef.current) {
        const focusable = panelRef.current.querySelectorAll<HTMLElement>(
          'button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
        );
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [onClose]);

  const validate = useCallback((): boolean => {
    const errs: Record<string, string> = {};
    if (overallRating === 0) errs.overall_rating = "Overall rating is required.";
    if (!recommendation) errs.recommendation = "Recommendation is required.";
    criteriaRatings.forEach((c, i) => {
      if (hasKit && c.rating === 0) errs[`criteria_${i}`] = "Rating is required.";
    });
    setValidationErrors(errs);
    return Object.keys(errs).length === 0;
  }, [overallRating, recommendation, criteriaRatings, hasKit]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    setSaving(true);
    setError("");

    try {
      const criteria = hasKit
        ? formCriteria.map((fc, i) => ({
            question_text: fc.question_text,
            category: fc.category,
            sort_order: fc.sort_order,
            rating: criteriaRatings[i].rating,
            notes: criteriaRatings[i].notes || undefined,
          }))
        : undefined;

      const payload = {
        overall_rating: overallRating,
        overall_recommendation: recommendation as OverallRecommendation,
        notes: notes || undefined,
        criteria,
      };

      if (existingScorecard) {
        await updateScorecard(existingScorecard.id, payload);
      } else {
        await submitScorecard(interviewId, payload);
      }

      setSuccess(true);
      setTimeout(() => {
        onSubmitted();
        onClose();
      }, 1000);
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Failed to submit scorecard."
      );
    } finally {
      setSaving(false);
    }
  };

  const updateCriterionRating = (index: number, rating: number) => {
    const updated = [...criteriaRatings];
    updated[index] = { ...updated[index], rating };
    setCriteriaRatings(updated);
  };

  const updateCriterionNotes = (index: number, notes: string) => {
    const updated = [...criteriaRatings];
    updated[index] = { ...updated[index], notes };
    setCriteriaRatings(updated);
  };

  return (
    <>
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/50 z-50"
        onClick={onClose}
      />

      {/* Modal */}
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="scorecard-modal-title"
        className="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div
          className="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 id="scorecard-modal-title" className="text-lg font-semibold text-gray-900">
              {existingScorecard ? "Edit Scorecard" : "Submit Scorecard"}
            </h2>
            <button
              ref={closeButtonRef}
              type="button"
              onClick={onClose}
              className="h-8 w-8 rounded-md text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 inline-flex items-center justify-center"
              aria-label="Close"
            >
              ✕
            </button>
          </div>

          {/* Content */}
          <div className="px-6 py-4">
            {success && (
              <div className="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700 mb-4">
                Scorecard {existingScorecard ? "updated" : "submitted"} successfully!
              </div>
            )}

            {error && (
              <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700 mb-4">
                {error}
              </div>
            )}

            {loading ? (
              <div className="space-y-4 animate-pulse">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="h-16 bg-gray-100 rounded" />
                ))}
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-5">
                {/* Criteria ratings */}
                {hasKit && formCriteria.length > 0 && (
                  <div className="space-y-4">
                    <h3 className="text-sm font-medium text-gray-900">Criteria</h3>
                    {formCriteria.map((criterion, i) => (
                      <div key={i} className="border border-gray-200 rounded-md p-3 space-y-2">
                        <div className="flex items-start justify-between gap-2">
                          <div>
                            <p className="text-sm text-gray-900">{criterion.question_text}</p>
                            <span className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 mt-1">
                              {criterion.category}
                            </span>
                          </div>
                        </div>
                        {criterion.scoring_rubric && (
                          <p className="text-xs text-gray-500 italic">{criterion.scoring_rubric}</p>
                        )}
                        <StarRating
                          value={criteriaRatings[i]?.rating ?? 0}
                          onChange={(v) => updateCriterionRating(i, v)}
                          label={`Rating for: ${criterion.question_text}`}
                        />
                        {validationErrors[`criteria_${i}`] && (
                          <p className="text-xs text-red-600">{validationErrors[`criteria_${i}`]}</p>
                        )}
                        <textarea
                          value={criteriaRatings[i]?.notes ?? ""}
                          onChange={(e) => updateCriterionNotes(i, e.target.value)}
                          placeholder="Notes (optional)…"
                          rows={1}
                          maxLength={2000}
                          className="w-full text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                          aria-label={`Notes for: ${criterion.question_text}`}
                        />
                      </div>
                    ))}
                  </div>
                )}

                {/* Overall rating */}
                <div>
                  <label className="block text-sm font-medium text-gray-900 mb-2">
                    Overall Rating *
                  </label>
                  <StarRating
                    value={overallRating}
                    onChange={setOverallRating}
                    label="Overall rating"
                  />
                  {validationErrors.overall_rating && (
                    <p className="text-xs text-red-600 mt-1">{validationErrors.overall_rating}</p>
                  )}
                </div>

                {/* Recommendation */}
                <div>
                  <label className="block text-sm font-medium text-gray-900 mb-2">
                    Recommendation *
                  </label>
                  <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Recommendation">
                    {RECOMMENDATIONS.map((rec) => (
                      <button
                        key={rec.value}
                        type="button"
                        onClick={() => setRecommendation(rec.value)}
                        className={`px-3 py-1.5 text-xs font-medium rounded-md border focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                          recommendation === rec.value
                            ? rec.color + " ring-2 ring-offset-1 ring-blue-500"
                            : "bg-gray-50 text-gray-600 border-gray-300 hover:bg-gray-100"
                        }`}
                        role="radio"
                        aria-checked={recommendation === rec.value}
                      >
                        {rec.label}
                      </button>
                    ))}
                  </div>
                  {validationErrors.recommendation && (
                    <p className="text-xs text-red-600 mt-1">{validationErrors.recommendation}</p>
                  )}
                </div>

                {/* Notes */}
                <div>
                  <label htmlFor="scorecard-notes" className="block text-sm font-medium text-gray-900 mb-1">
                    Notes
                  </label>
                  <textarea
                    id="scorecard-notes"
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="Overall notes about the candidate…"
                    rows={3}
                    maxLength={5000}
                    className="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                  />
                </div>

                {/* Actions */}
                <div className="flex gap-2 pt-2 border-t border-gray-200">
                  <button
                    type="submit"
                    disabled={saving || success}
                    className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    {saving ? "Submitting…" : existingScorecard ? "Update" : "Submit Scorecard"}
                  </button>
                  <button
                    type="button"
                    onClick={onClose}
                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400"
                  >
                    Cancel
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
