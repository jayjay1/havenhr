<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitScorecardRequest;
use App\Http\Requests\UpdateScorecardRequest;
use App\Services\ScorecardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScorecardController extends Controller
{
    public function __construct(
        protected ScorecardService $scorecardService,
    ) {}

    /**
     * Get scorecard form structure for an interview.
     *
     * GET /api/v1/interviews/{interviewId}/scorecard-form
     */
    public function form(Request $request, string $interviewId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->scorecardService->getScorecardForm($interviewId, $tenantId);

        return response()->json(['data' => $data]);
    }

    /**
     * Submit a scorecard for an interview.
     *
     * POST /api/v1/interviews/{interviewId}/scorecard
     */
    public function store(SubmitScorecardRequest $request, string $interviewId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $scorecard = $this->scorecardService->submit(
            $request->validated(),
            $interviewId,
            $userId,
            $tenantId
        );

        return response()->json([
            'data' => $this->formatScorecard($scorecard),
        ], 201);
    }

    /**
     * Get scorecard detail.
     *
     * GET /api/v1/scorecards/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $scorecard = $this->scorecardService->getDetail($id, $tenantId);

        if (!$scorecard) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Scorecard not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $this->formatScorecard($scorecard),
        ]);
    }

    /**
     * Update a scorecard.
     *
     * PUT /api/v1/scorecards/{id}
     */
    public function update(UpdateScorecardRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $scorecard = $this->scorecardService->getDetail($id, $tenantId);

        if (!$scorecard) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Scorecard not found.',
                ],
            ], 404);
        }

        // Ownership check: only the submitter can update
        if ($scorecard->submitted_by !== $userId) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You can only update your own scorecard.',
                ],
            ], 403);
        }

        $updated = $this->scorecardService->update($scorecard, $request->validated());

        return response()->json([
            'data' => $this->formatScorecard($updated),
        ]);
    }

    /**
     * List all scorecards for an interview.
     *
     * GET /api/v1/interviews/{interviewId}/scorecards
     */
    public function listForInterview(Request $request, string $interviewId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $scorecards = $this->scorecardService->listForInterview($interviewId, $tenantId);

        return response()->json([
            'data' => $scorecards->map(fn ($sc) => $this->formatScorecard($sc))->all(),
        ]);
    }

    /**
     * Get aggregated scorecard summary for an application.
     *
     * GET /api/v1/applications/{appId}/scorecard-summary
     */
    public function summary(Request $request, string $appId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->scorecardService->getSummary($appId, $tenantId);

        return response()->json(['data' => $data]);
    }

    /**
     * Format a scorecard model for API response.
     */
    protected function formatScorecard($scorecard): array
    {
        return [
            'id' => $scorecard->id,
            'interview_id' => $scorecard->interview_id,
            'submitted_by' => $scorecard->submitted_by,
            'submitter_name' => $scorecard->submitter->name ?? null,
            'overall_rating' => $scorecard->overall_rating,
            'overall_recommendation' => $scorecard->overall_recommendation,
            'notes' => $scorecard->notes,
            'criteria' => $scorecard->criteria->map(function ($c) {
                return [
                    'id' => $c->id,
                    'question_text' => $c->question_text,
                    'category' => $c->category,
                    'sort_order' => $c->sort_order,
                    'rating' => $c->rating,
                    'notes' => $c->notes,
                ];
            })->all(),
            'submitted_at' => $scorecard->submitted_at->toIso8601String(),
            'updated_at' => $scorecard->updated_at->toIso8601String(),
        ];
    }
}
