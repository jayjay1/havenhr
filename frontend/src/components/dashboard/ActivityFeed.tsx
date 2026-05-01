"use client";

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

export interface ActivityFeedProps {
  /** Audit log entries to display */
  logs: AuditLog[];
  /** Show skeleton placeholder when true */
  loading: boolean;
}

/**
 * Color-coded badge for audit log action types.
 * Reuses the same color mapping as the audit-logs page.
 */
function ActionBadge({ action }: { action: string }) {
  const colors: Record<string, string> = {
    "user.login": "bg-green-100 text-green-800",
    "user.logout": "bg-gray-100 text-gray-800",
    "user.login_failed": "bg-red-100 text-red-800",
    "user.registered": "bg-blue-100 text-blue-800",
    "user.password_reset": "bg-yellow-100 text-yellow-800",
    "role.assigned": "bg-purple-100 text-purple-800",
    "role.changed": "bg-purple-100 text-purple-800",
    "tenant.created": "bg-indigo-100 text-indigo-800",
    "job.created": "bg-emerald-100 text-emerald-800",
    "job.updated": "bg-emerald-100 text-emerald-800",
    "job.deleted": "bg-red-100 text-red-800",
    "job.status_changed": "bg-amber-100 text-amber-800",
    "application.created": "bg-cyan-100 text-cyan-800",
    "application.stage_changed": "bg-teal-100 text-teal-800",
  };

  return (
    <span
      className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
        colors[action] || "bg-gray-100 text-gray-800"
      }`}
    >
      {action}
    </span>
  );
}

/**
 * Build a human-readable description from an audit log entry.
 */
function describeAction(log: AuditLog): string {
  const descriptions: Record<string, string> = {
    "user.login": "User logged in",
    "user.logout": "User logged out",
    "user.login_failed": "Failed login attempt",
    "user.registered": "New user registered",
    "user.password_reset": "Password was reset",
    "role.assigned": "Role was assigned",
    "role.changed": "Role was changed",
    "tenant.created": "Organization was created",
    "job.created": "Job posting was created",
    "job.updated": "Job posting was updated",
    "job.deleted": "Job posting was deleted",
    "job.status_changed": "Job status was changed",
    "application.created": "New application submitted",
    "application.stage_changed": "Application moved to new stage",
  };

  return descriptions[log.action] || `${log.action} on ${log.resource_type}`;
}

/**
 * Convert a timestamp to a relative time string (e.g., "2 hours ago").
 */
export function relativeTime(dateStr: string): string {
  const now = Date.now();
  const then = new Date(dateStr).getTime();
  const diffMs = now - then;

  if (diffMs < 0) return "just now";

  const seconds = Math.floor(diffMs / 1000);
  if (seconds < 60) return "just now";

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes} minute${minutes === 1 ? "" : "s"} ago`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} hour${hours === 1 ? "" : "s"} ago`;

  const days = Math.floor(hours / 24);
  if (days < 30) return `${days} day${days === 1 ? "" : "s"} ago`;

  const months = Math.floor(days / 30);
  if (months < 12) return `${months} month${months === 1 ? "" : "s"} ago`;

  const years = Math.floor(months / 12);
  return `${years} year${years === 1 ? "" : "s"} ago`;
}

/**
 * Recent activity feed showing the latest audit log entries.
 * Displays action badges, descriptions, and relative timestamps.
 */
export function ActivityFeed({ logs, loading }: ActivityFeedProps) {
  if (loading) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white">
        <div className="border-b border-gray-200 px-6 py-4">
          <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
        </div>
        <div className="divide-y divide-gray-100">
          {Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="flex items-center gap-3 px-6 py-3">
              <div className="h-5 w-20 animate-pulse rounded-full bg-gray-200" />
              <div className="flex-1 space-y-1">
                <div className="h-3 w-40 animate-pulse rounded bg-gray-200" />
                <div className="h-3 w-20 animate-pulse rounded bg-gray-200" />
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-gray-200 bg-white">
      <div className="border-b border-gray-200 px-6 py-4">
        <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
      </div>

      {logs.length === 0 ? (
        <div className="px-6 py-8 text-center">
          <p className="text-sm text-gray-500">No recent activity.</p>
        </div>
      ) : (
        <div className="divide-y divide-gray-100">
          {logs.map((log) => (
            <div key={log.id} className="flex items-start gap-3 px-6 py-3">
              <ActionBadge action={log.action} />
              <div className="flex-1 min-w-0">
                <p className="text-sm text-gray-700">{describeAction(log)}</p>
                <p className="text-xs text-gray-400">{relativeTime(log.created_at)}</p>
              </div>
            </div>
          ))}
        </div>
      )}

      <div className="border-t border-gray-200 px-6 py-3">
        <a
          href="/dashboard/audit-logs"
          className="text-sm font-medium text-blue-600 hover:text-blue-700"
        >
          View All →
        </a>
      </div>
    </div>
  );
}
