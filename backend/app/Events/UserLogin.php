<?php

namespace App\Events;

class UserLogin extends DomainEvent
{
    public string $event_type = 'user.login';
}
