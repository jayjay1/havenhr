<?php

namespace App\Services;

use App\Events\ApplicationStageChanged;
use App\Models\JobApplication;
use App\Models\PipelineStage;
use App\Models\StageTransition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PipelineService
{
    /**
     * Default pipeline stages created for every new job posting.
     */
    protected const DEFAULT_STAGES = [
        ['name' => 'Applied', 'sort_order' => 0],
        ['name' => 'Screening', 'sort_order' => 1],
        ['name' => 'Interview', 'sort_order' => 2],
        ['name' => 'Offer', 'sort_order' => 3],
        ['name' => 'Hired', 'sort_order' => 4],
        ['name' => 'Rejected', 'sort_order' => 5],
    ];

    /**
     * Create the default pipeline stages for a job posting.
     */
    public function createDefaultStages(string $jobPostingId): void
    {
        foreach (self::DEFAULT_STAGES as $stage) {
            PipelineStage::create([
                'job_posting_id' => $jobPostingId,
                'name' => $stage['name'],
                'sort_order' => $stage['sort_order'],
            ]);
        }
    }

    /**
     * Add a new pipeline stage to a job posting.
     */
    public function addStage(string $jobPostingId, string $name, int $sortOrder): PipelineStage
    {
        return PipelineStage::create([
            'job_posting_id' => $jobPostingId,
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Reorder pipeline stages for a job posting.
     *
     * @param  array<int, array{id: string, sort_order: int}>  $stageOrder
     */
    public function reorderStages(string $jobPostingId, array $stageOrder): void
    {
        foreach ($stageOrder as $item) {
            PipelineStage::where('id', $item['id'])
                ->where('job_posting_id', $jobPostingId)
                ->update(['sort_order' => $item['sort_order']]);
        }
    }

    /**
     * Remove a pipeline stage.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function removeStage(string $stageId): void
    {
        $stage = PipelineStage::findOrFail($stageId);

        // Check for associated applications
        $applicationCount = JobApplication::where('pipeline_stage_id', $stageId)->count();

        if ($applicationCount > 0) {
            abort(response()->json([
                'error' => [
                    'code' => 'STAGE_HAS_APPLICATIONS',
                    'message' => 'Cannot remove a pipeline stage that has active applications.',
                    'details' => [
                        'application_count' => $applicationCount,
                    ],
                ],
            ], 422));
        }

        $jobPostingId = $stage->job_posting_id;
        $stage->delete();

        // Reorder remaining stages to close gaps
        $remainingStages = PipelineStage::where('job_posting_id', $jobPostingId)
            ->orderBy('sort_order')
            ->get();

        foreach ($remainingStages as $index => $remainingStage) {
            $remainingStage->update(['sort_order' => $index]);
        }
    }

    /**
     * Move an application to a different pipeline stage.
     */
    public function moveApplication(string $applicationId, string $targetStageId, string $userId): StageTransition
    {
        $application = JobApplication::findOrFail($applicationId);
        $targetStage = PipelineStage::findOrFail($targetStageId);

        // Verify target stage belongs to the same job posting
        if ($targetStage->job_posting_id !== $application->job_posting_id) {
            abort(response()->json([
                'error' => [
                    'code' => 'INVALID_STAGE',
                    'message' => 'The target stage does not belong to this job posting.',
                ],
            ], 422));
        }

        $fromStageId = $application->pipeline_stage_id;

        // Update the application's current stage
        $application->pipeline_stage_id = $targetStageId;
        $application->save();

        // Create stage transition record
        $transition = StageTransition::create([
            'job_application_id' => $applicationId,
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $targetStageId,
            'moved_by' => $userId,
            'moved_at' => Carbon::now(),
        ]);

        // Dispatch event
        $tenantId = $application->jobPosting->tenant_id ?? 'platform';

        event(new ApplicationStageChanged(
            $tenantId,
            $userId,
            [
                'application_id' => $applicationId,
                'from_stage' => $fromStageId,
                'to_stage' => $targetStageId,
            ],
        ));

        return $transition;
    }

    /**
     * Get the transition history for an application.
     */
    public function getTransitionHistory(string $applicationId): Collection
    {
        return StageTransition::where('job_application_id', $applicationId)
            ->with(['fromStage', 'toStage', 'movedBy'])
            ->orderBy('moved_at', 'asc')
            ->get();
    }
}
