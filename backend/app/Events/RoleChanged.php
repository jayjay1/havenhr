<?php

namespace App\Events;

class RoleChanged extends DomainEvent
{
    public string $event_type = 'role.changed';
}
