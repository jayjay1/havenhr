"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { createJobPosting } from "@/lib/jobApi";
import { ApiRequestError } from "@/lib/api";
import type { EmploymentType, RemoteStatus } from "@/types/job";

const EMPLOYMENT_TYPES: { value: EmploymentType; label: string }[] = [
  { value: "full-time", label: "Full-time" },
  { value: "part-time", label: "Part-time" },
  { value: "contract", label: "Contract" },
  { value: "internship", label: "Internship" },
];

const REMOTE_OPTIONS: { value: RemoteStatus; label: string }[] = [
  { value: "remote", label: "Remote" },
  { value: "on-site", label: "On-site" },
  { value: "hybrid", label: "Hybrid" },
];

export default function CreateJobPage() {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [location, setLocation] = useState("");
  const [employmentType, setEmploymentType] = useState<EmploymentType>("full-time");
  const [department, setDepartment] = useState("");
  const [salaryMin, setSalaryMin] = useState("");
  const [salaryMax, setSalaryMax] = useState("");
  const [salaryCurrency, setSalaryCurrency] = useState("USD");
  const [requirements, setRequirements] = useState("");
  const [benefits, setBenefits] = useState("");
  const [remoteStatus, setRemoteStatus] = useState<RemoteStatus | "">("");

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!title.trim()) errors.title = "Title is required.";
    else if (title.length > 255) errors.title = "Title must be 255 characters or less.";
    if (!description.trim()) errors.description = "Description is required.";
    else if (description.length > 10000) errors.description = "Description must be 10,000 characters or less.";
    if (!location.trim()) errors.location = "Location is required.";
    else if (location.length > 255) errors.location = "Location must be 255 characters or less.";
    if (salaryMin && salaryMax && Number(salaryMin) > Number(salaryMax)) {
      errors.salaryMin = "Minimum salary cannot exceed maximum salary.";
    }
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!validate()) return;

    setLoading(true);
    setError("");
    try {
      await createJobPosting({
        title: title.trim(),
        description: description.trim(),
        location: location.trim(),
        employment_type: employmentType,
        ...(department.trim() && { department: department.trim() }),
        ...(salaryMin && { salary_min: Number(salaryMin) }),
        ...(salaryMax && { salary_max: Number(salaryMax) }),
        ...(salaryCurrency && { salary_currency: salaryCurrency }),
        ...(requirements.trim() && { requirements: requirements.trim() }),
        ...(benefits.trim() && { benefits: benefits.trim() }),
        ...(remoteStatus && { remote_status: remoteStatus as RemoteStatus }),
      });
      router.push("/dashboard/jobs");
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message);
      } else {
        setError("Failed to create job posting.");
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="max-w-3xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Create Job Posting</h1>
        <p className="mt-1 text-sm text-gray-500">Fill in the details below. The job will be saved as a draft.</p>
      </div>

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6 bg-white rounded-lg border border-gray-200 p-6">
        {/* Title */}
        <div>
          <label htmlFor="title" className="block text-sm font-medium text-gray-700 mb-1">
            Job Title <span className="text-red-600" aria-hidden="true">*</span>
          </label>
          <input
            id="title"
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            maxLength={255}
            required
            className={`block w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-offset-1 ${
              fieldErrors.title ? "border-red-500 focus:ring-red-500" : "border-gray-300 focus:ring-blue-600"
            }`}
            placeholder="e.g. Senior Software Engineer"
          />
          {fieldErrors.title && <p className="mt-1 text-sm text-red-600">{fieldErrors.title}</p>}
        </div>

        {/* Description */}
        <div>
          <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">
            Description <span className="text-red-600" aria-hidden="true">*</span>
          </label>
          <textarea
            id="description"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            maxLength={10000}
            required
            rows={8}
            className={`block w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-offset-1 ${
              fieldErrors.description ? "border-red-500 focus:ring-red-500" : "border-gray-300 focus:ring-blue-600"
            }`}
            placeholder="Describe the role, responsibilities, and what makes this opportunity great…"
          />
          <p className="mt-1 text-xs text-gray-400">{description.length}/10,000 characters</p>
          {fieldErrors.description && <p className="mt-1 text-sm text-red-600">{fieldErrors.description}</p>}
        </div>

        {/* Location + Employment Type */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label htmlFor="location" className="block text-sm font-medium text-gray-700 mb-1">
              Location <span className="text-red-600" aria-hidden="true">*</span>
            </label>
            <input
              id="location"
              type="text"
              value={location}
              onChange={(e) => setLocation(e.target.value)}
              maxLength={255}
              required
              className={`block w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-offset-1 ${
                fieldErrors.location ? "border-red-500 focus:ring-red-500" : "border-gray-300 focus:ring-blue-600"
              }`}
              placeholder="e.g. San Francisco, CA"
            />
            {fieldErrors.location && <p className="mt-1 text-sm text-red-600">{fieldErrors.location}</p>}
          </div>
          <div>
            <label htmlFor="employment_type" className="block text-sm font-medium text-gray-700 mb-1">
              Employment Type <span className="text-red-600" aria-hidden="true">*</span>
            </label>
            <select
              id="employment_type"
              value={employmentType}
              onChange={(e) => setEmploymentType(e.target.value as EmploymentType)}
              className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1"
            >
              {EMPLOYMENT_TYPES.map((t) => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Department + Remote Status */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label htmlFor="department" className="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <input
              id="department"
              type="text"
              value={department}
              onChange={(e) => setDepartment(e.target.value)}
              maxLength={255}
              className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1"
              placeholder="e.g. Engineering"
            />
          </div>
          <div>
            <label htmlFor="remote_status" className="block text-sm font-medium text-gray-700 mb-1">Remote Status</label>
            <select
              id="remote_status"
              value={remoteStatus}
              onChange={(e) => setRemoteStatus(e.target.value as RemoteStatus | "")}
              className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1"
            >
              <option value="">Not specified</option>
              {REMOTE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </select>
          </div>
        </div>

        {/* Salary */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label htmlFor="salary_min" className="block text-sm font-medium text-gray-700 mb-1">Salary Min</label>
            <input
              id="salary_min"
              type="number"
              min={0}
              value={salaryMin}
              onChange={(e) => setSalaryMin(e.target.value)}
              className={`block w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-offset-1 ${
                fieldErrors.salaryMin ? "border-red-500 focus:ring-red-500" : "border-gray-300 focus:ring-blue-600"
              }`}
              placeholder="e.g. 80000"
            />
            {fieldErrors.salaryMin && <p className="mt-1 text-sm text-red-600">{fieldErrors.salaryMin}</p>}
          </div>
          <div>
            <label htmlFor="salary_max" className="block text-sm font-medium text-gray-700 mb-1">Salary Max</label>
            <input
              id="salary_max"
              type="number"
              min={0}
              value={salaryMax}
              onChange={(e) => setSalaryMax(e.target.value)}
              className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1"
              placeholder="e.g. 120000"
            />
          </div>
          <div>
            <label htmlFor="salary_currency" className="block text-sm font-medium text-gray-700 mb-1">Currency</label>
            <input
              id="salary_currency"
              type="text"
              maxLength={3}
              value={salaryCurrency}
              onChange={(e) => setSalaryCurrency(e.target.value.toUpperCase())}
              className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1"
              placeholder="USD"
            />
          </div>
        </div>

        {/* Requirements */}
        <div>
          <label htmlFor="requirements" className="block text-sm font-medium text-gray-700 mb-1">Requirements</label>
          <textarea
            id="requirements"
            value={requirements}
            onChange={(e) => setRequirements(e.target.value)}
            maxLength={5000}
            rows={4}
            className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1"
            placeholder="List the qualifications and skills required…"
          />
          <p className="mt-1 text-xs text-gray-400">{requirements.length}/5,000 characters</p>
        </div>

        {/* Benefits */}
        <div>
          <label htmlFor="benefits" className="block text-sm font-medium text-gray-700 mb-1">Benefits</label>
          <textarea
            id="benefits"
            value={benefits}
            onChange={(e) => setBenefits(e.target.value)}
            maxLength={5000}
            rows={4}
            className="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1"
            placeholder="Describe the benefits and perks…"
          />
          <p className="mt-1 text-xs text-gray-400">{benefits.length}/5,000 characters</p>
        </div>

        {/* Actions */}
        <div className="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
          <button
            type="submit"
            disabled={loading}
            className="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:bg-blue-300 disabled:cursor-not-allowed w-full sm:w-auto"
          >
            {loading ? "Creating…" : "Create Job Posting"}
          </button>
          <button
            type="button"
            onClick={() => router.push("/dashboard/jobs")}
            className="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 w-full sm:w-auto"
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  );
}
