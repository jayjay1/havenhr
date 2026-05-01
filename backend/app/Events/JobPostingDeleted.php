<?php

namespace App\Events;

class JobPostingDeleted extends DomainEvent
{
    public string $event_type = 'job_posting.deleted';
}
