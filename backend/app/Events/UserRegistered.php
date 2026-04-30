<?php

namespace App\Events;

class UserRegistered extends DomainEvent
{
    public string $event_type = 'user.registered';
}
