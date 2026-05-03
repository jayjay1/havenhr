<?php

namespace App\Listeners;

use App\Events\ApplicationStageChanged;
use App\Models\JobApplication;
use App\Models\PipelineStage;
use App\Notifications\RejectionNotification;
use App\Notifications\StageChangeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class StageChangeNotificationListener implements ShouldQueue
{
    /**
     * The number of times the queued listener should be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(ApplicationStageChanged $event): void
    {
        if (empty($event->data['notification_eligible'])) {
            return;
        }

        $application = JobApplication::with(['candidate', 'jobPosting.company'])
            ->find($event->data['application_id']);

        if (!$application || !$application->candidate) {
            return;
        }

        $candidate = $application->candidate;
        $preferences = $candidate->notification_preferences ?? [];

        if (($preferences['stage_change_emails'] ?? true) === false) {
            return;
        }

        $targetStage = PipelineStage::find($event->data['to_stage']);
        $jobTitle = $application->jobPosting->title ?? 'Unknown Position';
        $companyName = $application->jobPosting->company->name ?? 'HavenHR';

        if ($targetStage && $targetStage->name === 'Rejected') {
            $candidate->notify(new RejectionNotification($jobTitle, $companyName));
        } else {
            $stageName = $targetStage->name ?? 'Next Stage';
            $candidate->notify(new StageChangeNotification($jobTitle, $stageName, $companyName));
        }
    }
}
