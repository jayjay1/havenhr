<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicJobController extends Controller
{
    /**
     * List published jobs across all tenants (public job board).
     *
     * GET /api/v1/public/jobs
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobPosting::withoutGlobalScopes()
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->with('company')
            ->withCount('jobApplications');

        // Search: case-insensitive on title, department, location
        if ($request->has('q') && $request->query('q') !== '') {
            $searchTerm = $request->query('q');
            $query->where(function ($q) use ($searchTerm) {
                $driver = $q->getConnection()->getDriverName();
                $like = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
                $term = '%' . $searchTerm . '%';

                $q->where('title', $like, $term)
                    ->orWhere('department', $like, $term)
                    ->orWhere('location', $like, $term);
            });
        }

        // Filter by employment_type
        if ($request->has('employment_type') && $request->query('employment_type') !== '') {
            $types = explode(',', $request->query('employment_type'));
            $query->whereIn('employment_type', $types);
        }

        // Filter by remote_status
        if ($request->has('remote_status') && $request->query('remote_status') !== '') {
            $statuses = explode(',', $request->query('remote_status'));
            $query->whereIn('remote_status', $statuses);
        }

        // Sorting
        $sortField = $request->query('sort', 'published_at');
        $sortDirection = $request->query('direction', 'desc');

        if ($sortField === 'title') {
            $query->orderBy('title', $sortDirection);
        } else {
            $query->orderBy('published_at', $sortDirection);
        }

        // Pagination
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($job) => [
                'id' => $job->id,
                'title' => $job->title,
                'slug' => $job->slug,
                'company_name' => $job->company?->name,
                'department' => $job->department,
                'location' => $job->location,
                'employment_type' => $job->employment_type,
                'remote_status' => $job->remote_status,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'published_at' => $job->published_at?->toIso8601String(),
                'application_count' => $job->job_applications_count ?? 0,
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
     * Get public job detail by slug.
     *
     * GET /api/v1/public/jobs/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $jobPosting = JobPosting::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->with('company')
            ->withCount('jobApplications')
            ->first();

        if (! $jobPosting) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Job posting not found.',
                ],
            ], 404);
        }

        $companyName = $jobPosting->company?->name;
        $logoUrl = $jobPosting->company?->settings['logo_url'] ?? null;
        $descriptionPreview = mb_substr(strip_tags($jobPosting->description), 0, 200);

        return response()->json([
            'data' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title,
                'slug' => $jobPosting->slug,
                'company_name' => $companyName,
                'company_logo_url' => $logoUrl,
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
                'application_count' => $jobPosting->job_applications_count ?? 0,
                'og' => [
                    'title' => "{$jobPosting->title} — {$companyName}",
                    'description' => $descriptionPreview,
                    'url' => config('app.url') . '/jobs/' . $jobPosting->slug,
                    'type' => 'website',
                ],
            ],
        ]);
    }
}
