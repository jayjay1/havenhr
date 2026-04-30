<?php

namespace App\Events;

class UserLogout extends DomainEvent
{
    public string $event_type = 'user.logout';
}
