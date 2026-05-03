<?php

namespace App\Services;

use App\Models\Interview;
use App\Models\InterviewKit;
use App\Models\JobApplication;
use App\Models\Scorecard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class ScorecardService
{
    /**
     * Get the scorecard form structure for an interview.
     * Returns criteria from the interview kit linked to the interview's pipeline stage.
     */
    public function getScorecardForm(string $interviewId, string $tenantId): array
    {
        $interview = $this->findInterviewForTenant($interviewId, $tenantId);

        if (!$interview) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Interview not found.',
                    ],
                ], 404)
            );
        }

        // Find the kit for the interview's application's current pipeline stage
        $stageId = $interview->jobApplication->pipeline_stage_id;
        $kit = null;
        $criteria = [];

        if ($stageId) {
            $kit = InterviewKit::where('pipeline_stage_id', $stageId)
                ->with('questions')
                ->first();

            if ($kit) {
                $criteria = $kit->questions->map(function ($q) {
                    return [
                        'question_text' => $q->text,
                        'category' => $q->category,
                        'sort_order' => $q->sort_order,
                        'scoring_rubric' => $q->scoring_rubric,
                    ];
                })->all();
            }
        }

        return [
            'interview_id' => $interview->id,
            'interview_status' => $interview->status,
            'has_kit' => $kit !== null,
            'criteria' => $criteria,
        ];
    }

    /**
     * Submit a new scorecard for an interview.
     * Validates: interview is completed, no duplicate scorecard for this interviewer.
     */
    public function submit(array $data, string $interviewId, string $userId, string $tenantId): Scorecard
    {
        $interview = $this->findInterviewForTenant($interviewId, $tenantId);

        if (!$interview) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Interview not found.',
                    ],
                ], 404)
            );
        }

        // Validate interview is completed
        if ($interview->status !== 'completed') {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Interview must be completed before submitting a scorecard.',
                    ],
                ], 422)
            );
        }

        // Check for duplicate scorecard
        $existing = Scorecard::where('interview_id', $interviewId)
            ->where('submitted_by', $userId)
            ->exists();

        if ($existing) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'A scorecard already exists for this interviewer and interview.',
                    ],
                ], 422)
            );
        }

        return DB::transaction(function () use ($data, $interviewId, $userId) {
            $scorecard = Scorecard::create([
                'interview_id' => $interviewId,
                'submitted_by' => $userId,
                'overall_rating' => $data['overall_rating'],
                'overall_recommendation' => $data['overall_recommendation'],
                'notes' => $data['notes'] ?? null,
                'submitted_at' => now(),
            ]);

            if (!empty($data['criteria'])) {
                foreach ($data['criteria'] as $criterion) {
                    $scorecard->criteria()->create([
                        'question_text' => $criterion['question_text'],
                        'category' => $criterion['category'],
                        'sort_order' => $criterion['sort_order'],
                        'rating' => $criterion['rating'],
                        'notes' => $criterion['notes'] ?? null,
                    ]);
                }
            }

            $scorecard->load(['criteria', 'submitter:id,name']);

            return $scorecard;
        });
    }

    /**
     * Update an existing scorecard.
     */
    public function update(Scorecard $scorecard, array $data): Scorecard
    {
        return DB::transaction(function () use ($scorecard, $data) {
            $updateData = [];

            if (isset($data['overall_rating'])) {
                $updateData['overall_rating'] = $data['overall_rating'];
            }
            if (isset($data['overall_recommendation'])) {
                $updateData['overall_recommendation'] = $data['overall_recommendation'];
            }
            if (array_key_exists('notes', $data)) {
                $updateData['notes'] = $data['notes'];
            }

            if (!empty($updateData)) {
                $scorecard->update($updateData);
            }

            if (isset($data['criteria'])) {
                $scorecard->criteria()->delete();

                foreach ($data['criteria'] as $criterion) {
                    $scorecard->criteria()->create([
                        'question_text' => $criterion['question_text'],
                        'category' => $criterion['category'],
                        'sort_order' => $criterion['sort_order'],
                        'rating' => $criterion['rating'],
                        'notes' => $criterion['notes'] ?? null,
                    ]);
                }
            }

            $scorecard->load(['criteria', 'submitter:id,name']);

            return $scorecard;
        });
    }

    /**
     * Get a single scorecard with all criteria.
     * Validates tenant ownership.
     */
    public function getDetail(string $scorecardId, string $tenantId): ?Scorecard
    {
        return Scorecard::whereHas('interview', function ($q) use ($tenantId) {
            $q->whereHas('jobApplication', function ($q2) use ($tenantId) {
                $q2->whereHas('jobPosting', function ($q3) use ($tenantId) {
                    $q3->where('tenant_id', $tenantId);
                });
            });
        })
            ->with(['criteria', 'submitter:id,name'])
            ->find($scorecardId);
    }

    /**
     * Get the aggregated scorecard summary for a job application.
     */
    public function getSummary(string $applicationId, string $tenantId): array
    {
        // Verify application belongs to tenant
        $application = JobApplication::whereHas('jobPosting', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->find($applicationId);

        if (!$application) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Application not found.',
                    ],
                ], 404)
            );
        }

        // Get all scorecards for all interviews of this application
        $scorecards = Scorecard::whereHas('interview', function ($q) use ($applicationId) {
            $q->where('job_application_id', $applicationId);
        })
            ->with(['criteria', 'submitter:id,name', 'interview:id,job_application_id'])
            ->get();

        $total = $scorecards->count();

        if ($total === 0) {
            return [
                'application_id' => $applicationId,
                'total_scorecards' => 0,
                'average_overall_rating' => null,
                'recommendation_distribution' => [
                    'strong_no' => 0,
                    'no' => 0,
                    'mixed' => 0,
                    'yes' => 0,
                    'strong_yes' => 0,
                ],
                'criteria_averages' => [],
                'interviewers' => [],
            ];
        }

        $avgRating = round($scorecards->avg('overall_rating'), 2);

        $recommendations = [
            'strong_no' => 0,
            'no' => 0,
            'mixed' => 0,
            'yes' => 0,
            'strong_yes' => 0,
        ];

        foreach ($scorecards as $sc) {
            if (isset($recommendations[$sc->overall_recommendation])) {
                $recommendations[$sc->overall_recommendation]++;
            }
        }

        // Per-criterion averages
        $allCriteria = $scorecards->flatMap(function ($sc) {
            return $sc->criteria;
        });

        $criteriaAverages = $allCriteria->groupBy('question_text')->map(function ($group, $questionText) {
            return [
                'question_text' => $questionText,
                'category' => $group->first()->category,
                'average_rating' => round($group->avg('rating'), 2),
                'rating_count' => $group->count(),
            ];
        })->values()->all();

        // Individual interviewer entries
        $interviewers = $scorecards->map(function ($sc) {
            return [
                'interviewer_id' => $sc->submitted_by,
                'interviewer_name' => $sc->submitter->name ?? null,
                'interview_id' => $sc->interview_id,
                'overall_rating' => $sc->overall_rating,
                'overall_recommendation' => $sc->overall_recommendation,
                'submitted_at' => $sc->submitted_at->toIso8601String(),
            ];
        })->all();

        return [
            'application_id' => $applicationId,
            'total_scorecards' => $total,
            'average_overall_rating' => $avgRating,
            'recommendation_distribution' => $recommendations,
            'criteria_averages' => $criteriaAverages,
            'interviewers' => $interviewers,
        ];
    }

    /**
     * List all scorecards for a specific interview.
     */
    public function listForInterview(string $interviewId, string $tenantId): Collection
    {
        $interview = $this->findInterviewForTenant($interviewId, $tenantId);

        if (!$interview) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Interview not found.',
                    ],
                ], 404)
            );
        }

        return Scorecard::where('interview_id', $interviewId)
            ->with(['criteria', 'submitter:id,name'])
            ->get();
    }

    /**
     * Find an interview scoped to a tenant.
     */
    protected function findInterviewForTenant(string $interviewId, string $tenantId): ?Interview
    {
        return Interview::whereHas('jobApplication', function ($q) use ($tenantId) {
            $q->whereHas('jobPosting', function ($q2) use ($tenantId) {
                $q2->where('tenant_id', $tenantId);
            });
        })
            ->with('jobApplication:id,job_posting_id,pipeline_stage_id')
            ->find($interviewId);
    }
}
