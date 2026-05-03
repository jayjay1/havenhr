"use client";

import { useState, useEffect, useCallback } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { apiClient, ApiRequestError } from "@/lib/api";
import { StatCard } from "@/components/dashboard/StatCard";
import { ActivityFeed } from "@/components/dashboard/ActivityFeed";
import { StageChart } from "@/components/dashboard/StageChart";
import { UpcomingInterviewsWidget } from "@/components/dashboard/UpcomingInterviewsWidget";

/** Dashboard metrics response shape */
interface DashboardMetrics {
  open_jobs_count: number;
  total_candidates: number;
  applications_this_week: number;
  pipeline_conversion_rate: number;
}

/** Audit log entry */
interface AuditLog {
  id: string;
  action: string;
  resource_type: string;
  resource_id: string | null;
  created_at: string;
  ip_address: string | null;
  user_agent: string | null;
  tenant_id: string;
  user_id: string | null;
}

/** Paginated audit logs response */
interface PaginatedAuditLogs {
  data: AuditLog[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/** Applications by stage entry */
interface StageCount {
  stage_name: string;
  count: number;
}

/**
 * Check whether an error is a 403 permission-denied response.
 */
function isPermissionDenied(err: unknown): boolean {
  return err instanceof ApiRequestError && err.status === 403;
}

/** SVG icon components for stat cards */
function BriefcaseIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
    </svg>
  );
}

function UsersIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
    </svg>
  );
}

function DocumentIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
    </svg>
  );
}

function ChartIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
    </svg>
  );
}

/**
 * Subtle info banner for permission-denied or access-restricted sections.
 */
function PermissionNotice({ message }: { message: string }) {
  return (
    <div className="flex items-center gap-2 rounded-md bg-gray-50 border border-gray-200 px-4 py-3">
      <svg className="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
        <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
      </svg>
      <p className="text-sm text-gray-500">{message}</p>
    </div>
  );
}

