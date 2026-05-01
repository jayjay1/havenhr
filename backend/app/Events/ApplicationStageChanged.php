<?php

namespace App\Events;

class ApplicationStageChanged extends DomainEvent
{
    public string $event_type = 'application.stage_changed';
}
