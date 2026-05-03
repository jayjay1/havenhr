"use client";

import { useState, useEffect, useCallback } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { apiClient, ApiRequestError } from "@/lib/api";
import { StatCard } from "@/components/dashboard/StatCard";
import { DateRangeFilter } from "@/components/reports/DateRangeFilter";
import { JobSelector, type JobOption } from "@/components/reports/JobSelector";
import { TimeToHireChart, type TimeToHireData } from "@/components/reports/TimeToHireChart";
import { StageDurationChart, type StageDurationData } from "@/components/reports/StageDurationChart";
import { TrendChart, type TrendData } from "@/components/reports/TrendChart";
import { FunnelChart, type FunnelData } from "@/components/reports/FunnelChart";
import { SourceChart, type SourceData } from "@/components/reports/SourceChart";
import { ExportButton } from "@/components/reports/ExportButton";

/** Overview metrics response */
interface OverviewData {
  avg_time_to_hire: number;
  total_hires: number;
  offer_acceptance_rate: number;
}

/** Time-to-hire response */
interface TimeToHireResponse {
  by_job: TimeToHireData[];
  by_department: { department: string; avg_days: number; hire_count: number }[];
  by_stage: StageDurationData[];
  trend: TrendData[];
}

/** Funnel response */
interface FunnelResponse {
  stages: FunnelData[];
  job_id: string | null;
}

/** Job listing response */
interface JobListItem {
  id: string;
  title: string;
}

function formatDate(date: Date): string {
  return date.toISOString().split("T")[0];
}

function ClockIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  );
}

function CheckCircleIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  );
}

function PercentIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
    </svg>
  );
}

