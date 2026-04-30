<?php

namespace App\Events;

class UserLoginFailed extends DomainEvent
{
    public string $event_type = 'user.login_failed';
}
