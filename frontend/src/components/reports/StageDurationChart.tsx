"use client";

export interface StageDurationData {
  stage_name: string;
  avg_days: number;
}

export interface StageDurationChartProps {
  data: StageDurationData[];
  loading: boolean;
}

/**
 * Horizontal bar chart showing average days per pipeline stage.
 */
export function StageDurationChart({ data, loading }: StageDurationChartProps) {
  if (loading) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white">
        <div className="border-b border-gray-200 px-6 py-4">
          <h2 className="text-lg font-semibold text-gray-900">Average Stage Duration</h2>
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

  const maxDays = Math.max(...data.map((d) => d.avg_days), 1);

  return (
    <div className="rounded-lg border border-gray-200 bg-white">
      <div className="border-b border-gray-200 px-6 py-4">
        <h2 className="text-lg font-semibold text-gray-900">Average Stage Duration</h2>
      </div>

      {data.length === 0 ? (
        <div className="px-6 py-8 text-center">
          <p className="text-sm text-gray-500">No stage duration data available for this period.</p>
        </div>
      ) : (
        <div className="space-y-3 px-6 py-4">
          {data.map((item) => {
            const widthPercent = (item.avg_days / maxDays) * 100;
            return (
              <div key={item.stage_name}>
                <div className="flex items-center justify-between mb-1">
                  <span className="text-sm font-medium text-gray-700">{item.stage_name}</span>
                  <span className="text-sm text-gray-500">{item.avg_days} days</span>
                </div>
                <div className="h-4 w-full rounded-full bg-gray-100">
                  <div
                    className="h-4 rounded-full bg-indigo-500 transition-all duration-300"
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
