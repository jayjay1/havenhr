<?php

namespace App\Events;

class CandidateLogin extends DomainEvent
{
    public string $event_type = 'candidate.login';
}
