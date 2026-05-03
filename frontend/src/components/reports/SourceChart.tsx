"use client";

export interface SourceData {
  source: string;
  count: number;
}

export interface SourceChartProps {
  data: SourceData[];
  loading: boolean;
}

/**
 * Horizontal bar chart showing application count per source.
 * Follows the StageChart pattern.
 */
export function SourceChart({ data, loading }: SourceChartProps) {
  if (loading) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white">
        <div className="border-b border-gray-200 px-6 py-4">
          <h2 className="text-lg font-semibold text-gray-900">Application Sources</h2>
        </div>
        <div className="space-y-4 px-6 py-4">
          {Array.from({ length: 2 }).map((_, i) => (
            <div key={i} className="space-y-1">
              <div className="h-3 w-20 animate-pulse rounded bg-gray-200" />
              <div className="h-5 animate-pulse rounded bg-gray-200" style={{ width: `${80 - i * 30}%` }} />
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
        <h2 className="text-lg font-semibold text-gray-900">Application Sources</h2>
      </div>

      {data.length === 0 ? (
        <div className="px-6 py-8 text-center">
          <p className="text-sm text-gray-500">No source data available for this period.</p>
        </div>
      ) : (
        <div className="space-y-3 px-6 py-4">
          {data.map((item) => {
            const widthPercent = (item.count / maxCount) * 100;
            return (
              <div key={item.source}>
                <div className="flex items-center justify-between mb-1">
                  <span className="text-sm font-medium text-gray-700 capitalize">{item.source}</span>
                  <span className="text-sm text-gray-500">{item.count}</span>
                </div>
                <div className="h-4 w-full rounded-full bg-gray-100">
                  <div
                    className="h-4 rounded-full bg-amber-500 transition-all duration-300"
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
