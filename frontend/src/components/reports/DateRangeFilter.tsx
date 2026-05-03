"use client";

export interface DateRangeFilterProps {
  startDate: string;
  endDate: string;
  onChange: (startDate: string, endDate: string) => void;
}

/**
 * Date range filter with two date inputs.
 * Defaults to last 30 days. Prevents start > end on the client side.
 */
export function DateRangeFilter({ startDate, endDate, onChange }: DateRangeFilterProps) {
  return (
    <div className="flex flex-wrap items-end gap-4">
      <div>
        <label htmlFor="start-date" className="block text-sm font-medium text-gray-700 mb-1">
          Start Date
        </label>
        <input
          type="date"
          id="start-date"
          value={startDate}
          max={endDate}
          onChange={(e) => {
            const newStart = e.target.value;
            if (newStart <= endDate) {
              onChange(newStart, endDate);
            }
          }}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
      <div>
        <label htmlFor="end-date" className="block text-sm font-medium text-gray-700 mb-1">
          End Date
        </label>
        <input
          type="date"
          id="end-date"
          value={endDate}
          min={startDate}
          onChange={(e) => {
            const newEnd = e.target.value;
            if (newEnd >= startDate) {
              onChange(startDate, newEnd);
            }
          }}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
    </div>
  );
}
