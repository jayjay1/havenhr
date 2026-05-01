<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateJobPostingRequest;
use App\Http\Requests\TransitionJobPostingStatusRequest;
use App\Http\Requests\UpdateJobPostingRequest;
use App\Services\JobPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostingController extends Controller
{
    public function __construct(
        protected JobPostingService $jobPostingService,
    ) {}

    /**
     * List tenant job postings with filters, sorting, and pagination.
     *
     * GET /api/v1/jobs
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->query('status');
        }

        $pagination = [
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => min(100, max(1, (int) $request->query('per_page', 20))),
        ];

        $sort = [
            'field' => $request->query('sort', 'created_at'),
            'direction' => $request->query('direction', 'desc'),
        ];

        $paginator = $this->jobPostingService->listForTenant($filters, $pagination, $sort);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($job) => [
                'id' => $job->id,
                'title' => $job->title,
                'status' => $job->status,
                'department' => $job->department,
                'location' => $job->location,
                'employment_type' => $job->employment_type,
                'application_count' => $job->job_applications_count ?? 0,
                'published_at' => $job->published_at?->toIso8601String(),
                'created_at' => $job->created_at?->toIso8601String(),
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
     * Create a new job posting.
     *
     * POST /api/v1/jobs
     */
    public function store(CreateJobPostingRequest $request): JsonResponse
    {
        $user = $request->user();

        $jobPosting = $this->jobPostingService->create(
            $request->validated(),
            $user->id,
        );

        return response()->json([
            'data' => $this->formatJobPosting($jobPosting),
        ], 201);
    }

    /**
     * Get job posting detail with pipeline stages.
     *
     * GET /api/v1/jobs/{id}
     */
    public function show(string $id): JsonResponse
    {
        $jobPosting = $this->jobPostingService->getDetail($id);

        $data = $this->formatJobPosting($jobPosting);
        $data['application_count'] = $jobPosting->job_applications_count ?? 0;
        $data['pipeline_stages'] = $jobPosting->pipelineStages->map(fn ($stage) => [
            'id' => $stage->id,
            'name' => $stage->name,
            'sort_order' => $stage->sort_order,
            'application_count' => $stage->job_applications_count ?? 0,
        ])->values()->toArray();

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Update a job posting.
     *
     * PUT /api/v1/jobs/{id}
     */
    public function update(UpdateJobPostingRequest $request, string $id): JsonResponse
    {
        $user = $request->user();

        $jobPosting = $this->jobPostingService->update(
            $id,
            $request->validated(),
            $user->id,
        );

        return response()->json([
            'data' => $this->formatJobPosting($jobPosting),
        ]);
    }

    /**
     * Soft-delete a draft job posting.
     *
     * DELETE /api/v1/jobs/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $this->jobPostingService->delete($id, $user->id);

        return response()->json(null, 204);
    }

    /**
     * Transition job posting status.
     *
     * PATCH /api/v1/jobs/{id}/status
     */
    public function transitionStatus(TransitionJobPostingStatusRequest $request, string $id): JsonResponse
    {
        $user = $request->user();

        $jobPosting = $this->jobPostingService->transitionStatus(
            $id,
            $request->validated()['status'],
            $user->id,
        );

        return response()->json([
            'data' => $this->formatJobPosting($jobPosting),
        ]);
    }

    /**
     * Format a job posting for API response.
     */
    protected function formatJobPosting($jobPosting): array
    {
        return [
            'id' => $jobPosting->id,
            'title' => $jobPosting->title,
            'slug' => $jobPosting->slug,
            'status' => $jobPosting->status,
            'department' => $jobPosting->department,
            'location' => $jobPosting->location,
            'employment_type' => $jobPosting->employment_type,
            'remote_status' => $jobPosting->remote_status,
            'salary_min' => $jobPosting->salary_min,
            'salary_max' => $jobPosting->salary_max,
            'salary_currency' => $jobPosting->salary_currency,
            'description' => $jobPosting->description,
            'requirements' => $jobPosting->requirements,
            'benefits' => $jobPosting->benefits,
            'published_at' => $jobPosting->published_at?->toIso8601String(),
            'closed_at' => $jobPosting->closed_at?->toIso8601String(),
            'created_at' => $jobPosting->created_at?->toIso8601String(),
            'updated_at' => $jobPosting->updated_at?->toIso8601String(),
        ];
    }
}
