<?php

namespace App\Services;

use App\Events\JobPostingCreated;
use App\Events\JobPostingDeleted;
use App\Events\JobPostingStatusChanged;
use App\Events\JobPostingUpdated;
use App\Models\JobPosting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class JobPostingService
{
    /**
     * Allowed status transitions: current_status => [allowed_targets].
     */
    protected const STATUS_TRANSITIONS = [
        'draft' => ['published'],
        'published' => ['draft', 'closed'],
        'closed' => ['published', 'archived'],
        'archived' => [],
    ];

    public function __construct(
        protected PipelineService $pipelineService,
    ) {}

    /**
     * Create a new job posting.
     */
    public function create(array $data, string $userId): JobPosting
    {
        $slug = $this->generateSlug($data['title']);

        $jobPosting = JobPosting::create(array_merge($data, [
            'slug' => $slug,
            'status' => 'draft',
            'created_by' => $userId,
        ]));

        $this->pipelineService->createDefaultStages($jobPosting->id);

        event(new JobPostingCreated(
            $jobPosting->tenant_id,
            $userId,
            [
                'resource_type' => 'job_posting',
                'resource_id' => $jobPosting->id,
                'new_state' => [
                    'title' => $jobPosting->title,
                    'status' => $jobPosting->status,
                ],
            ],
        ));

        return $jobPosting;
    }

    /**
     * Update an existing job posting.
     */
    public function update(string $id, array $data, string $userId): JobPosting
    {
        $jobPosting = JobPosting::findOrFail($id);

        if ($jobPosting->status === 'archived') {
            abort(422, json_encode([
                'error' => [
                    'code' => 'ARCHIVED_NOT_EDITABLE',
                    'message' => 'Archived job postings cannot be edited.',
                ],
            ]));
        }

        $previousState = [
            'title' => $jobPosting->title,
            'status' => $jobPosting->status,
        ];

        $jobPosting->fill($data);

        // Regenerate slug if title changed on a published posting
        if ($jobPosting->isDirty('title') && $jobPosting->status === 'published') {
            $jobPosting->slug = $this->generateSlug($data['title']);
        }

        $jobPosting->save();

        event(new JobPostingUpdated(
            $jobPosting->tenant_id,
            $userId,
            [
                'resource_type' => 'job_posting',
                'resource_id' => $jobPosting->id,
                'previous_state' => $previousState,
                'new_state' => [
                    'title' => $jobPosting->title,
                    'status' => $jobPosting->status,
                ],
            ],
        ));

        return $jobPosting;
    }

    /**
     * Transition a job posting's status.
     */
    public function transitionStatus(string $id, string $newStatus, string $userId): JobPosting
    {
        $jobPosting = JobPosting::findOrFail($id);

        $currentStatus = $jobPosting->status;
        $allowedTransitions = self::STATUS_TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowedTransitions, true)) {
            abort(response()->json([
                'error' => [
                    'code' => 'INVALID_STATUS_TRANSITION',
                    'message' => "Cannot transition from '{$currentStatus}' to '{$newStatus}'.",
                    'details' => [
                        'current_status' => $currentStatus,
                        'requested_status' => $newStatus,
                        'allowed_transitions' => $allowedTransitions,
                    ],
                ],
            ], 422));
        }

        $previousStatus = $currentStatus;

        // Set published_at on first publish
        if ($newStatus === 'published' && $jobPosting->published_at === null) {
            $jobPosting->published_at = Carbon::now();
        }

        // Set closed_at on close
        if ($newStatus === 'closed') {
            $jobPosting->closed_at = Carbon::now();
        }

        $jobPosting->status = $newStatus;
        $jobPosting->save();

        event(new JobPostingStatusChanged(
            $jobPosting->tenant_id,
            $userId,
            [
                'resource_type' => 'job_posting',
                'resource_id' => $jobPosting->id,
                'previous_state' => $previousStatus,
                'new_state' => $newStatus,
            ],
        ));

        return $jobPosting;
    }

    /**
     * Soft-delete a draft job posting.
     */
    public function delete(string $id, string $userId): void
    {
        $jobPosting = JobPosting::findOrFail($id);

        if ($jobPosting->status !== 'draft') {
            abort(response()->json([
                'error' => [
                    'code' => 'DELETE_DRAFT_ONLY',
                    'message' => 'Only draft job postings can be deleted.',
                    'details' => [
                        'current_status' => $jobPosting->status,
                    ],
                ],
            ], 422));
        }

        $jobPosting->delete();

        event(new JobPostingDeleted(
            $jobPosting->tenant_id,
            $userId,
            [
                'resource_type' => 'job_posting',
                'resource_id' => $jobPosting->id,
                'previous_state' => [
                    'title' => $jobPosting->title,
                    'status' => $jobPosting->status,
                ],
            ],
        ));
    }

    /**
     * Get job posting detail with pipeline stages and application counts.
     */
    public function getDetail(string $id): JobPosting
    {
        $jobPosting = JobPosting::with(['pipelineStages' => function ($query) {
            $query->orderBy('sort_order')
                ->withCount('jobApplications');
        }])->withCount('jobApplications')->findOrFail($id);

        return $jobPosting;
    }

    /**
     * List job postings for the current tenant with filters, pagination, and sorting.
     */
    public function listForTenant(array $filters, array $pagination, array $sort): LengthAwarePaginator
    {
        $query = JobPosting::query();

        // Filter by status
        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', $filters['status']);
            $query->whereIn('status', $statuses);
        }

        // Add application count
        $query->withCount('jobApplications');

        // Sorting
        $sortField = $sort['field'] ?? 'created_at';
        $sortDirection = $sort['direction'] ?? 'desc';

        $allowedSortFields = ['created_at', 'title', 'status'];
        if ($sortField === 'application_count') {
            $query->orderBy('job_applications_count', $sortDirection);
        } elseif (in_array($sortField, $allowedSortFields, true)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $page = $pagination['page'] ?? 1;
        $perPage = min($pagination['per_page'] ?? 20, 100);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Generate a URL-safe slug from a title.
     */
    public function generateSlug(string $title): string
    {
        // Convert to lowercase
        $slug = strtolower($title);

        // Replace non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Collapse consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim leading/trailing hyphens
        $slug = trim($slug, '-');

        // Append 8-char UUID suffix
        $slug .= '-' . substr((string) Str::uuid(), 0, 8);

        return $slug;
    }
}
