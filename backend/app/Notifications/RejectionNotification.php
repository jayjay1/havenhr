<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RejectionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        protected string $jobTitle,
        protected string $companyName,
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
            ->subject("Application Update — {$this->jobTitle}")
            ->markdown('emails.rejection', [
                'candidateName' => $notifiable->name,
                'jobTitle' => $this->jobTitle,
                'companyName' => $this->companyName,
                'preferencesUrl' => config('app.frontend_url') . '/profile/notification-preferences',
            ]);
    }
}
