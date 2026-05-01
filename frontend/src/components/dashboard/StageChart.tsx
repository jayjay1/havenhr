"use client";

export interface StageChartProps {
  /** Array of stage names and their application counts */
  data: { stage_name: string; count: number }[];
  /** Show skeleton placeholder when true */
  loading: boolean;
}

/**
 * Horizontal bar chart showing application counts by pipeline stage.
 * Uses pure CSS/Tailwind — no chart library dependency.
 * Each bar is proportional to the maximum count.
 */
export function StageChart({ data, loading }: StageChartProps) {
  if (loading) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white">
        <div className="border-b border-gray-200 px-6 py-4">
          <h2 className="text-lg font-semibold text-gray-900">Applications by Stage</h2>
        </div>
        <div className="space-y-4 px-6 py-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="space-y-1">
              <div className="h-3 w-24 animate-pulse rounded bg-gray-200" />
              <div className="h-5 animate-pulse rounded bg-gray-200" style={{ width: `${80 - i * 15}%` }} />
            </div>
          ))}
        </div>
      </div>
    );
  }

  const maxCount = Math.max(...data.map((d) => d.count), 1);

  return (
    <div className="rounded-lg border border-gray-200 bg-white">
      <div className="border-b border-gray-200 px-6 py-4">
        <h2 className="text-lg font-semibold text-gray-900">Applications by Stage</h2>
      </div>

      {data.length === 0 ? (
        <div className="px-6 py-8 text-center">
          <p className="text-sm text-gray-500">No application data yet.</p>
        </div>
      ) : (
        <div className="space-y-3 px-6 py-4">
          {data.map((stage) => {
            const widthPercent = (stage.count / maxCount) * 100;
            return (
              <div key={stage.stage_name}>
                <div className="flex items-center justify-between mb-1">
                  <span className="text-sm font-medium text-gray-700">{stage.stage_name}</span>
                  <span className="text-sm text-gray-500">{stage.count}</span>
                </div>
                <div className="h-4 w-full rounded-full bg-gray-100">
                  <div
                    className="h-4 rounded-full bg-blue-500 transition-all duration-300"
                    style={{ width: `${widthPercent}%` }}
                  />
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
