<?php

namespace App\Http\Controllers;

use App\Http\Requests\AIGenerateBulletsRequest;
use App\Http\Requests\AIGenerateSummaryRequest;
use App\Http\Requests\AIImproveTextRequest;
use App\Http\Requests\AIOptimizeATSRequest;
use App\Http\Requests\AISuggestSkillsRequest;
use App\Services\AIService;
use App\Services\RateLimitExceededException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function __construct(
        protected AIService $aiService,
    ) {}

    /**
     * Generate a professional summary.
     *
     * POST /api/v1/candidate/ai/summary
     */
    public function summary(AIGenerateSummaryRequest $request): JsonResponse
    {
        return $this->createAIJob($request, 'summary', $request->validated());
    }

    /**
     * Generate work experience bullet points.
     *
     * POST /api/v1/candidate/ai/bullets
     */
    public function bullets(AIGenerateBulletsRequest $request): JsonResponse
    {
        return $this->createAIJob($request, 'bullets', $request->validated());
    }

    /**
     * Suggest relevant skills.
     *
     * POST /api/v1/candidate/ai/skills
     */
    public function skills(AISuggestSkillsRequest $request): JsonResponse
    {
        return $this->createAIJob($request, 'skills', $request->validated());
    }

    /**
     * Optimize resume for ATS compatibility.
     *
     * POST /api/v1/candidate/ai/ats-optimize
     */
    public function atsOptimize(AIOptimizeATSRequest $request): JsonResponse
    {
        return $this->createAIJob($request, 'ats_optimize', $request->validated());
    }

    /**
     * Improve existing text.
     *
     * POST /api/v1/candidate/ai/improve
     */
    public function improve(AIImproveTextRequest $request): JsonResponse
    {
        return $this->createAIJob($request, 'improve', $request->validated());
    }

    /**
     * Poll AI job status.
     *
     * GET /api/v1/candidate/ai/jobs/{id}
     */
    public function getJob(Request $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        $job = $this->aiService->getJob($candidate->id, $id);

        return response()->json([
            'data' => $job,
        ]);
    }

    /**
     * Create an AI job and return 202 Accepted, or 429 if rate limited.
     *
     * @param  Request  $request
     * @param  string  $jobType
     * @param  array<string, mixed>  $inputData
     * @return JsonResponse
     */
    protected function createAIJob(Request $request, string $jobType, array $inputData): JsonResponse
    {
        $candidate = $request->user();

        try {
            $result = $this->aiService->createJob($candidate->id, $jobType, $inputData);

            return response()->json([
                'data' => $result,
            ], 202);
        } catch (RateLimitExceededException $e) {
            return response()->json([
                'error' => [
                    'code' => 'AI_RATE_LIMIT_EXCEEDED',
                    'message' => $e->getMessage(),
                    'details' => [
                        'retry_after' => $e->retryAfter,
                        'limit_type' => $e->limitType,
                        'limit' => $e->limit,
                        'used' => $e->used,
                    ],
                ],
            ], 429)->withHeaders([
                'Retry-After' => $e->retryAfter,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }
}
