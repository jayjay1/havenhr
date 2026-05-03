"use client";

import { useState, useEffect, useCallback } from "react";
import { ApiRequestError } from "@/lib/api";
import {
  createInterviewKit,
  updateInterviewKit,
  listInterviewKitTemplates,
  createInterviewKitFromTemplate,
} from "@/lib/interviewKitApi";
import type {
  InterviewKit,
  InterviewKitTemplate,
  QuestionCategory,
} from "@/types/interviewKit";

interface QuestionRow {
  text: string;
  category: QuestionCategory;
  scoring_rubric: string;
}

interface InterviewKitFormProps {
  jobId: string;
  stageId: string;
  existingKit?: InterviewKit;
  onSaved: () => void;
  onCancel: () => void;
}

const CATEGORIES: { value: QuestionCategory; label: string }[] = [
  { value: "technical", label: "Technical" },
  { value: "behavioral", label: "Behavioral" },
  { value: "cultural", label: "Cultural" },
  { value: "experience", label: "Experience" },
];

export function InterviewKitForm({
  jobId,
  stageId,
  existingKit,
  onSaved,
  onCancel,
}: InterviewKitFormProps) {
  const [name, setName] = useState(existingKit?.name ?? "");
  const [description, setDescription] = useState(existingKit?.description ?? "");
  const [questions, setQuestions] = useState<QuestionRow[]>(
    existingKit?.questions.map((q) => ({
      text: q.text,
      category: q.category,
      scoring_rubric: q.scoring_rubric ?? "",
    })) ?? [{ text: "", category: "technical", scoring_rubric: "" }]
  );
  const [templates, setTemplates] = useState<InterviewKitTemplate[]>([]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!existingKit) {
      listInterviewKitTemplates()
        .then((res) => setTemplates(res.data))
        .catch(() => {});
    }
  }, [existingKit]);

  const validate = useCallback((): boolean => {
    const newErrors: Record<string, string> = {};
    if (!name.trim()) newErrors.name = "Name is required.";
    if (questions.length === 0) newErrors.questions = "At least one question is required.";
    questions.forEach((q, i) => {
      if (!q.text.trim()) newErrors[`question_${i}_text`] = "Question text is required.";
    });
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }, [name, questions]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    setSaving(true);
    setError("");

    try {
      const payload = {
        name: name.trim(),
        description: description.trim() || undefined,
        questions: questions.map((q, i) => ({
          text: q.text.trim(),
          category: q.category,
          sort_order: i,
          scoring_rubric: q.scoring_rubric.trim() || undefined,
        })),
      };

      if (existingKit) {
        await updateInterviewKit(existingKit.id, payload);
      } else {
        await createInterviewKit(jobId, stageId, payload);
      }
      onSaved();
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Failed to save interview kit."
      );
    } finally {
      setSaving(false);
    }
  };

  const handleTemplateSelect = async (templateKey: string) => {
    if (!templateKey) return;

    // Option 1: Pre-fill the form from template data
    const template = templates.find((t) => t.key === templateKey);
    if (template) {
      setName(template.name);
      setDescription(template.description);
      setQuestions(
        template.questions.map((q) => ({
          text: q.text,
          category: q.category,
          scoring_rubric: q.scoring_rubric ?? "",
        }))
      );
    }
  };

  const addQuestion = () => {
    setQuestions([...questions, { text: "", category: "technical", scoring_rubric: "" }]);
  };

  const removeQuestion = (index: number) => {
    setQuestions(questions.filter((_, i) => i !== index));
  };

  const moveQuestion = (index: number, direction: "up" | "down") => {
    const newQuestions = [...questions];
    const targetIndex = direction === "up" ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= newQuestions.length) return;
    [newQuestions[index], newQuestions[targetIndex]] = [newQuestions[targetIndex], newQuestions[index]];
    setQuestions(newQuestions);
  };

  const updateQuestion = (index: number, field: keyof QuestionRow, value: string) => {
    const newQuestions = [...questions];
    newQuestions[index] = { ...newQuestions[index], [field]: value };
    setQuestions(newQuestions);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <h3 className="text-base font-semibold text-gray-900">
        {existingKit ? "Edit Interview Kit" : "Create Interview Kit"}
      </h3>

      {error && (
        <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
          {error}
        </div>
      )}

      {/* Template selection (only for new kits) */}
      {!existingKit && templates.length > 0 && (
        <div>
          <label htmlFor="template-select" className="block text-sm font-medium text-gray-700 mb-1">
            Start from template
          </label>
          <select
            id="template-select"
            onChange={(e) => handleTemplateSelect(e.target.value)}
            className="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Select a template…</option>
            {templates.map((t) => (
              <option key={t.key} value={t.key}>
                {t.name}
              </option>
            ))}
          </select>
        </div>
      )}

      {/* Name */}
      <div>
        <label htmlFor="kit-name" className="block text-sm font-medium text-gray-700 mb-1">
          Name *
        </label>
        <input
          id="kit-name"
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value)}
          maxLength={255}
          className="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name}</p>}
      </div>

      {/* Description */}
      <div>
        <label htmlFor="kit-description" className="block text-sm font-medium text-gray-700 mb-1">
          Description
        </label>
        <textarea
          id="kit-description"
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          maxLength={2000}
          rows={2}
          className="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
        />
      </div>

      {/* Questions */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <label className="block text-sm font-medium text-gray-700">Questions *</label>
          <button
            type="button"
            onClick={addQuestion}
            className="text-xs font-medium text-blue-600 hover:text-blue-700"
          >
            + Add Question
          </button>
        </div>
        {errors.questions && <p className="text-xs text-red-600 mb-2">{errors.questions}</p>}

        <div className="space-y-3">
          {questions.map((q, i) => (
            <div key={i} className="border border-gray-200 rounded-md p-3 space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-gray-500">Question {i + 1}</span>
                <div className="flex items-center gap-1">
                  <button
                    type="button"
                    onClick={() => moveQuestion(i, "up")}
                    disabled={i === 0}
                    className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                    aria-label={`Move question ${i + 1} up`}
                  >
                    ↑
                  </button>
                  <button
                    type="button"
                    onClick={() => moveQuestion(i, "down")}
                    disabled={i === questions.length - 1}
                    className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30"
                    aria-label={`Move question ${i + 1} down`}
                  >
                    ↓
                  </button>
                  {questions.length > 1 && (
                    <button
                      type="button"
                      onClick={() => removeQuestion(i)}
                      className="p-1 text-red-400 hover:text-red-600"
                      aria-label={`Remove question ${i + 1}`}
                    >
                      ✕
                    </button>
                  )}
                </div>
              </div>

              <textarea
                value={q.text}
                onChange={(e) => updateQuestion(i, "text", e.target.value)}
                placeholder="Enter question text…"
                rows={2}
                maxLength={1000}
                className="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                aria-label={`Question ${i + 1} text`}
              />
              {errors[`question_${i}_text`] && (
                <p className="text-xs text-red-600">{errors[`question_${i}_text`]}</p>
              )}

              <div className="flex gap-2">
                <div className="flex-1">
                  <select
                    value={q.category}
                    onChange={(e) => updateQuestion(i, "category", e.target.value)}
                    className="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    aria-label={`Question ${i + 1} category`}
                  >
                    {CATEGORIES.map((c) => (
                      <option key={c.value} value={c.value}>
                        {c.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <textarea
                value={q.scoring_rubric}
                onChange={(e) => updateQuestion(i, "scoring_rubric", e.target.value)}
                placeholder="Scoring rubric (optional)…"
                rows={1}
                maxLength={2000}
                className="w-full text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none text-gray-500"
                aria-label={`Question ${i + 1} scoring rubric`}
              />
            </div>
          ))}
        </div>
      </div>

      {/* Actions */}
      <div className="flex gap-2 pt-2">
        <button
          type="submit"
          disabled={saving}
          className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          {saving ? "Saving…" : existingKit ? "Update Kit" : "Create Kit"}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400"
        >
          Cancel
        </button>
      </div>
    </form>
  );
}
