<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterviewReminderCandidate extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        protected string $jobTitle,
        protected Carbon $scheduledAt,
        protected string $interviewType,
        protected string $location,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Interview Reminder — {$this->jobTitle}")
            ->markdown('emails.interview-reminder-candidate', [
                'candidateName' => $notifiable->name,
                'jobTitle' => $this->jobTitle,
                'scheduledAt' => $this->scheduledAt->format('l, F j, Y \a\t g:i A'),
                'interviewType' => $this->interviewType,
                'location' => $this->location,
            ]);
    }
}
