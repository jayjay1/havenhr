<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\PipelineStage;
use App\Models\StageTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for reporting and analytics endpoints.
 *
 * All queries are tenant-scoped via job_postings.tenant_id joins.
 * All endpoints require the reports.view permission.
 */
class ReportsController extends Controller
{
    /**
     * Parse and validate date range from request.
     *
     * @return array{start_date: string, end_date: string}|JsonResponse
     */
    private function parseDateRange(Request $request): array|JsonResponse
    {
        $startDate = $request->query('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::now()->format('Y-m-d'));

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !strtotime($startDate)) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The start date must be a valid date in Y-m-d format.',
                ],
            ], 422);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || !strtotime($endDate)) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The end date must be a valid date in Y-m-d format.',
                ],
            ], 422);
        }

        // Validate start <= end
        if ($startDate > $endDate) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The start date must be before or equal to the end date.',
                ],
            ], 422);
        }

        return ['start_date' => $startDate, 'end_date' => $endDate];
    }

    /**
     * Get the tenant ID from the authenticated user.
     */
    private function getTenantId(Request $request): string
    {
        return $request->user()->tenant_id;
    }

    /**
     * Get completed hires (applications at the final pipeline stage) within date range for a tenant.
     * Returns a query builder for further manipulation.
     *
     * A "hire" = application whose current pipeline_stage_id is the stage with the
     * highest sort_order for that job posting.
     */
    private function getHiresQuery(string $tenantId, string $startDate, string $endDate)
    {
        return DB::table('job_applications')
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->join('pipeline_stages as current_stage', 'job_applications.pipeline_stage_id', '=', 'current_stage.id')
            ->whereRaw('current_stage.sort_order = (
                SELECT MAX(ps.sort_order) FROM pipeline_stages ps WHERE ps.job_posting_id = job_postings.id
            )')
            ->where('job_postings.tenant_id', $tenantId)
            ->whereBetween('job_applications.applied_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ]);
    }

    /**
     * GET /reports/overview
     *
     * Returns high-level hiring metrics: avg_time_to_hire, total_hires, offer_acceptance_rate.
     */
    public function overview(Request $request): JsonResponse
    {
        $dateRange = $this->parseDateRange($request);
        if ($dateRange instanceof JsonResponse) {
            return $dateRange;
        }

        $tenantId = $this->getTenantId($request);
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];

        // Get hires with their time-to-hire
        $hires = $this->getHiresQuery($tenantId, $startDate, $endDate)
            ->join('stage_transitions', function ($join) {
                $join->on('stage_transitions.job_application_id', '=', 'job_applications.id')
                    ->on('stage_transitions.to_stage_id', '=', 'current_stage.id');
            })
            ->select(
                'job_applications.id',
                'job_applications.applied_at',
                'stage_transitions.moved_at'
            )
            ->get();

        $totalHires = $hires->count();

        // Compute avg time to hire
        $avgTimeToHire = 0;
        if ($totalHires > 0) {
            $totalDays = $hires->sum(function ($hire) {
                $appliedAt = Carbon::parse($hire->applied_at);
                $movedAt = Carbon::parse($hire->moved_at);
                return $appliedAt->diffInDays($movedAt);
            });
            $avgTimeToHire = round($totalDays / $totalHires, 1);
        }

        // Compute offer acceptance rate
        // Candidates at final stage / candidates who reached second-to-last stage
        $offerAcceptanceRate = 0;

        // Get all job postings for this tenant that have applications in the date range
        $jobPostingIds = DB::table('job_applications')
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->where('job_postings.tenant_id', $tenantId)
            ->whereBetween('job_applications.applied_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->pluck('job_postings.id')
            ->unique();

        $totalAtSecondToLast = 0;
        $totalAtFinal = 0;

        foreach ($jobPostingIds as $jobPostingId) {
            $stages = DB::table('pipeline_stages')
                ->where('job_posting_id', $jobPostingId)
                ->orderBy('sort_order', 'desc')
                ->limit(2)
                ->pluck('sort_order', 'id');

            if ($stages->count() < 2) {
                continue;
            }

            $stageIds = $stages->keys()->toArray();
            $finalStageId = $stageIds[0];
            $secondToLastStageId = $stageIds[1];
            $secondToLastSortOrder = $stages[$secondToLastStageId];

            // Count candidates who reached second-to-last stage
            // "Reached" = current stage sort_order >= second-to-last sort_order OR has a stage_transition to that stage
            $reachedSecondToLast = DB::table('job_applications')
                ->join('pipeline_stages as cs', 'job_applications.pipeline_stage_id', '=', 'cs.id')
                ->where('job_applications.job_posting_id', $jobPostingId)
                ->whereBetween('job_applications.applied_at', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ])
                ->where(function ($query) use ($secondToLastSortOrder, $secondToLastStageId) {
                    $query->where('cs.sort_order', '>=', $secondToLastSortOrder)
                        ->orWhereExists(function ($sub) use ($secondToLastStageId) {
                            $sub->select(DB::raw(1))
                                ->from('stage_transitions')
                                ->whereColumn('stage_transitions.job_application_id', 'job_applications.id')
                                ->where('stage_transitions.to_stage_id', $secondToLastStageId);
                        });
                })
                ->count();

            // Count candidates at final stage
            $atFinal = DB::table('job_applications')
                ->where('job_posting_id', $jobPostingId)
                ->where('pipeline_stage_id', $finalStageId)
                ->whereBetween('applied_at', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ])
                ->count();

            $totalAtSecondToLast += $reachedSecondToLast;
            $totalAtFinal += $atFinal;
        }

        if ($totalAtSecondToLast > 0) {
            $offerAcceptanceRate = round(($totalAtFinal / $totalAtSecondToLast) * 100, 1);
        }

        return response()->json([
            'data' => [
                'avg_time_to_hire' => $avgTimeToHire,
                'total_hires' => $totalHires,
                'offer_acceptance_rate' => $offerAcceptanceRate,
            ],
        ]);
    }

    /**
     * GET /reports/time-to-hire
     *
     * Returns time-to-hire breakdowns: by_job, by_department, by_stage, trend.
     */
    public function timeToHire(Request $request): JsonResponse
    {
        $dateRange = $this->parseDateRange($request);
        if ($dateRange instanceof JsonResponse) {
            return $dateRange;
        }

        $tenantId = $this->getTenantId($request);
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];

        // Get all hires with their details
        $hires = $this->getHiresQuery($tenantId, $startDate, $endDate)
            ->join('stage_transitions', function ($join) {
                $join->on('stage_transitions.job_application_id', '=', 'job_applications.id')
                    ->on('stage_transitions.to_stage_id', '=', 'current_stage.id');
            })
            ->select(
                'job_applications.id as application_id',
                'job_applications.applied_at',
                'job_applications.job_posting_id',
                'job_postings.title as job_title',
                'job_postings.department',
                'stage_transitions.moved_at'
            )
            ->get();

        // by_job: avg time-to-hire and hire count per job
        $byJob = $hires->groupBy('job_posting_id')->map(function ($group) {
            $avgDays = $group->avg(function ($hire) {
                return Carbon::parse($hire->applied_at)->diffInDays(Carbon::parse($hire->moved_at));
            });
            return [
                'job_id' => $group->first()->job_posting_id,
                'job_title' => $group->first()->job_title,
                'avg_days' => round($avgDays, 1),
                'hire_count' => $group->count(),
            ];
        })->values()->toArray();

        // by_department: avg time-to-hire and hire count per department
        $byDepartment = $hires->groupBy('department')->map(function ($group, $department) {
            $avgDays = $group->avg(function ($hire) {
                return Carbon::parse($hire->applied_at)->diffInDays(Carbon::parse($hire->moved_at));
            });
            return [
                'department' => $department ?: 'Unspecified',
                'avg_days' => round($avgDays, 1),
                'hire_count' => $group->count(),
            ];
        })->values()->toArray();

        // by_stage: avg stage duration per pipeline stage name
        $byStage = $this->computeStageDurations($tenantId, $startDate, $endDate);

        // trend: monthly avg time-to-hire for last 6 months
        $trend = $this->computeMonthlyTrend($hires);

        return response()->json([
            'data' => [
                'by_job' => $byJob,
                'by_department' => $byDepartment,
                'by_stage' => $byStage,
                'trend' => $trend,
            ],
        ]);
    }

    /**
     * Compute average stage durations across all applications in the date range.
     */
    private function computeStageDurations(string $tenantId, string $startDate, string $endDate): array
    {
        // Get all applications in the date range for this tenant
        $applicationIds = DB::table('job_applications')
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->where('job_postings.tenant_id', $tenantId)
            ->whereBetween('job_applications.applied_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->pluck('job_applications.id');

        if ($applicationIds->isEmpty()) {
            return [];
        }

        // For each application, get transitions ordered by moved_at
        $stageDurations = [];

        foreach ($applicationIds as $appId) {
            $app = DB::table('job_applications')
                ->where('id', $appId)
                ->select('applied_at', 'pipeline_stage_id', 'job_posting_id')
                ->first();

            $transitions = DB::table('stage_transitions')
                ->where('job_application_id', $appId)
                ->orderBy('moved_at', 'asc')
                ->select('from_stage_id', 'to_stage_id', 'moved_at')
                ->get();

            if ($transitions->isEmpty()) {
                // Application hasn't moved — get the first stage name
                $stage = DB::table('pipeline_stages')
                    ->where('id', $app->pipeline_stage_id)
                    ->first();
                if ($stage) {
                    $stageDurations[$stage->name][] = Carbon::parse($app->applied_at)->diffInDays(Carbon::now());
                }
                continue;
            }

            // First stage: from applied_at to first transition moved_at
            $firstTransition = $transitions->first();
            $firstStageName = DB::table('pipeline_stages')
                ->where('id', $firstTransition->from_stage_id)
                ->value('name');

            if ($firstStageName) {
                $duration = Carbon::parse($app->applied_at)->diffInDays(Carbon::parse($firstTransition->moved_at));
                $stageDurations[$firstStageName][] = $duration;
            }

            // Subsequent stages: from transition IN to transition OUT
            for ($i = 0; $i < $transitions->count(); $i++) {
                $currentTransition = $transitions[$i];
                $toStageName = DB::table('pipeline_stages')
                    ->where('id', $currentTransition->to_stage_id)
                    ->value('name');

                if (!$toStageName) {
                    continue;
                }

                if ($i + 1 < $transitions->count()) {
                    // Duration = next transition moved_at - current transition moved_at
                    $nextTransition = $transitions[$i + 1];
                    $duration = Carbon::parse($currentTransition->moved_at)->diffInDays(Carbon::parse($nextTransition->moved_at));
                } else {
                    // Still in this stage — duration from transition to now
                    $duration = Carbon::parse($currentTransition->moved_at)->diffInDays(Carbon::now());
                }

                $stageDurations[$toStageName][] = $duration;
            }
        }

        // Average the durations per stage
        $result = [];
        foreach ($stageDurations as $stageName => $durations) {
            $result[] = [
                'stage_name' => $stageName,
                'avg_days' => round(array_sum($durations) / count($durations), 1),
            ];
        }

        return $result;
    }

    /**
     * Compute monthly average time-to-hire for the last 6 months.
     */
    private function computeMonthlyTrend($hires): array
    {
        // Group hires by month of their final stage transition (moved_at)
        $grouped = $hires->groupBy(function ($hire) {
            return Carbon::parse($hire->moved_at)->format('Y-m');
        });

        // Get last 6 months
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = Carbon::now()->subMonths($i)->format('Y-m');
        }

        $trend = [];
        foreach ($months as $month) {
            $monthHires = $grouped->get($month, collect());
            if ($monthHires->isEmpty()) {
                $trend[] = [
                    'month' => $month,
                    'avg_days' => 0,
                ];
            } else {
                $avgDays = $monthHires->avg(function ($hire) {
                    return Carbon::parse($hire->applied_at)->diffInDays(Carbon::parse($hire->moved_at));
                });
                $trend[] = [
                    'month' => $month,
                    'avg_days' => round($avgDays, 1),
                ];
            }
        }

        return $trend;
    }

    /**
     * GET /reports/funnel
     *
     * Returns pipeline funnel data with candidate counts and conversion rates per stage.
     */
    public function funnel(Request $request): JsonResponse
    {
        $dateRange = $this->parseDateRange($request);
        if ($dateRange instanceof JsonResponse) {
            return $dateRange;
        }

        $tenantId = $this->getTenantId($request);
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $jobId = $request->query('job_id');

        // Validate job_id if provided
        if ($jobId) {
            $jobExists = DB::table('job_postings')
                ->where('id', $jobId)
                ->where('tenant_id', $tenantId)
                ->exists();

            if (!$jobExists) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The selected job posting was not found.',
                    ],
                ], 422);
            }
        }

        if ($jobId) {
            // Per-job funnel: use actual pipeline stages for this job
            $stages = DB::table('pipeline_stages')
                ->where('job_posting_id', $jobId)
                ->orderBy('sort_order', 'asc')
                ->select('id', 'name', 'sort_order')
                ->get();

            $funnelStages = [];
            $previousCount = null;

            foreach ($stages as $stage) {
                // Count candidates who "reached" this stage
                $count = DB::table('job_applications')
                    ->where('job_posting_id', $jobId)
                    ->whereBetween('applied_at', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59',
                    ])
                    ->where(function ($query) use ($stage) {
                        // Current stage sort_order >= this stage's sort_order
                        $query->whereExists(function ($sub) use ($stage) {
                            $sub->select(DB::raw(1))
                                ->from('pipeline_stages as cs')
                                ->whereColumn('cs.id', 'job_applications.pipeline_stage_id')
                                ->where('cs.sort_order', '>=', $stage->sort_order);
                        })
                        // OR has a stage_transition to this stage
                        ->orWhereExists(function ($sub) use ($stage) {
                            $sub->select(DB::raw(1))
                                ->from('stage_transitions')
                                ->whereColumn('stage_transitions.job_application_id', 'job_applications.id')
                                ->where('stage_transitions.to_stage_id', $stage->id);
                        });
                    })
                    ->distinct('job_applications.candidate_id')
                    ->count('job_applications.candidate_id');

                $conversionRate = null;
                if ($previousCount !== null && $previousCount > 0) {
                    $conversionRate = round(($count / $previousCount) * 100, 1);
                }

                $funnelStages[] = [
                    'stage_name' => $stage->name,
                    'count' => $count,
                    'conversion_rate' => $conversionRate,
                ];

                $previousCount = $count;
            }
        } else {
            // Overall funnel: aggregate by stage name across all job postings
            // Get distinct stage names ordered by their typical sort_order
            $stageNames = DB::table('pipeline_stages')
                ->join('job_postings', 'pipeline_stages.job_posting_id', '=', 'job_postings.id')
                ->where('job_postings.tenant_id', $tenantId)
                ->select('pipeline_stages.name', DB::raw('MIN(pipeline_stages.sort_order) as min_sort_order'))
                ->groupBy('pipeline_stages.name')
                ->orderBy('min_sort_order', 'asc')
                ->get();

            $funnelStages = [];
            $previousCount = null;

            foreach ($stageNames as $stageName) {
                // Count distinct candidates who reached this stage name across all jobs
                $count = DB::table('job_applications')
                    ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
                    ->where('job_postings.tenant_id', $tenantId)
                    ->whereBetween('job_applications.applied_at', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59',
                    ])
                    ->where(function ($query) use ($stageName) {
                        // Current stage sort_order >= this stage name's sort_order (within same job)
                        $query->whereExists(function ($sub) use ($stageName) {
                            $sub->select(DB::raw(1))
                                ->from('pipeline_stages as cs')
                                ->whereColumn('cs.id', 'job_applications.pipeline_stage_id')
                                ->where('cs.sort_order', '>=', DB::raw(
                                    '(SELECT MIN(ps2.sort_order) FROM pipeline_stages ps2 WHERE ps2.job_posting_id = job_applications.job_posting_id AND ps2.name = ' . DB::connection()->getPdo()->quote($stageName->name) . ')'
                                ));
                        })
                        // OR has a stage_transition to a stage with this name
                        ->orWhereExists(function ($sub) use ($stageName) {
                            $sub->select(DB::raw(1))
                                ->from('stage_transitions')
                                ->join('pipeline_stages as ts', 'stage_transitions.to_stage_id', '=', 'ts.id')
                                ->whereColumn('stage_transitions.job_application_id', 'job_applications.id')
                                ->where('ts.name', $stageName->name);
                        });
                    })
                    ->distinct('job_applications.candidate_id')
                    ->count('job_applications.candidate_id');

                $conversionRate = null;
                if ($previousCount !== null && $previousCount > 0) {
                    $conversionRate = round(($count / $previousCount) * 100, 1);
                }

                $funnelStages[] = [
                    'stage_name' => $stageName->name,
                    'count' => $count,
                    'conversion_rate' => $conversionRate,
                ];

                $previousCount = $count;
            }
        }

        return response()->json([
            'data' => [
                'stages' => $funnelStages,
                'job_id' => $jobId,
            ],
        ]);
    }

    /**
     * GET /reports/sources
     *
     * Returns application counts grouped by source. For MVP, all are "direct".
     */
    public function sources(Request $request): JsonResponse
    {
        $dateRange = $this->parseDateRange($request);
        if ($dateRange instanceof JsonResponse) {
            return $dateRange;
        }

        $tenantId = $this->getTenantId($request);
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];

        $count = DB::table('job_applications')
            ->join('job_postings', 'job_applications.job_posting_id', '=', 'job_postings.id')
            ->where('job_postings.tenant_id', $tenantId)
            ->whereBetween('job_applications.applied_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
            ])
            ->count();

        $data = [];
        if ($count > 0) {
            $data[] = [
                'source' => 'direct',
                'count' => $count,
            ];
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * GET /reports/export/{type}
     *
     * Returns CSV file for the specified report type.
     */
    public function export(Request $request, string $type): StreamedResponse|JsonResponse
    {
        $allowedTypes = ['overview', 'time-to-hire', 'funnel', 'sources'];

        if (!in_array($type, $allowedTypes)) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid report type. Must be one of: overview, time-to-hire, funnel, sources.',
                ],
            ], 422);
        }

        // Get the data from the corresponding method
        switch ($type) {
            case 'overview':
                $response = $this->overview($request);
                break;
            case 'time-to-hire':
                $response = $this->timeToHire($request);
                break;
            case 'funnel':
                $response = $this->funnel($request);
                break;
            case 'sources':
                $response = $this->sources($request);
                break;
        }

        // If the underlying method returned an error, pass it through
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $data = json_decode($response->getContent(), true)['data'];

        $dateRange = $this->parseDateRange($request);
        if ($dateRange instanceof JsonResponse) {
            return $dateRange;
        }

        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $filename = "havenhr-{$type}-{$startDate}-to-{$endDate}.csv";

        $csvContent = $this->generateCsv($type, $data);

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Generate CSV content for a given report type and data.
     */
    private function generateCsv(string $type, array $data): string
    {
        $output = fopen('php://temp', 'r+');

        switch ($type) {
            case 'overview':
                fputcsv($output, ['Metric', 'Value']);
                fputcsv($output, ['Average Time to Hire (days)', $data['avg_time_to_hire']]);
                fputcsv($output, ['Total Hires', $data['total_hires']]);
                fputcsv($output, ['Offer Acceptance Rate (%)', $data['offer_acceptance_rate']]);
                break;

            case 'time-to-hire':
                // By Job section
                fputcsv($output, ['By Job']);
                fputcsv($output, ['Job ID', 'Job Title', 'Avg Days', 'Hire Count']);
                foreach ($data['by_job'] as $row) {
                    fputcsv($output, [$row['job_id'], $row['job_title'], $row['avg_days'], $row['hire_count']]);
                }
                fputcsv($output, []);

                // By Department section
                fputcsv($output, ['By Department']);
                fputcsv($output, ['Department', 'Avg Days', 'Hire Count']);
                foreach ($data['by_department'] as $row) {
                    fputcsv($output, [$row['department'], $row['avg_days'], $row['hire_count']]);
                }
                fputcsv($output, []);

                // By Stage section
                fputcsv($output, ['By Stage']);
                fputcsv($output, ['Stage Name', 'Avg Days']);
                foreach ($data['by_stage'] as $row) {
                    fputcsv($output, [$row['stage_name'], $row['avg_days']]);
                }
                fputcsv($output, []);

                // Trend section
                fputcsv($output, ['Monthly Trend']);
                fputcsv($output, ['Month', 'Avg Days']);
                foreach ($data['trend'] as $row) {
                    fputcsv($output, [$row['month'], $row['avg_days']]);
                }
                break;

            case 'funnel':
                fputcsv($output, ['Stage Name', 'Count', 'Conversion Rate (%)']);
                foreach ($data['stages'] as $row) {
                    fputcsv($output, [$row['stage_name'], $row['count'], $row['conversion_rate'] ?? '']);
                }
                break;

            case 'sources':
                fputcsv($output, ['Source', 'Count']);
                foreach ($data as $row) {
                    fputcsv($output, [$row['source'], $row['count']]);
                }
                break;
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
