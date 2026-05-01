<?php

namespace App\Events;

class JobPostingStatusChanged extends DomainEvent
{
    public string $event_type = 'job_posting.status_changed';
}
