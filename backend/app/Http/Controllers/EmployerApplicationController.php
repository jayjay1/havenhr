<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\StageTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployerApplicationController extends Controller
{
    /**
     * List applications for a specific job posting.
     *
     * GET /api/v1/jobs/{jobId}/applications
     */
    public function listForJob(Request $request, string $jobId): JsonResponse
    {
        // Verify job posting exists in tenant scope
        $jobPosting = JobPosting::findOrFail($jobId);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = JobApplication::where('job_posting_id', $jobId)
            ->with(['candidate', 'pipelineStage'])
            ->orderByDesc('applied_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($app) => [
                'id' => $app->id,
                'candidate_name' => $app->candidate?->name,
                'candidate_email' => $app->candidate?->email,
                'current_stage' => $app->pipelineStage?->name,
                'status' => $app->status,
                'applied_at' => $app->applied_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get a single application detail with candidate profile, pipeline stage, and transitions.
     *
     * GET /api/v1/applications/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $application = JobApplication::with(['candidate', 'pipelineStage', 'jobPosting'])
            ->find($id);

        if (! $application) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Application not found.',
                ],
            ], 404);
        }

        // Verify the application belongs to a job posting in the current tenant
        $jobPosting = JobPosting::find($application->job_posting_id);
        if (! $jobPosting) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Application not found.',
                ],
            ], 404);
        }

        // Get transition history
        $transitions = StageTransition::where('job_application_id', $id)
            ->with(['fromStage', 'toStage', 'movedBy'])
            ->orderBy('moved_at', 'asc')
            ->get();

        return response()->json([
            'data' => [
                'id' => $application->id,
                'candidate_name' => $application->candidate?->name,
                'candidate_email' => $application->candidate?->email,
                'current_stage' => $application->pipelineStage?->name,
                'status' => $application->status,
                'applied_at' => $application->applied_at?->toIso8601String(),
                'resume_snapshot' => $application->resume_snapshot,
                'transitions' => $transitions->map(fn ($t) => [
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
            ],
        ]);
    }

    /**
     * List all unique candidates who applied to any job in the tenant.
     *
     * GET /api/v1/talent-pool
     */
    public function talentPool(Request $request): JsonResponse
    {
        // Get all job posting IDs for the current tenant
        $jobPostingIds = JobPosting::pluck('id');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = JobApplication::whereIn('job_posting_id', $jobPostingIds)
            ->with('candidate')
            ->orderByDesc('applied_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($app) => [
                'id' => $app->id,
                'candidate_name' => $app->candidate?->name,
                'candidate_email' => $app->candidate?->email,
                'status' => $app->status,
                'applied_at' => $app->applied_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
