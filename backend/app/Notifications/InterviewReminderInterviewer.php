<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterviewReminderInterviewer extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        protected string $candidateName,
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
            ->subject("Interview in 1 Hour — {$this->candidateName}")
            ->markdown('emails.interview-reminder-interviewer', [
                'interviewerName' => $notifiable->name,
                'candidateName' => $this->candidateName,
                'scheduledAt' => $this->scheduledAt->format('l, F j, Y \a\t g:i A'),
                'interviewType' => $this->interviewType,
                'location' => $this->location,
            ]);
    }
}
