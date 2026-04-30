<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateResumeRequest;
use App\Http\Requests\ToggleSharingRequest;
use App\Http\Requests\UpdateResumeRequest;
use App\Services\ResumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResumeController extends Controller
{
    public function __construct(
        protected ResumeService $resumeService,
    ) {}

    /**
     * List all resumes for the authenticated candidate.
     *
     * GET /api/v1/candidate/resumes
     */
    public function index(Request $request): JsonResponse
    {
        $candidate = $request->user();

        $resumes = $this->resumeService->listResumes($candidate->id);

        return response()->json([
            'data' => $resumes,
        ]);
    }

    /**
     * Create a new resume.
     *
     * POST /api/v1/candidate/resumes
     */
    public function store(CreateResumeRequest $request): JsonResponse
    {
        $candidate = $request->user();

        try {
            $resume = $this->resumeService->createResume(
                $candidate->id,
                $request->validated(),
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'LIMIT_EXCEEDED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        return response()->json([
            'data' => $this->formatResume($resume),
        ], 201);
    }

    /**
     * Get a single resume with full content.
     *
     * GET /api/v1/candidate/resumes/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        $resume = $this->resumeService->getResume($candidate->id, $id);

        return response()->json([
            'data' => $this->formatResume($resume),
        ]);
    }

    /**
     * Update a resume (auto-save).
     *
     * PUT /api/v1/candidate/resumes/{id}
     */
    public function update(UpdateResumeRequest $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        try {
            $resume = $this->resumeService->updateResume(
                $candidate->id,
                $id,
                $request->validated(),
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'LIMIT_EXCEEDED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        return response()->json([
            'data' => $this->formatResume($resume),
        ]);
    }

    /**
     * Delete a resume and all its versions.
     *
     * DELETE /api/v1/candidate/resumes/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        $this->resumeService->deleteResume($candidate->id, $id);

        return response()->json([
            'data' => [
                'message' => 'Resume deleted.',
            ],
        ]);
    }

    /**
     * Finalize a resume.
     *
     * POST /api/v1/candidate/resumes/{id}/finalize
     */
    public function finalize(Request $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        $resume = $this->resumeService->finalizeResume($candidate->id, $id);

        return response()->json([
            'data' => $this->formatResume($resume),
        ]);
    }

    /**
     * List all versions for a resume.
     *
     * GET /api/v1/candidate/resumes/{id}/versions
     */
    public function listVersions(Request $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        $versions = $this->resumeService->listVersions($candidate->id, $id);

        return response()->json([
            'data' => $versions,
        ]);
    }

    /**
     * Restore a version.
     *
     * POST /api/v1/candidate/resumes/{id}/versions/{versionId}/restore
     */
    public function restoreVersion(Request $request, string $id, string $versionId): JsonResponse
    {
        $candidate = $request->user();

        try {
            $resume = $this->resumeService->restoreVersion($candidate->id, $id, $versionId);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'LIMIT_EXCEEDED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        return response()->json([
            'data' => $this->formatResume($resume),
        ]);
    }

    /**
     * Toggle public sharing.
     *
     * POST /api/v1/candidate/resumes/{id}/share
     */
    public function share(ToggleSharingRequest $request, string $id): JsonResponse
    {
        $candidate = $request->user();
        $validated = $request->validated();

        $resume = $this->resumeService->toggleSharing(
            $candidate->id,
            $id,
            (bool) $validated['enable'],
            (bool) ($validated['show_contact'] ?? false),
        );

        return response()->json([
            'data' => $this->formatResume($resume),
        ]);
    }

    /**
     * Export resume as PDF.
     *
     * POST /api/v1/candidate/resumes/{id}/export-pdf
     */
    public function exportPdf(Request $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        try {
            $result = $this->resumeService->exportPdf($candidate->id, $id);

            return response()->json([
                'data' => $result,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'PDF_EXPORT_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Format a resume model for JSON response.
     *
     * @param  \App\Models\Resume  $resume
     * @return array<string, mixed>
     */
    protected function formatResume($resume): array
    {
        return [
            'id' => $resume->id,
            'title' => $resume->title,
            'template_slug' => $resume->template_slug,
            'content' => $resume->content,
            'is_complete' => $resume->is_complete,
            'public_link_token' => $resume->public_link_token,
            'public_link_active' => $resume->public_link_active,
            'show_contact_on_public' => $resume->show_contact_on_public,
            'created_at' => $resume->created_at->toIso8601String(),
            'updated_at' => $resume->updated_at->toIso8601String(),
        ];
    }
}
