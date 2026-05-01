"use client";

import type { ReactNode } from "react";

export interface StatCardProps {
  /** Metric label displayed above the value */
  label: string;
  /** Metric value (number or formatted string) */
  value: string | number;
  /** Icon rendered on the left side of the card */
  icon: ReactNode;
  /** Show skeleton placeholder when true */
  loading?: boolean;
}

/**
 * A stat card that displays a single metric with an icon, label, and value.
 * Shows a pulsing skeleton placeholder while loading.
 */
export function StatCard({ label, value, icon, loading = false }: StatCardProps) {
  if (loading) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white p-6">
        <div className="flex items-center gap-4">
          <div className="h-10 w-10 animate-pulse rounded-lg bg-gray-200" />
          <div className="flex-1 space-y-2">
            <div className="h-3 w-20 animate-pulse rounded bg-gray-200" />
            <div className="h-6 w-16 animate-pulse rounded bg-gray-200" />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-6">
      <div className="flex items-center gap-4">
        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
          {icon}
        </div>
        <div>
          <p className="text-sm font-medium text-gray-500">{label}</p>
          <p className="text-2xl font-semibold text-gray-900">{value}</p>
        </div>
      </div>
    </div>
  );
}
