"use client";

export interface FunnelData {
  stage_name: string;
  count: number;
  conversion_rate: number | null;
}

export interface FunnelChartProps {
  data: FunnelData[];
  loading: boolean;
}

/**
 * Visual funnel with progressively narrower horizontal bars.
 * Each bar shows stage name, candidate count, and conversion rate.
 * Bar widths are proportional to the first stage count.
 */
export function FunnelChart({ data, loading }: FunnelChartProps) {
  if (loading) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white">
        <div className="border-b border-gray-200 px-6 py-4">
          <h2 className="text-lg font-semibold text-gray-900">Pipeline Funnel</h2>
        </div>
        <div className="space-y-3 px-6 py-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="space-y-1">
              <div className="h-3 w-20 animate-pulse rounded bg-gray-200" />
              <div className="h-8 animate-pulse rounded bg-gray-200" style={{ width: `${100 - i * 20}%` }} />
            </div>
          ))}
        </div>
      </div>
    );
  }

  const maxCount = data.length > 0 ? Math.max(data[0].count, 1) : 1;

  return (
    <div className="rounded-lg border border-gray-200 bg-white">
      <div className="border-b border-gray-200 px-6 py-4">
        <h2 className="text-lg font-semibold text-gray-900">Pipeline Funnel</h2>
      </div>

      {data.length === 0 ? (
        <div className="px-6 py-8 text-center">
          <p className="text-sm text-gray-500">No funnel data available for this period.</p>
        </div>
      ) : (
        <div className="space-y-2 px-6 py-4">
          {data.map((stage) => {
            const widthPercent = (stage.count / maxCount) * 100;
            return (
              <div key={stage.stage_name}>
                <div className="flex items-center justify-between mb-1">
                  <span className="text-sm font-medium text-gray-700">{stage.stage_name}</span>
                  <span className="text-sm text-gray-500">
                    {stage.count} candidates
                    {stage.conversion_rate !== null && (
                      <span className="ml-2 text-xs text-gray-400">({stage.conversion_rate}%)</span>
                    )}
                  </span>
                </div>
                <div className="flex justify-center">
                  <div
                    className="h-8 rounded bg-purple-500 transition-all duration-300 flex items-center justify-center"
                    style={{ width: `${Math.max(widthPercent, 2)}%` }}
                  >
                    {widthPercent > 15 && (
                      <span className="text-xs font-medium text-white">{stage.count}</span>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
