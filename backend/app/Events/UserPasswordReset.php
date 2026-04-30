<?php

namespace App\Events;

class UserPasswordReset extends DomainEvent
{
    public string $event_type = 'user.password_reset';
}
