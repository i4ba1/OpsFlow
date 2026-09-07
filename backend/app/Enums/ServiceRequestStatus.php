<?php

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case New = 'NEW';
    case Triaged = 'TRIAGED';
    case InProgress = 'IN_PROGRESS';
    case WaitingCustomer = 'WAITING_CUSTOMER';
    case WaitingInternal = 'WAITING_INTERNAL';
    case Escalated = 'ESCALATED';
    case Resolved = 'RESOLVED';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Triaged => 'Triaged',
            self::InProgress => 'In progress',
            self::WaitingCustomer => 'Waiting on customer',
            self::WaitingInternal => 'Waiting on internal team',
            self::Escalated => 'Escalated',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }
}
