<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddEducationRequest;
use App\Http\Requests\AddWorkHistoryRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\ReplaceSkillsRequest;
use App\Http\Requests\UpdateEducationRequest;
use App\Http\Requests\UpdatePersonalInfoRequest;
use App\Http\Requests\UpdateWorkHistoryRequest;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateProfileController extends Controller
{
    public function __construct(
        protected CandidateProfileService $profileService,
    ) {}

    /**
     * Get the authenticated candidate's full profile.
     *
     * GET /api/v1/candidate/profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $candidate = $request->user();

        $profile = $this->profileService->getProfile($candidate->id);

        return response()->json([
            'data' => $profile,
        ]);
    }

    /**
     * Update the authenticated candidate's personal info.
     *
     * PUT /api/v1/candidate/profile
     */
    public function updatePersonalInfo(UpdatePersonalInfoRequest $request): JsonResponse
    {
        $candidate = $request->user();

        $personalInfo = $this->profileService->updatePersonalInfo(
            $candidate->id,
            $request->validated(),
        );

        return response()->json([
            'data' => $personalInfo,
        ]);
    }

    /**
     * Add a work history entry.
     *
     * POST /api/v1/candidate/profile/work-history
     */
    public function addWorkHistory(AddWorkHistoryRequest $request): JsonResponse
    {
        $candidate = $request->user();

        $entry = $this->profileService->addWorkHistory(
            $candidate->id,
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'job_title' => $entry->job_title,
                'company_name' => $entry->company_name,
                'start_date' => $entry->start_date?->format('Y-m'),
                'end_date' => $entry->end_date?->format('Y-m'),
                'description' => $entry->description,
                'sort_order' => $entry->sort_order,
            ],
        ], 201);
    }

    /**
     * Update a work history entry.
     *
     * PUT /api/v1/candidate/profile/work-history/{id}
     */
    public function updateWorkHistory(UpdateWorkHistoryRequest $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        $entry = $this->profileService->updateWorkHistory(
            $candidate->id,
            $id,
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'job_title' => $entry->job_title,
                'company_name' => $entry->company_name,
                'start_date' => $entry->start_date?->format('Y-m'),
                'end_date' => $entry->end_date?->format('Y-m'),
                'description' => $entry->description,
                'sort_order' => $entry->sort_order,
            ],
        ]);
    }

    /**
     * Delete a work history entry.
     *
     * DELETE /api/v1/candidate/profile/work-history/{id}
     */
    public function deleteWorkHistory(Request $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        $this->profileService->deleteWorkHistory($candidate->id, $id);

        return response()->json([
            'data' => [
                'message' => 'Work history entry deleted.',
            ],
        ]);
    }

    /**
     * Reorder work history entries.
     *
     * PUT /api/v1/candidate/profile/work-history/reorder
     */
    public function reorderWorkHistory(ReorderRequest $request): JsonResponse
    {
        $candidate = $request->user();

        $this->profileService->reorderWorkHistory(
            $candidate->id,
            $request->validated('ordered_ids'),
        );

        return response()->json([
            'data' => [
                'message' => 'Work history reordered.',
            ],
        ]);
    }

    /**
     * Add an education entry.
     *
     * POST /api/v1/candidate/profile/education
     */
    public function addEducation(AddEducationRequest $request): JsonResponse
    {
        $candidate = $request->user();

        $entry = $this->profileService->addEducation(
            $candidate->id,
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'institution_name' => $entry->institution_name,
                'degree' => $entry->degree,
                'field_of_study' => $entry->field_of_study,
                'start_date' => $entry->start_date?->format('Y-m'),
                'end_date' => $entry->end_date?->format('Y-m'),
                'sort_order' => $entry->sort_order,
            ],
        ], 201);
    }

    /**
     * Update an education entry.
     *
     * PUT /api/v1/candidate/profile/education/{id}
     */
    public function updateEducation(UpdateEducationRequest $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        $entry = $this->profileService->updateEducation(
            $candidate->id,
            $id,
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'id' => $entry->id,
                'institution_name' => $entry->institution_name,
                'degree' => $entry->degree,
                'field_of_study' => $entry->field_of_study,
                'start_date' => $entry->start_date?->format('Y-m'),
                'end_date' => $entry->end_date?->format('Y-m'),
                'sort_order' => $entry->sort_order,
            ],
        ]);
    }

    /**
     * Delete an education entry.
     *
     * DELETE /api/v1/candidate/profile/education/{id}
     */
    public function deleteEducation(Request $request, string $id): JsonResponse
    {
        $candidate = $request->user();

        $this->profileService->deleteEducation($candidate->id, $id);

        return response()->json([
            'data' => [
                'message' => 'Education entry deleted.',
            ],
        ]);
    }

    /**
     * Reorder education entries.
     *
     * PUT /api/v1/candidate/profile/education/reorder
     */
    public function reorderEducation(ReorderRequest $request): JsonResponse
    {
        $candidate = $request->user();

        $this->profileService->reorderEducation(
            $candidate->id,
            $request->validated('ordered_ids'),
        );

        return response()->json([
            'data' => [
                'message' => 'Education reordered.',
            ],
        ]);
    }

    /**
     * Replace all skills for the authenticated candidate.
     *
     * PUT /api/v1/candidate/profile/skills
     */
    public function replaceSkills(ReplaceSkillsRequest $request): JsonResponse
    {
        $candidate = $request->user();

        $skills = $this->profileService->replaceSkills(
            $candidate->id,
            $request->validated('skills'),
        );

        return response()->json([
            'data' => [
                'skills' => $skills,
            ],
        ]);
    }
}
