<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateInterviewKitRequest;
use App\Http\Requests\UpdateInterviewKitRequest;
use App\Services\InterviewKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewKitController extends Controller
{
    public function __construct(
        protected InterviewKitService $interviewKitService,
    ) {}

    /**
     * List all interview kits for a job posting grouped by stage.
     *
     * GET /api/v1/jobs/{jobId}/interview-kits
     */
    public function listForJob(Request $request, string $jobId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->interviewKitService->listForJob($jobId, $tenantId);

        return response()->json(['data' => $data]);
    }

    /**
     * Get interview kit detail with questions.
     *
     * GET /api/v1/interview-kits/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $kit = $this->interviewKitService->getDetail($id, $tenantId);

        if (!$kit) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Interview kit not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $this->formatKit($kit),
        ]);
    }

    /**
     * Create a new interview kit for a pipeline stage.
     *
     * POST /api/v1/jobs/{jobId}/stages/{stageId}/interview-kits
     */
    public function store(CreateInterviewKitRequest $request, string $jobId, string $stageId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $kit = $this->interviewKitService->create($request->validated(), $stageId, $jobId, $tenantId);

        return response()->json([
            'data' => $this->formatKit($kit),
        ], 201);
    }

    /**
     * Update an interview kit and its questions.
     *
     * PUT /api/v1/interview-kits/{id}
     */
    public function update(UpdateInterviewKitRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $kit = $this->interviewKitService->getDetail($id, $tenantId);

        if (!$kit) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Interview kit not found.',
                ],
            ], 404);
        }

        $updated = $this->interviewKitService->update($kit, $request->validated());

        return response()->json([
            'data' => $this->formatKit($updated),
        ]);
    }

    /**
     * Delete an interview kit.
     *
     * DELETE /api/v1/interview-kits/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $kit = $this->interviewKitService->getDetail($id, $tenantId);

        if (!$kit) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Interview kit not found.',
                ],
            ], 404);
        }

        $this->interviewKitService->delete($kit);

        return response()->json(['data' => null], 200);
    }

    /**
     * List available default kit templates.
     *
     * GET /api/v1/interview-kit-templates
     */
    public function templates(Request $request): JsonResponse
    {
        $data = $this->interviewKitService->getDefaultTemplates();

        return response()->json(['data' => $data]);
    }

    /**
     * Create a kit from a default template.
     *
     * POST /api/v1/jobs/{jobId}/stages/{stageId}/interview-kits/from-template
     */
    public function createFromTemplate(Request $request, string $jobId, string $stageId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $templateKey = $request->input('template_key');

        if (!$templateKey) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The template_key field is required.',
                ],
            ], 422);
        }

        $kit = $this->interviewKitService->createFromTemplate($templateKey, $stageId, $jobId, $tenantId);

        return response()->json([
            'data' => $this->formatKit($kit),
        ], 201);
    }

    /**
     * Format an interview kit model for API response.
     */
    protected function formatKit($kit): array
    {
        return [
            'id' => $kit->id,
            'pipeline_stage_id' => $kit->pipeline_stage_id,
            'name' => $kit->name,
            'description' => $kit->description,
            'questions' => $kit->questions->map(function ($q) {
                return [
                    'id' => $q->id,
                    'text' => $q->text,
                    'category' => $q->category,
                    'sort_order' => $q->sort_order,
                    'scoring_rubric' => $q->scoring_rubric,
                ];
            })->all(),
            'created_at' => $kit->created_at->toIso8601String(),
            'updated_at' => $kit->updated_at->toIso8601String(),
        ];
    }
}
