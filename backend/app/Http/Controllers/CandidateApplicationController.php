<?php

namespace App\Http\Controllers;

use App\Services\JobApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateApplicationController extends Controller
{
    public function __construct(
        protected JobApplicationService $applicationService,
    ) {}

    /**
     * Submit a job application.
     *
     * POST /api/v1/candidate/applications
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'job_posting_id' => 'required|uuid',
            'resume_id' => 'required|uuid',
        ]);

        $candidate = $request->user();

        try {
            $application = $this->applicationService->apply(
                $candidate->id,
                $request->input('job_posting_id'),
                $request->input('resume_id'),
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Resume not found.',
                ],
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'DUPLICATE_APPLICATION',
                    'message' => $e->getMessage(),
                ],
            ], 409);
        }

        return response()->json([
            'data' => [
                'id' => $application->id,
                'job_posting_id' => $application->job_posting_id,
                'resume_id' => $application->resume_id,
                'status' => $application->status,
                'applied_at' => $application->applied_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * List all applications for the authenticated candidate.
     *
     * GET /api/v1/candidate/applications
     */
    public function index(Request $request): JsonResponse
    {
        $candidate = $request->user();

        $applications = $this->applicationService->listCandidateApplications($candidate->id);

        return response()->json([
            'data' => $applications,
        ]);
    }
}
