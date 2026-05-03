<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleInterviewRequest;
use App\Http\Requests\UpdateInterviewRequest;
use App\Models\Interview;
use App\Services\InterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    public function __construct(
        protected InterviewService $interviewService,
    ) {}

    /**
     * Schedule a new interview.
     *
     * POST /api/v1/interviews
     */
    public function store(ScheduleInterviewRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $interview = $this->interviewService->schedule($request->validated(), $tenantId);

        return response()->json([
            'data' => $this->formatInterview($interview),
        ], 201);
    }

    /**
     * List interviews for a specific application.
     *
     * GET /api/v1/applications/{appId}/interviews
     */
    public function listForApplication(Request $request, string $appId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $interviews = $this->interviewService->listForApplication($appId, $tenantId);

        return response()->json([
            'data' => $interviews->map(fn ($interview) => [
                'id' => $interview->id,
                'interviewer_name' => $interview->interviewer->name ?? null,
                'interviewer_email' => $interview->interviewer->email ?? null,
                'scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'duration_minutes' => $interview->duration_minutes,
                'interview_type' => $interview->interview_type,
                'status' => $interview->status,
                'location' => $interview->location,
                'notes' => $interview->notes,
            ]),
        ]);
    }

    /**
     * Get interview detail.
     *
     * GET /api/v1/interviews/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $interview = $this->interviewService->getDetail($id, $tenantId);

        if (!$interview) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Interview not found.',
                ],
            ], 404);
        }

        $data = $this->formatInterview($interview);
        $data['candidate_name'] = $interview->jobApplication->candidate->name ?? null;
        $data['job_title'] = $interview->jobApplication->jobPosting->title ?? null;

        return response()->json(['data' => $data]);
    }

    /**
     * Update an interview.
     *
     * PUT /api/v1/interviews/{id}
     */
    public function update(UpdateInterviewRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $interview = Interview::whereHas('jobApplication', function ($q) use ($tenantId) {
            $q->whereHas('jobPosting', function ($q2) use ($tenantId) {
                $q2->where('tenant_id', $tenantId);
            });
        })->find($id);

        if (!$interview) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Interview not found.',
                ],
            ], 404);
        }

        $updated = $this->interviewService->update($interview, $request->validated());

        return response()->json([
            'data' => $this->formatInterview($updated),
        ]);
    }

    /**
     * Cancel an interview.
     *
     * PATCH /api/v1/interviews/{id}/cancel
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $interview = Interview::whereHas('jobApplication', function ($q) use ($tenantId) {
            $q->whereHas('jobPosting', function ($q2) use ($tenantId) {
                $q2->where('tenant_id', $tenantId);
            });
        })->find($id);

        if (!$interview) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Interview not found.',
                ],
            ], 404);
        }

        $cancelled = $this->interviewService->cancel($interview);

        return response()->json([
            'data' => $this->formatInterview($cancelled),
        ]);
    }

    /**
     * Get upcoming interviews for the dashboard widget.
     *
     * GET /api/v1/dashboard/upcoming-interviews
     */
    public function upcoming(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $interviews = $this->interviewService->getUpcoming($tenantId);

        return response()->json([
            'data' => $interviews->map(fn ($interview) => [
                'id' => $interview->id,
                'candidate_name' => $interview->jobApplication->candidate->name ?? null,
                'job_title' => $interview->jobApplication->jobPosting->title ?? null,
                'scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'duration_minutes' => $interview->duration_minutes,
                'interview_type' => $interview->interview_type,
                'location' => $interview->location,
            ]),
        ]);
    }

    /**
     * Get interviews for the authenticated candidate.
     *
     * GET /api/v1/candidate/interviews
     */
    public function candidateInterviews(Request $request): JsonResponse
    {
        $candidateId = $request->user()->id;
        $interviews = $this->interviewService->listForCandidate($candidateId);

        return response()->json([
            'data' => $interviews->map(fn ($interview) => [
                'id' => $interview->id,
                'job_title' => $interview->jobApplication->jobPosting->title ?? null,
                'interview_type' => $interview->interview_type,
                'location' => $interview->location,
                'interviewer_name' => $interview->interviewer->name ?? null,
                'scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'duration_minutes' => $interview->duration_minutes,
            ]),
        ]);
    }

    /**
     * Format an interview model for API response.
     */
    protected function formatInterview(Interview $interview): array
    {
        return [
            'id' => $interview->id,
            'job_application_id' => $interview->job_application_id,
            'interviewer_id' => $interview->interviewer_id,
            'interviewer_name' => $interview->interviewer->name ?? null,
            'interviewer_email' => $interview->interviewer->email ?? null,
            'scheduled_at' => $interview->scheduled_at->toIso8601String(),
            'duration_minutes' => $interview->duration_minutes,
            'location' => $interview->location,
            'interview_type' => $interview->interview_type,
            'status' => $interview->status,
            'notes' => $interview->notes,
            'created_at' => $interview->created_at->toIso8601String(),
            'updated_at' => $interview->updated_at->toIso8601String(),
        ];
    }
}
