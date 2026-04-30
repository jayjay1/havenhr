<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployerApplicationController extends Controller
{
    /**
     * List applications for a specific job posting.
     *
     * GET /api/v1/jobs/{jobId}/applications
     *
     * Placeholder: returns empty array until Job Management spec is implemented.
     */
    public function listForJob(Request $request, string $jobId): JsonResponse
    {
        // Placeholder — full implementation requires the Job Management spec
        // and a job_postings table scoped by tenant_id.
        return response()->json([
            'data' => [],
        ]);
    }

    /**
     * Get a single application detail with candidate profile and frozen resume.
     *
     * GET /api/v1/applications/{id}
     *
     * Placeholder: returns 404 until Job Management spec is implemented.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        // Placeholder — full implementation requires the Job Management spec.
        return response()->json([
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Application not found.',
            ],
        ], 404);
    }

    /**
     * List all unique candidates who applied to any job in the tenant.
     *
     * GET /api/v1/talent-pool
     *
     * Placeholder: returns empty array until Job Management spec is implemented.
     */
    public function talentPool(Request $request): JsonResponse
    {
        // Placeholder — full implementation requires the Job Management spec
        // and tenant-scoped job_postings.
        return response()->json([
            'data' => [],
        ]);
    }
}
