<?php

namespace App\Events;

class TenantCreated extends DomainEvent
{
    public string $event_type = 'tenant.created';
}
