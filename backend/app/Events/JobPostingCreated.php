<?php

namespace App\Events;

class JobPostingCreated extends DomainEvent
{
    public string $event_type = 'job_posting.created';
}
