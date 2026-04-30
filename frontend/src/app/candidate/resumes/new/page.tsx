"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { candidateApiClient } from "@/lib/candidateApi";
import { ApiRequestError } from "@/lib/api";
import { Button } from "@/components/ui/Button";

const TEMPLATES = [
  {
    slug: "clean",
    label: "Clean",
    description: "Minimal and elegant. Great for any industry.",
    color: "bg-gray-100 border-gray-300 text-gray-700",
    accent: "bg-gray-600",
  },
  {
    slug: "modern",
    label: "Modern",
    description: "Bold layout with a contemporary feel.",
    color: "bg-teal-50 border-teal-300 text-teal-700",
    accent: "bg-teal-600",
  },
  {
    slug: "professional",
    label: "Professional",
    description: "Traditional format preferred by corporate recruiters.",
    color: "bg-blue-50 border-blue-300 text-blue-700",
    accent: "bg-blue-600",
  },
  {
    slug: "creative",
    label: "Creative",
    description: "Standout design for creative roles.",
    color: "bg-purple-50 border-purple-300 text-purple-700",
    accent: "bg-purple-600",
  },
] as const;

export default function CreateResumePage() {
  const router = useRouter();
  const [title, setTitle] = useState("");
  const [templateSlug, setTemplateSlug] = useState<string>("clean");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!title.trim()) {
      setError("Please enter a resume title.");
      return;
    }
    setSaving(true);
    setError("");
    try {
      const response = await candidateApiClient.post<{ id: string }>(
        "/candidate/resumes",
        { title: title.trim(), template_slug: templateSlug }
      );
      router.push(`/candidate/resumes/${response.data.id}`);
    } catch (err) {
      setError(
        err instanceof ApiRequestError
          ? err.message
          : "Failed to create resume."
      );
      setSaving(false);
    }
  }

  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Create New Resume</h1>
        <p className="mt-1 text-sm text-gray-500">
          Give your resume a title and pick a template to get started.
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Title */}
        <div className="bg-white rounded-lg border border-gray-200 p-6">
          <label
            htmlFor="resume-title"
            className="block text-sm font-medium text-gray-700 mb-1"
          >
            Resume Title{" "}
            <span className="text-red-600" aria-hidden="true">
              *
            </span>
          </label>
          <input
            id="resume-title"
            value={title}
            onChange={(e) => {
              setTitle(e.target.value);
              setError("");
            }}
            placeholder="e.g. Software Engineer Resume"
            required
            className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
          />
        </div>

        {/* Template Selection */}
        <div className="bg-white rounded-lg border border-gray-200 p-6">
          <fieldset>
            <legend className="text-sm font-medium text-gray-700 mb-3">
              Choose a Template
            </legend>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {TEMPLATES.map((tpl) => {
                const selected = templateSlug === tpl.slug;
                return (
                  <label
                    key={tpl.slug}
                    className={`
                      relative flex flex-col cursor-pointer rounded-lg border-2 p-4 transition-all
                      focus-within:ring-2 focus-within:ring-teal-500 focus-within:ring-offset-1
                      ${
                        selected
                          ? "border-teal-500 ring-2 ring-teal-500 ring-offset-1"
                          : "border-gray-200 hover:border-gray-300"
                      }
                    `}
                  >
                    <input
                      type="radio"
                      name="template"
                      value={tpl.slug}
                      checked={selected}
                      onChange={() => setTemplateSlug(tpl.slug)}
                      className="sr-only"
                    />
                    {/* Color preview bar */}
                    <div
                      className={`h-2 w-full rounded-full mb-3 ${tpl.accent}`}
                      aria-hidden="true"
                    />
                    <span className="text-sm font-semibold text-gray-900">
                      {tpl.label}
                    </span>
                    <span className="text-xs text-gray-500 mt-1">
                      {tpl.description}
                    </span>
                    {selected && (
                      <div className="absolute top-2 right-2">
                        <svg
                          className="h-5 w-5 text-teal-600"
                          fill="currentColor"
                          viewBox="0 0 20 20"
                          aria-hidden="true"
                        >
                          <path
                            fillRule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                            clipRule="evenodd"
                          />
                        </svg>
                      </div>
                    )}
                  </label>
                );
              })}
            </div>
          </fieldset>
        </div>

        {error && (
          <div
            role="alert"
            className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700"
          >
            {error}
          </div>
        )}

        <div className="flex gap-3">
          <Button type="submit" loading={saving}>
            Create Resume
          </Button>
          <Link href="/candidate/resumes">
            <Button type="button" variant="secondary">
              Cancel
            </Button>
          </Link>
        </div>
      </form>
    </div>
  );
}
