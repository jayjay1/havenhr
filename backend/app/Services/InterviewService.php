<?php

namespace App\Services;

use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\InterviewReminderCandidate;
use App\Notifications\InterviewReminderInterviewer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;

class InterviewService
{
    /**
     * Create a new interview for a job application.
     * Validates that the application and interviewer belong to the current tenant.
     */
    public function schedule(array $data, string $tenantId): Interview
    {
        // Verify application belongs to tenant
        $application = JobApplication::whereHas('jobPosting', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->find($data['job_application_id']);

        if (!$application) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Application not found.',
                    ],
                ], 404)
            );
        }

        // Verify interviewer belongs to tenant
        $interviewer = User::where('tenant_id', $tenantId)->find($data['interviewer_id']);

        if (!$interviewer) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Interviewer not found.',
                    ],
                ], 404)
            );
        }

        $interview = Interview::create([
            'job_application_id' => $data['job_application_id'],
            'interviewer_id' => $data['interviewer_id'],
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'],
            'location' => $data['location'],
            'interview_type' => $data['interview_type'],
            'status' => 'scheduled',
            'notes' => $data['notes'] ?? null,
        ]);

        $interview->load('interviewer');

        return $interview;
    }

    /**
     * List all interviews for a specific job application.
     * Ordered by scheduled_at descending.
     */
    public function listForApplication(string $applicationId, string $tenantId): Collection
    {
        // Verify application belongs to tenant
        $application = JobApplication::whereHas('jobPosting', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->find($applicationId);

        if (!$application) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Application not found.',
                    ],
                ], 404)
            );
        }

        return Interview::where('job_application_id', $applicationId)
            ->with('interviewer:id,name,email')
            ->orderBy('scheduled_at', 'desc')
            ->get();
    }

    /**
     * Get a single interview with full details.
     * Includes interviewer, candidate name, job title.
     */
    public function getDetail(string $interviewId, string $tenantId): ?Interview
    {
        return Interview::whereHas('jobApplication', function ($q) use ($tenantId) {
            $q->whereHas('jobPosting', function ($q2) use ($tenantId) {
                $q2->where('tenant_id', $tenantId);
            });
        })
            ->with([
                'interviewer:id,name,email',
                'jobApplication.candidate:id,name',
                'jobApplication.jobPosting:id,title',
            ])
            ->find($interviewId);
    }

    /**
     * Update an existing interview's fields.
     */
    public function update(Interview $interview, array $data): Interview
    {
        $interview->update($data);
        $interview->load('interviewer:id,name,email');

        return $interview;
    }

    /**
     * Cancel an interview (set status to 'cancelled').
     * Returns error if already cancelled.
     */
    public function cancel(Interview $interview): Interview
    {
        if ($interview->status === 'cancelled') {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Interview is already cancelled.',
                    ],
                ], 422)
            );
        }

        $interview->update(['status' => 'cancelled']);
        $interview->load('interviewer:id,name,email');

        return $interview;
    }

    /**
     * Get upcoming interviews for the tenant (next 7 days, max 10).
     */
    public function getUpcoming(string $tenantId, int $limit = 10): Collection
    {
        return Interview::whereHas('jobApplication', function ($q) use ($tenantId) {
            $q->whereHas('jobPosting', function ($q2) use ($tenantId) {
                $q2->where('tenant_id', $tenantId);
            });
        })
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addDays(7))
            ->with([
                'jobApplication.candidate:id,name',
                'jobApplication.jobPosting:id,title',
            ])
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get interviews for a specific candidate's applications.
     * Used by candidate-facing endpoint.
     */
    public function listForCandidate(string $candidateId): Collection
    {
        return Interview::whereHas('jobApplication', function ($q) use ($candidateId) {
            $q->where('candidate_id', $candidateId);
        })
            ->with([
                'interviewer:id,name',
                'jobApplication.jobPosting:id,title',
            ])
            ->orderBy('scheduled_at', 'asc')
            ->get();
    }

    /**
     * Find and send reminders for interviews due within the current window.
     * Called by the scheduled command.
     */
    public function sendDueReminders(): void
    {
        $this->sendCandidateReminders();
        $this->sendInterviewerReminders();
    }

    /**
     * Send candidate reminders for interviews ~24 hours away.
     */
    protected function sendCandidateReminders(): void
    {
        $windowStart = now()->addHours(23)->addMinutes(45);
        $windowEnd = now()->addHours(24)->addMinutes(15);

        $interviews = Interview::where('status', 'scheduled')
            ->whereNull('candidate_reminder_sent_at')
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->with([
                'jobApplication.candidate',
                'jobApplication.jobPosting:id,title',
            ])
            ->get();

        foreach ($interviews as $interview) {
            $candidate = $interview->jobApplication->candidate ?? null;
            if (!$candidate) {
                continue;
            }

            // Check candidate notification preferences
            $preferences = $candidate->notification_preferences ?? [];
            if (($preferences['interview_reminders'] ?? true) === false) {
                continue;
            }

            $jobTitle = $interview->jobApplication->jobPosting->title ?? 'Interview';

            // Set sent_at before dispatching to prevent duplicates
            $interview->update(['candidate_reminder_sent_at' => now()]);

            $candidate->notify(new InterviewReminderCandidate(
                $jobTitle,
                $interview->scheduled_at,
                $interview->interview_type,
                $interview->location,
            ));
        }
    }

    /**
     * Send interviewer reminders for interviews ~1 hour away.
     */
    protected function sendInterviewerReminders(): void
    {
        $windowStart = now()->addMinutes(45);
        $windowEnd = now()->addHour()->addMinutes(15);

        $interviews = Interview::where('status', 'scheduled')
            ->whereNull('interviewer_reminder_sent_at')
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->with([
                'interviewer',
                'jobApplication.candidate:id,name',
            ])
            ->get();

        foreach ($interviews as $interview) {
            $interviewer = $interview->interviewer ?? null;
            if (!$interviewer) {
                continue;
            }

            $candidateName = $interview->jobApplication->candidate->name ?? 'Candidate';

            // Set sent_at before dispatching to prevent duplicates
            $interview->update(['interviewer_reminder_sent_at' => now()]);

            $interviewer->notify(new InterviewReminderInterviewer(
                $candidateName,
                $interview->scheduled_at,
                $interview->interview_type,
                $interview->location,
            ));
        }
    }
}
