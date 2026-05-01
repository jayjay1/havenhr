<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller for dashboard metrics and analytics.
 *
 * Provides aggregated statistics for the employer dashboard home page.
 * All queries are tenant-scoped via the BelongsToTenant trait on JobPosting.
 */
class DashboardController extends Controller
{
    /**
     * Return aggregated dashboard metrics for the current tenant.
     *
     * GET /api/v1/dashboard/metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // Open jobs: count of published job postings (tenant-scoped via BelongsToTenant)
        $openJobsCount = JobPosting::where('status', 'published')->count();

        // Total candidates: distinct candidate_id from job_applications joined with tenant's job_postings
        $totalCandidates = JobApplication::query()
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->where('job_postings.tenant_id', $tenantId)
            ->distinct('job_applications.candidate_id')
            ->count('job_applications.candidate_id');

        // Applications this week: applications where applied_at >= 7 days ago, scoped to tenant
        $applicationsThisWeek = JobApplication::query()
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->where('job_postings.tenant_id', $tenantId)
            ->where('job_applications.applied_at', '>=', now()->subDays(7))
            ->count();

        // Pipeline conversion rate: percentage of applications at stages with sort_order > 0
        // (i.e., moved beyond the first/Applied stage)
        $totalApplications = JobApplication::query()
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->where('job_postings.tenant_id', $tenantId)
            ->count();

        $pipelineConversionRate = 0.0;
        if ($totalApplications > 0) {
            $advancedApplications = JobApplication::query()
                ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
                ->join('pipeline_stages', 'job_applications.pipeline_stage_id', '=', 'pipeline_stages.id')
                ->where('job_postings.tenant_id', $tenantId)
                ->where('pipeline_stages.sort_order', '>', 0)
                ->count();

            $pipelineConversionRate = round(($advancedApplications / $totalApplications) * 100, 1);
        }

        return response()->json([
            'data' => [
                'open_jobs_count' => $openJobsCount,
                'total_candidates' => $totalCandidates,
                'applications_this_week' => $applicationsThisWeek,
                'pipeline_conversion_rate' => $pipelineConversionRate,
            ],
        ]);
    }

    /**
     * Return application counts grouped by pipeline stage for the current tenant.
     *
     * GET /api/v1/dashboard/applications-by-stage
     */
    public function applicationsByStage(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $stages = DB::table('pipeline_stages')
            ->join('job_applications', 'pipeline_stages.id', '=', 'job_applications.pipeline_stage_id')
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->where('job_postings.tenant_id', $tenantId)
            ->select('pipeline_stages.name as stage_name', DB::raw('count(job_applications.id) as count'))
            ->groupBy('pipeline_stages.name', 'pipeline_stages.sort_order')
            ->orderBy('pipeline_stages.sort_order', 'asc')
            ->get();

        return response()->json([
            'data' => $stages,
        ]);
    }
}
