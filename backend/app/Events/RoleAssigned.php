<?php

namespace App\Events;

class RoleAssigned extends DomainEvent
{
    public string $event_type = 'role.assigned';
}
