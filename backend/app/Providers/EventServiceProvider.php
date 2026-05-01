<?php

namespace App\Providers;

use App\Events\ApplicationStageChanged;
use App\Events\JobPostingCreated;
use App\Events\JobPostingDeleted;
use App\Events\JobPostingStatusChanged;
use App\Events\JobPostingUpdated;
use App\Events\RoleAssigned;
use App\Events\RoleChanged;
use App\Events\TenantCreated;
use App\Events\UserLogin;
use App\Events\UserLoginFailed;
use App\Events\UserLogout;
use App\Events\UserPasswordReset;
use App\Events\UserRegistered;
use App\Listeners\AuditLogListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TenantCreated::class => [
            AuditLogListener::class,
        ],
        UserRegistered::class => [
            AuditLogListener::class,
        ],
        UserLogin::class => [
            AuditLogListener::class,
        ],
        UserLoginFailed::class => [
            AuditLogListener::class,
        ],
        UserLogout::class => [
            AuditLogListener::class,
        ],
        UserPasswordReset::class => [
            AuditLogListener::class,
        ],
        RoleAssigned::class => [
            AuditLogListener::class,
        ],
        RoleChanged::class => [
            AuditLogListener::class,
        ],
        JobPostingCreated::class => [
            AuditLogListener::class,
        ],
        JobPostingUpdated::class => [
            AuditLogListener::class,
        ],
        JobPostingStatusChanged::class => [
            AuditLogListener::class,
        ],
        JobPostingDeleted::class => [
            AuditLogListener::class,
        ],
        ApplicationStageChanged::class => [
            AuditLogListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
