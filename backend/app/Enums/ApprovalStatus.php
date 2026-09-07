<?php

namespace App\Enums;

/**
 * Approval lifecycle (PRD §16). PENDING approvals can be approved, rejected,
 * cancelled or — once past their due date — expired by the
 * ExpirePendingApprovals job. A decision (APPROVED / REJECTED) is immutable;
 * the only way to revisit it is an explicit reset/reissue that creates a new
 * PENDING approval.
 */
enum ApprovalStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }
}
