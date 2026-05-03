"use client";

import type { Scorecard } from "@/types/scorecard";

interface ScorecardDetailViewProps {
  scorecard: Scorecard;
  canEdit: boolean;
  onEdit?: () => void;
}

const RECOMMENDATION_BADGE: Record<string, string> = {
  strong_no: "bg-red-100 text-red-700",
  no: "bg-orange-100 text-orange-700",
  mixed: "bg-yellow-100 text-yellow-700",
  yes: "bg-green-100 text-green-700",
  strong_yes: "bg-emerald-100 text-emerald-700",
};

const CATEGORY_BADGE: Record<string, string> = {
  technical: "bg-blue-100 text-blue-700",
  behavioral: "bg-purple-100 text-purple-700",
  cultural: "bg-teal-100 text-teal-700",
  experience: "bg-yellow-100 text-yellow-700",
};

function formatRecommendation(rec: string): string {
  return rec.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

function StarDisplay({ rating }: { rating: number }) {
  return (
    <div className="flex items-center gap-0.5" aria-label={`${rating} out of 5 stars`}>
      {[1, 2, 3, 4, 5].map((star) => (
        <span
          key={star}
          className={`inline-block h-4 w-4 text-center text-xs leading-4 rounded-full ${
            star <= rating
              ? "bg-yellow-400 text-yellow-900"
              : "bg-gray-200 text-gray-400"
          }`}
        >
          {star}
        </span>
      ))}
    </div>
  );
}

export function ScorecardDetailView({ scorecard, canEdit, onEdit }: ScorecardDetailViewProps) {
  const submittedDate = new Date(scorecard.submitted_at).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });

  // Group criteria by category
  const groupedCriteria = scorecard.criteria.reduce<Record<string, typeof scorecard.criteria>>(
    (acc, criterion) => {
      const cat = criterion.category;
      if (!acc[cat]) acc[cat] = [];
      acc[cat].push(criterion);
      return acc;
    },
    {}
  );

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm font-medium text-gray-900">{scorecard.submitter_name}</p>
          <p className="text-xs text-gray-500">{submittedDate}</p>
        </div>
        <div className="flex items-center gap-2">
          <StarDisplay rating={scorecard.overall_rating} />
          <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
              RECOMMENDATION_BADGE[scorecard.overall_recommendation] ?? "bg-gray-100 text-gray-700"
            }`}
          >
            {formatRecommendation(scorecard.overall_recommendation)}
          </span>
        </div>
      </div>

      {/* Overall notes */}
      {scorecard.notes && (
        <div>
          <p className="text-xs font-medium text-gray-500 mb-1">Notes</p>
          <p className="text-sm text-gray-700 whitespace-pre-wrap">{scorecard.notes}</p>
        </div>
      )}

      {/* Criteria by category */}
      {Object.entries(groupedCriteria).map(([category, criteria]) => (
        <div key={category}>
          <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium mb-2 ${
              CATEGORY_BADGE[category] ?? "bg-gray-100 text-gray-700"
            }`}
          >
            {category.charAt(0).toUpperCase() + category.slice(1)}
          </span>
          <div className="space-y-2">
            {criteria.map((c) => (
              <div key={c.id} className="bg-gray-50 rounded-md px-3 py-2">
                <div className="flex items-start justify-between gap-2">
                  <p className="text-sm text-gray-800">{c.question_text}</p>
                  <StarDisplay rating={c.rating} />
                </div>
                {c.notes && (
                  <p className="text-xs text-gray-500 mt-1">{c.notes}</p>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}

      {/* Edit button */}
      {canEdit && onEdit && (
        <button
          type="button"
          onClick={onEdit}
          className="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          Edit Scorecard
        </button>
      )}
    </div>
  );
}
