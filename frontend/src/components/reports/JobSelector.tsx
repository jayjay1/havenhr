"use client";

export interface JobOption {
  id: string;
  title: string;
}

export interface JobSelectorProps {
  jobId: string | null;
  jobs: JobOption[];
  onChange: (jobId: string | null) => void;
}

/**
 * Dropdown selector for filtering reports by job posting.
 * Includes an "All Jobs" default option.
 */
export function JobSelector({ jobId, jobs, onChange }: JobSelectorProps) {
  return (
    <div>
      <label htmlFor="job-selector" className="block text-sm font-medium text-gray-700 mb-1">
        Job Posting
      </label>
      <select
        id="job-selector"
        value={jobId ?? ""}
        onChange={(e) => onChange(e.target.value || null)}
        className="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
      >
        <option value="">All Jobs</option>
        {jobs.map((job) => (
          <option key={job.id} value={job.id}>
            {job.title}
          </option>
        ))}
      </select>
    </div>
  );
}
