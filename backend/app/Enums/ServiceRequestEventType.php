<?php

namespace App\Enums;

enum ServiceRequestEventType: string
{
    case StatusChanged = 'STATUS_CHANGED';
    case Assigned = 'ASSIGNED';
    case PriorityChanged = 'PRIORITY_CHANGED';

    public function label(): string
    {
        return match ($this) {
            self::StatusChanged => 'Status changed',
            self::Assigned => 'Assigned',
            self::PriorityChanged => 'Priority changed',
        };
    }
}
