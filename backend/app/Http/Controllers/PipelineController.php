<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPipelineStageRequest;
use App\Http\Requests\MoveApplicationRequest;
use App\Http\Requests\ReorderPipelineStagesRequest;
use App\Models\JobPosting;
use App\Models\PipelineStage;
use App\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function __construct(
        protected PipelineService $pipelineService,
    ) {}

    /**
     * List pipeline stages for a job posting.
     *
     * GET /api/v1/jobs/{jobId}/stages
     */
    public function listStages(string $jobId): JsonResponse
    {
        // Verify job posting exists in tenant scope
        $jobPosting = JobPosting::findOrFail($jobId);

        $stages = PipelineStage::where('job_posting_id', $jobId)
            ->orderBy('sort_order')
            ->withCount('jobApplications')
            ->get();

        return response()->json([
            'data' => $stages->map(fn ($stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'sort_order' => $stage->sort_order,
                'application_count' => $stage->job_applications_count ?? 0,
            ])->values()->toArray(),
        ]);
    }

    /**
     * Add a new pipeline stage to a job posting.
     *
     * POST /api/v1/jobs/{jobId}/stages
     */
    public function addStage(AddPipelineStageRequest $request, string $jobId): JsonResponse
    {
        // Verify job posting exists in tenant scope
        JobPosting::findOrFail($jobId);

        $stage = $this->pipelineService->addStage(
            $jobId,
            $request->validated()['name'],
            $request->validated()['sort_order'],
        );

        return response()->json([
            'data' => [
                'id' => $stage->id,
                'name' => $stage->name,
                'sort_order' => $stage->sort_order,
            ],
        ], 201);
    }

    /**
     * Reorder pipeline stages for a job posting.
     *
     * PUT /api/v1/jobs/{jobId}/stages/reorder
     */
    public function reorderStages(ReorderPipelineStagesRequest $request, string $jobId): JsonResponse
    {
        // Verify job posting exists in tenant scope
        JobPosting::findOrFail($jobId);

        $this->pipelineService->reorderStages(
            $jobId,
            $request->validated()['stages'],
        );

        // Return updated stages
        $stages = PipelineStage::where('job_posting_id', $jobId)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $stages->map(fn ($stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'sort_order' => $stage->sort_order,
            ])->values()->toArray(),
        ]);
    }

    /**
     * Remove a pipeline stage.
     *
     * DELETE /api/v1/jobs/{jobId}/stages/{stageId}
     */
    public function removeStage(string $jobId, string $stageId): JsonResponse
    {
        // Verify job posting exists in tenant scope
        JobPosting::findOrFail($jobId);

        // Verify stage belongs to this job posting
        $stage = PipelineStage::where('id', $stageId)
            ->where('job_posting_id', $jobId)
            ->firstOrFail();

        $this->pipelineService->removeStage($stageId);

        return response()->json(null, 204);
    }

    /**
     * Move an application to a different pipeline stage.
     *
     * POST /api/v1/applications/{appId}/move
     */
    public function moveApplication(MoveApplicationRequest $request, string $appId): JsonResponse
    {
        $user = $request->user();

        $transition = $this->pipelineService->moveApplication(
            $appId,
            $request->validated()['stage_id'],
            $user->id,
        );

        return response()->json([
            'data' => [
                'id' => $transition->id,
                'from_stage_id' => $transition->from_stage_id,
                'to_stage_id' => $transition->to_stage_id,
                'moved_by' => $transition->moved_by,
                'moved_at' => $transition->moved_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get transition history for an application.
     *
     * GET /api/v1/applications/{appId}/transitions
     */
    public function transitionHistory(string $appId): JsonResponse
    {
        $transitions = $this->pipelineService->getTransitionHistory($appId);

        return response()->json([
            'data' => $transitions->map(fn ($t) => [
                'id' => $t->id,
                'from_stage' => $t->fromStage ? [
                    'id' => $t->fromStage->id,
                    'name' => $t->fromStage->name,
                ] : null,
                'to_stage' => $t->toStage ? [
                    'id' => $t->toStage->id,
                    'name' => $t->toStage->name,
                ] : null,
                'moved_by' => $t->movedBy ? [
                    'id' => $t->movedBy->id,
                    'name' => $t->movedBy->name,
                ] : null,
                'moved_at' => $t->moved_at->toIso8601String(),
            ])->values()->toArray(),
        ]);
    }
}
