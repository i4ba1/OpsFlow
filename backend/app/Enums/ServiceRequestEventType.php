<?php

namespace App\Enums;

enum ServiceRequestEventType: string
{
    case StatusChanged = 'STATUS_CHANGED';
    case Assigned = 'ASSIGNED';
    case PriorityChanged = 'PRIORITY_CHANGED';
    case ApprovalRequested = 'APPROVAL_REQUESTED';
    case ApprovalResponded = 'APPROVAL_RESPONDED';

    public function label(): string
    {
        return match ($this) {
            self::StatusChanged => 'Status changed',
            self::Assigned => 'Assigned',
            self::PriorityChanged => 'Priority changed',
            self::ApprovalRequested => 'Approval requested',
            self::ApprovalResponded => 'Approval responded',
        };
    }
}
