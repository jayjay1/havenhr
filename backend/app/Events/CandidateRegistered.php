<?php

namespace App\Events;

class CandidateRegistered extends DomainEvent
{
    public string $event_type = 'candidate.registered';
}
