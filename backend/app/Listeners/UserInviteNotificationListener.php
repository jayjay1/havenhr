<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\User;
use App\Notifications\UserInviteNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserInviteNotificationListener implements ShouldQueue
{
    /**
     * The number of times the queued listener should be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        $user = User::withoutGlobalScopes()->find($event->user_id);

        if (!$user) {
            return;
        }

        $temporaryPassword = $event->data['password'] ?? null;

        if (!$temporaryPassword) {
            return;
        }

        $loginUrl = config('app.frontend_url', 'http://localhost:3001') . '/login';

        $user->notify(new UserInviteNotification(
            userName: $user->name,
            email: $user->email,
            temporaryPassword: $temporaryPassword,
            loginUrl: $loginUrl,
        ));
    }
}
