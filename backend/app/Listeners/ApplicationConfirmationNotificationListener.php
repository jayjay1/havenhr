<?php

namespace App\Listeners;

use App\Events\CandidateApplied;
use App\Models\JobApplication;
use App\Notifications\ApplicationConfirmationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ApplicationConfirmationNotificationListener implements ShouldQueue
{
    /**
     * The number of times the queued listener should be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(CandidateApplied $event): void
    {
        $application = JobApplication::with(['candidate', 'jobPosting.company'])
            ->find($event->data['application_id']);

        if (!$application || !$application->candidate) {
            return;
        }

        $candidate = $application->candidate;
        $preferences = $candidate->notification_preferences ?? [];

        if (($preferences['application_confirmation_emails'] ?? true) === false) {
            return;
        }

        $jobTitle = $application->jobPosting->title ?? 'Unknown Position';
        $companyName = $application->jobPosting->company->name ?? 'HavenHR';

        $candidate->notify(new ApplicationConfirmationNotification($jobTitle, $companyName));
    }
}
