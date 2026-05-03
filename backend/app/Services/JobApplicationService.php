<?php

namespace App\Services;

use App\Events\CandidateApplied;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\PipelineStage;
use App\Models\Resume;
use App\Models\StageTransition;
use Illuminate\Support\Carbon;

class JobApplicationService
{
    /**
     * Submit a job application.
     *
     * Validates resume belongs to candidate, verifies job posting is published,
     * snapshots resume content, creates job_applications record with pipeline stage,
     * and dispatches CandidateApplied event.
     *
     * @param  string  $candidateId
     * @param  string  $jobPostingId
     * @param  string  $resumeId
     * @return JobApplication
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException if resume not found for candidate
     * @throws \RuntimeException if duplicate application
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException if job posting not published
     */
    public function apply(string $candidateId, string $jobPostingId, string $resumeId): JobApplication
    {
        // Verify job posting exists and is published
        $jobPosting = JobPosting::withoutGlobalScopes()->find($jobPostingId);

        if (! $jobPosting || $jobPosting->status !== 'published') {
            abort(response()->json([
                'error' => [
                    'code' => 'JOB_NOT_AVAILABLE',
                    'message' => 'This job posting is not available for applications.',
                ],
            ], 422));
        }

        // Validate resume belongs to candidate
        $resume = Resume::where('id', $resumeId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        // Check for duplicate application (candidate_id + job_posting_id)
        $existing = JobApplication::where('candidate_id', $candidateId)
            ->where('job_posting_id', $jobPostingId)
            ->exists();

        if ($existing) {
            throw new \RuntimeException('You have already applied to this job.');
        }

        // Get the first pipeline stage (Applied) for this job posting
        $firstStage = PipelineStage::where('job_posting_id', $jobPostingId)
            ->orderBy('sort_order')
            ->first();

        // Snapshot resume content as JSON
        $resumeSnapshot = $resume->content;

        // Create job_applications record
        $application = JobApplication::create([
            'candidate_id' => $candidateId,
            'job_posting_id' => $jobPostingId,
            'resume_id' => $resumeId,
            'resume_snapshot' => $resumeSnapshot,
            'pipeline_stage_id' => $firstStage?->id,
            'status' => 'submitted',
            'applied_at' => Carbon::now(),
        ]);

        // Dispatch CandidateApplied event
        event(new CandidateApplied(
            'platform', // no tenant context for candidates
            $candidateId,
            [
                'candidate_id' => $candidateId,
                'job_posting_id' => $jobPostingId,
                'application_id' => $application->id,
            ],
        ));

        return $application;
    }

    /**
     * List all applications for a candidate with job and pipeline context.
     *
     * @param  string  $candidateId
     * @param  string|null  $status
     * @param  string  $sortBy
     * @param  string  $sortDir
     * @return array<int, array<string, mixed>>
     */
    public function listCandidateApplications(
        string $candidateId,
        ?string $status = null,
        string $sortBy = 'applied_at',
        string $sortDir = 'desc',
    ): array {
        $query = JobApplication::where('candidate_id', $candidateId)
            ->with(['jobPosting' => function ($q) {
                $q->withoutGlobalScopes()->with(['company', 'pipelineStages' => function ($sq) {
                    $sq->orderBy('sort_order');
                }]);
            }, 'pipelineStage']);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($sortBy === 'job_title') {
            $query->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
                ->orderBy('job_postings.title', $sortDir)
                ->select('job_applications.*');
        } else {
            $query->orderBy('applied_at', $sortDir);
        }

        $applications = $query->get();

        return $applications->map(function (JobApplication $app) {
            $allStages = $app->jobPosting?->pipelineStages?->map(fn (PipelineStage $stage) => [
                'name' => $stage->name,
                'color' => $stage->color,
                'sort_order' => $stage->sort_order,
            ])->values()->toArray() ?? [];

            return [
                'id' => $app->id,
                'job_posting_id' => $app->job_posting_id,
                'resume_id' => $app->resume_id,
                'status' => $app->status,
                'applied_at' => $app->applied_at->toIso8601String(),
                'job_title' => $app->jobPosting?->title,
                'company_name' => $app->jobPosting?->company?->name,
                'location' => $app->jobPosting?->location,
                'employment_type' => $app->jobPosting?->employment_type,
                'pipeline_stage' => $app->pipelineStage ? [
                    'name' => $app->pipelineStage->name,
                    'color' => $app->pipelineStage->color,
                ] : null,
                'all_stages' => $allStages,
            ];
        })->values()->toArray();
    }

    /**
     * Get full detail for a single candidate application.
     *
     * @param  string  $candidateId
     * @param  string  $applicationId
     * @return array<string, mixed>|null
     */
    public function getCandidateApplicationDetail(string $candidateId, string $applicationId): ?array
    {
        $application = JobApplication::with(['jobPosting' => function ($q) {
            $q->withoutGlobalScopes()->with(['company', 'pipelineStages' => function ($sq) {
                $sq->orderBy('sort_order');
            }]);
        }, 'pipelineStage'])
            ->find($applicationId);

        if (! $application || $application->candidate_id !== $candidateId) {
            return null;
        }

        $transitions = StageTransition::where('job_application_id', $applicationId)
            ->with(['fromStage', 'toStage'])
            ->orderBy('moved_at')
            ->get();

        $allStages = $application->jobPosting?->pipelineStages?->map(fn (PipelineStage $stage) => [
            'name' => $stage->name,
            'color' => $stage->color,
            'sort_order' => $stage->sort_order,
        ])->values()->toArray() ?? [];

        $transitionsArray = $transitions->map(fn (StageTransition $t) => [
            'from_stage' => $t->fromStage?->name,
            'to_stage' => $t->toStage?->name,
            'moved_at' => $t->moved_at->toIso8601String(),
        ])->values()->toArray();

        return [
            'id' => $application->id,
            'job_posting_id' => $application->job_posting_id,
            'resume_id' => $application->resume_id,
            'status' => $application->status,
            'applied_at' => $application->applied_at->toIso8601String(),
            'job_title' => $application->jobPosting?->title,
            'company_name' => $application->jobPosting?->company?->name,
            'location' => $application->jobPosting?->location,
            'employment_type' => $application->jobPosting?->employment_type,
            'pipeline_stage' => $application->pipelineStage ? [
                'name' => $application->pipelineStage->name,
                'color' => $application->pipelineStage->color,
            ] : null,
            'all_stages' => $allStages,
            'transitions' => $transitionsArray,
            'resume_snapshot' => $application->resume_snapshot,
        ];
    }
}