export default function DashboardPage() {
  const { user, hasPermission, isLoading: authLoading } = useAuth();

  const canViewAuditLogs = hasPermission("audit_logs.view");

  // Metrics state
  const [metrics, setMetrics] = useState<DashboardMetrics | null>(null);
  const [metricsLoading, setMetricsLoading] = useState(true);
  const [metricsError, setMetricsError] = useState("");
  const [metricsDenied, setMetricsDenied] = useState(false);

  // Activity feed state
  const [activityLogs, setActivityLogs] = useState<AuditLog[]>([]);
  const [activityLoading, setActivityLoading] = useState(true);
  const [activityError, setActivityError] = useState("");

  // Stage chart state
  const [stageData, setStageData] = useState<StageCount[]>([]);
  const [stageLoading, setStageLoading] = useState(true);
  const [stageError, setStageError] = useState("");
  const [stageDenied, setStageDenied] = useState(false);

  const fetchMetrics = useCallback(async () => {
    setMetricsLoading(true);
    setMetricsError("");
    setMetricsDenied(false);
    try {
      const response = await apiClient.get<DashboardMetrics>("/dashboard/metrics");
      setMetrics(response.data);
    } catch (err) {
      if (isPermissionDenied(err)) {
        setMetricsDenied(true);
      } else if (err instanceof ApiRequestError) {
        setMetricsError(err.message || "Failed to load metrics.");
      } else {
        setMetricsError("Failed to load metrics.");
      }
    } finally {
      setMetricsLoading(false);
    }
  }, []);

  const fetchActivity = useCallback(async () => {
    // Skip fetch entirely if user lacks audit_logs.view permission
    if (!canViewAuditLogs) {
      setActivityLoading(false);
      return;
    }
    setActivityLoading(true);
    setActivityError("");
    try {
      const response = await apiClient.get<PaginatedAuditLogs>("/audit-logs?per_page=10");
      const paginated = response.data;
      if (paginated && Array.isArray(paginated.data)) {
        setActivityLogs(paginated.data);
      } else if (Array.isArray(paginated)) {
        setActivityLogs(paginated as unknown as AuditLog[]);
      } else {
        setActivityLogs([]);
      }
    } catch (err) {
      // If we somehow still get a 403, handle it gracefully
      if (isPermissionDenied(err)) {
        setActivityError("");
      } else if (err instanceof ApiRequestError) {
        setActivityError(err.message || "Failed to load activity.");
      } else {
        setActivityError("Failed to load activity.");
      }
    } finally {
      setActivityLoading(false);
    }
  }, [canViewAuditLogs]);

  const fetchStageData = useCallback(async () => {
    setStageLoading(true);
    setStageError("");
    setStageDenied(false);
    try {
      const response = await apiClient.get<StageCount[]>("/dashboard/applications-by-stage");
      setStageData(response.data);
    } catch (err) {
      if (isPermissionDenied(err)) {
        setStageDenied(true);
      } else if (err instanceof ApiRequestError) {
        setStageError(err.message || "Failed to load chart data.");
      } else {
        setStageError("Failed to load chart data.");
      }
    } finally {
      setStageLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!authLoading) {
      fetchMetrics();
      fetchActivity();
      fetchStageData();
    }
  }, [authLoading, fetchMetrics, fetchActivity, fetchStageData]);

  return (
    <div>
      {/* Page header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
        {user && (
          <p className="mt-1 text-sm text-gray-500">
            Welcome back, {user.name}.
          </p>
        )}
      </div>

      {/* 2-column layout: left (stats + quick actions) / right (activity + chart) */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left column */}
        <div className="space-y-6">
          {/* Stat cards */}
          {metricsDenied ? (
            <PermissionNotice message="You don't have access to dashboard metrics." />
          ) : metricsError ? (
            <div role="alert" className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
              {metricsError}
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <StatCard
                label="Open Jobs"
                value={metrics?.open_jobs_count ?? 0}
                icon={<BriefcaseIcon />}
                loading={metricsLoading}
              />
              <StatCard
                label="Total Candidates"
                value={metrics?.total_candidates ?? 0}
                icon={<UsersIcon />}
                loading={metricsLoading}
              />
              <StatCard
                label="Applications This Week"
                value={metrics?.applications_this_week ?? 0}
                icon={<DocumentIcon />}
                loading={metricsLoading}
              />
              <StatCard
                label="Pipeline Conversion Rate"
                value={metrics ? `${metrics.pipeline_conversion_rate}%` : "0%"}
                icon={<ChartIcon />}
                loading={metricsLoading}
              />
            </div>
          )}

          {/* Quick Actions */}
          <div className="rounded-lg border border-gray-200 bg-white p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
            <div className="flex flex-wrap gap-3">
              {hasPermission("jobs.create") && (
                <a
                  href="/dashboard/jobs/new"
                  className="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                >
                  <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                  </svg>
                  Create Job
                </a>
              )}
              <a
                href="/dashboard/jobs"
                className="inline-flex items-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 transition-colors"
              >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
                View Pipeline
              </a>
              {hasPermission("users.create") && (
                <a
                  href="/dashboard/users?action=invite"
                  className="inline-flex items-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 transition-colors"
                >
                  <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                  </svg>
                  Invite User
                </a>
              )}
              {hasPermission("reports.view") && (
                <a
                  href="/dashboard/reports"
                  className="inline-flex items-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 transition-colors"
                >
                  <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                  </svg>
                  View Reports
                </a>
              )}
            </div>
          </div>
        </div>

        {/* Right column */}
        <div className="space-y-6">
          {/* Activity Feed — only shown for users with audit_logs.view permission */}
          {canViewAuditLogs ? (
            activityError ? (
              <div className="rounded-lg border border-gray-200 bg-white">
                <div className="border-b border-gray-200 px-6 py-4">
                  <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
                </div>
                <div className="px-6 py-8 text-center">
                  <p className="text-sm text-red-600">{activityError}</p>
                </div>
              </div>
            ) : (
              <ActivityFeed logs={activityLogs} loading={activityLoading} />
            )
          ) : (
            <div className="rounded-lg border border-gray-200 bg-white">
              <div className="border-b border-gray-200 px-6 py-4">
                <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
              </div>
              <div className="px-6 py-8 text-center">
                <svg className="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                <p className="text-sm text-gray-500">Activity feed requires audit log access.</p>
              </div>
            </div>
          )}

          {/* Stage Chart */}
          {stageDenied ? (
            <div className="rounded-lg border border-gray-200 bg-white">
              <div className="border-b border-gray-200 px-6 py-4">
                <h2 className="text-lg font-semibold text-gray-900">Applications by Stage</h2>
              </div>
              <div className="px-6 py-8 text-center">
                <PermissionNotice message="You don't have access to this section." />
              </div>
            </div>
          ) : stageError ? (
            <div className="rounded-lg border border-gray-200 bg-white">
              <div className="border-b border-gray-200 px-6 py-4">
                <h2 className="text-lg font-semibold text-gray-900">Applications by Stage</h2>
              </div>
              <div className="px-6 py-8 text-center">
                <p className="text-sm text-red-600">{stageError}</p>
              </div>
            </div>
          ) : (
            <StageChart data={stageData} loading={stageLoading} />
          )}

          {/* Upcoming Interviews */}
          <UpcomingInterviewsWidget />
        </div>
      </div>
    </div>
  );
}
