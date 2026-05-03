"use client";

export interface TrendData {
  month: string;
  avg_days: number;
}

export interface TrendChartProps {
  data: TrendData[];
  loading: boolean;
}

/**
 * Vertical bar chart showing monthly average time-to-hire for the last 6 months.
 * Bars grow upward from a baseline using CSS flex-end alignment.
 */
export function TrendChart({ data, loading }: TrendChartProps) {
  if (loading) {
    return (
      <div className="rounded-lg border border-gray-200 bg-white">
        <div className="border-b border-gray-200 px-6 py-4">
          <h2 className="text-lg font-semibold text-gray-900">Monthly Hiring Trend</h2>
        </div>
        <div className="flex items-end justify-around gap-2 px-6 py-4 h-48">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="flex flex-col items-center gap-1 flex-1">
              <div className="w-full animate-pulse rounded bg-gray-200" style={{ height: `${30 + i * 15}%` }} />
              <div className="h-3 w-10 animate-pulse rounded bg-gray-200" />
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
        <h2 className="text-lg font-semibold text-gray-900">Monthly Hiring Trend</h2>
      </div>

      {data.length === 0 || data.every((d) => d.avg_days === 0) ? (
        <div className="px-6 py-8 text-center">
          <p className="text-sm text-gray-500">No trend data available for this period.</p>
        </div>
      ) : (
        <div className="px-6 py-4">
          <div className="flex items-end justify-around gap-2 h-48">
            {data.map((item) => {
              const heightPercent = maxDays > 0 ? (item.avg_days / maxDays) * 100 : 0;
              const monthLabel = item.month.slice(5); // "01", "02", etc.
              return (
                <div key={item.month} className="flex flex-col items-center gap-1 flex-1">
                  <span className="text-xs text-gray-500">{item.avg_days > 0 ? `${item.avg_days}d` : ""}</span>
                  <div className="w-full flex flex-col justify-end flex-1">
                    <div
                      className="w-full rounded-t bg-emerald-500 transition-all duration-300"
                      style={{ height: `${heightPercent}%`, minHeight: item.avg_days > 0 ? "4px" : "0px" }}
                    />
                  </div>
                  <span className="text-xs text-gray-500">{monthLabel}</span>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