export default function ReportsPage() {
  const { isLoading: authLoading } = useAuth();

  // Date range state
  const now = new Date();
  const thirtyDaysAgo = new Date(now);
  thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

  const [startDate, setStartDate] = useState(formatDate(thirtyDaysAgo));
  const [endDate, setEndDate] = useState(formatDate(now));

  // Job selector state
  const [jobId, setJobId] = useState<string | null>(null);
  const [jobs, setJobs] = useState<JobOption[]>([]);

  // Overview state
  const [overview, setOverview] = useState<OverviewData | null>(null);
  const [overviewLoading, setOverviewLoading] = useState(true);
  const [overviewError, setOverviewError] = useState("");

  // Time-to-hire state
  const [timeToHire, setTimeToHire] = useState<TimeToHireResponse | null>(null);
  const [tthLoading, setTthLoading] = useState(true);
  const [tthError, setTthError] = useState("");

  // Funnel state
  const [funnel, setFunnel] = useState<FunnelResponse | null>(null);
  const [funnelLoading, setFunnelLoading] = useState(true);
  const [funnelError, setFunnelError] = useState("");

  // Sources state
  const [sources, setSources] = useState<SourceData[]>([]);
  const [sourcesLoading, setSourcesLoading] = useState(true);
  const [sourcesError, setSourcesError] = useState("");

  const dateParams = `start_date=${startDate}&end_date=${endDate}`;

  const fetchOverview = useCallback(async () => {
    setOverviewLoading(true);
    setOverviewError("");
    try {
      const res = await apiClient.get<OverviewData>(`/reports/overview?${dateParams}`);
      setOverview(res.data);
    } catch (err) {
      setOverviewError(err instanceof ApiRequestError ? err.message : "Failed to load overview.");
    } finally {
      setOverviewLoading(false);
    }
  }, [dateParams]);

  const fetchTimeToHire = useCallback(async () => {
    setTthLoading(true);
    setTthError("");
    try {
      const res = await apiClient.get<TimeToHireResponse>(`/reports/time-to-hire?${dateParams}`);
      setTimeToHire(res.data);
    } catch (err) {
      setTthError(err instanceof ApiRequestError ? err.message : "Failed to load time-to-hire data.");
    } finally {
      setTthLoading(false);
    }
  }, [dateParams]);

  const fetchFunnel = useCallback(async () => {
    setFunnelLoading(true);
    setFunnelError("");
    try {
      const jobParam = jobId ? `&job_id=${jobId}` : "";
      const res = await apiClient.get<FunnelResponse>(`/reports/funnel?${dateParams}${jobParam}`);
      setFunnel(res.data);
    } catch (err) {
      setFunnelError(err instanceof ApiRequestError ? err.message : "Failed to load funnel data.");
    } finally {
      setFunnelLoading(false);
    }
  }, [dateParams, jobId]);

  const fetchSources = useCallback(async () => {
    setSourcesLoading(true);
    setSourcesError("");
    try {
      const res = await apiClient.get<SourceData[]>(`/reports/sources?${dateParams}`);
      setSources(res.data);
    } catch (err) {
      setSourcesError(err instanceof ApiRequestError ? err.message : "Failed to load source data.");
    } finally {
      setSourcesLoading(false);
    }
  }, [dateParams]);

  const fetchJobs = useCallback(async () => {
    try {
      const res = await apiClient.get<{ data: JobListItem[] }>("/jobs");
      const jobsData = res.data;
      const jobsList = Array.isArray(jobsData) ? jobsData : (jobsData as { data: JobListItem[] }).data ?? [];
      setJobs(jobsList.map((j) => ({ id: j.id, title: j.title })));
    } catch {
      // Non-critical — job selector will just show "All Jobs"
    }
  }, []);

  // Fetch all data on mount and when date range changes
  useEffect(() => {
    if (!authLoading) {
      fetchOverview();
      fetchTimeToHire();
      fetchSources();
      fetchJobs();
    }
  }, [authLoading, fetchOverview, fetchTimeToHire, fetchSources, fetchJobs]);

  // Fetch funnel separately since it depends on jobId too
  useEffect(() => {
    if (!authLoading) {
      fetchFunnel();
    }
  }, [authLoading, fetchFunnel]);

  function handleDateChange(newStart: string, newEnd: string) {
    setStartDate(newStart);
    setEndDate(newEnd);
  }

  function handleJobChange(newJobId: string | null) {
    setJobId(newJobId);
  }

  return (
    <div>
      {/* Page header */}
      <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Reports & Analytics</h1>
          <p className="mt-1 text-sm text-gray-500">Track hiring performance and pipeline metrics.</p>
        </div>
        <DateRangeFilter startDate={startDate} endDate={endDate} onChange={handleDateChange} />
      </div>

      {/* Overview Section */}
      <section className="mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900">Overview</h2>
          <ExportButton reportType="overview" startDate={startDate} endDate={endDate} />
        </div>
        {overviewError && (
          <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700 mb-4">
            {overviewError}
          </div>
        )}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <StatCard
            label="Avg Time to Hire"
            value={overview ? `${overview.avg_time_to_hire} days` : "0 days"}
            icon={<ClockIcon />}
            loading={overviewLoading}
          />
          <StatCard
            label="Total Hires"
            value={overview?.total_hires ?? 0}
            icon={<CheckCircleIcon />}
            loading={overviewLoading}
          />
          <StatCard
            label="Offer Acceptance Rate"
            value={overview ? `${overview.offer_acceptance_rate}%` : "0%"}
            icon={<PercentIcon />}
            loading={overviewLoading}
          />
        </div>
      </section>

      {/* Time-to-Hire Section */}
      <section className="mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900">Time to Hire</h2>
          <ExportButton reportType="time-to-hire" startDate={startDate} endDate={endDate} />
        </div>
        {tthError && (
          <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700 mb-4">
            {tthError}
          </div>
        )}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <TimeToHireChart data={timeToHire?.by_job ?? []} loading={tthLoading} />
          <StageDurationChart data={timeToHire?.by_stage ?? []} loading={tthLoading} />
        </div>
        <div className="mt-6">
          <TrendChart data={timeToHire?.trend ?? []} loading={tthLoading} />
        </div>
      </section>

      {/* Funnel Section */}
      <section className="mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900">Pipeline Funnel</h2>
          <div className="flex items-center gap-4">
            <JobSelector jobId={jobId} jobs={jobs} onChange={handleJobChange} />
            <ExportButton reportType="funnel" startDate={startDate} endDate={endDate} jobId={jobId} />
          </div>
        </div>
        {funnelError && (
          <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700 mb-4">
            {funnelError}
          </div>
        )}
        <FunnelChart data={funnel?.stages ?? []} loading={funnelLoading} />
      </section>

      {/* Sources Section */}
      <section className="mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900">Application Sources</h2>
          <ExportButton reportType="sources" startDate={startDate} endDate={endDate} />
        </div>
        {sourcesError && (
          <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700 mb-4">
            {sourcesError}
          </div>
        )}
        <SourceChart data={sources} loading={sourcesLoading} />
      </section>
    </div>
  );
}
