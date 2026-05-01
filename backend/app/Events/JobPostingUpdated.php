<?php

namespace App\Events;

class JobPostingUpdated extends DomainEvent
{
    public string $event_type = 'job_posting.updated';
}
