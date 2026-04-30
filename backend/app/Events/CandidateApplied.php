<?php

namespace App\Events;

class CandidateApplied extends DomainEvent
{
    public string $event_type = 'candidate.applied';
}
