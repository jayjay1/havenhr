<?php

namespace App\Services;

use App\Events\CandidateApplied;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\PipelineStage;
use App\Models\Resume;
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
     * @return array<int, array<string, mixed>>
     */
    public function listCandidateApplications(string $candidateId): array
    {
        $applications = JobApplication::where('candidate_id', $candidateId)
            ->with(['jobPosting' => function ($query) {
                $query->withoutGlobalScopes()->with('company');
            }, 'pipelineStage'])
            ->orderByDesc('applied_at')
            ->get();

        return $applications->map(fn (JobApplication $app) => [
            'id' => $app->id,
            'job_posting_id' => $app->job_posting_id,
            'resume_id' => $app->resume_id,
            'status' => $app->status,
            'applied_at' => $app->applied_at->toIso8601String(),
            'job_title' => $app->jobPosting?->title,
            'company_name' => $app->jobPosting?->company?->name,
            'pipeline_stage' => $app->pipelineStage?->name,
        ])->values()->toArray();
    }
}
