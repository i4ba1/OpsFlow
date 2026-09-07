<?php

namespace App\Support\Notifications;

/**
 * In-app notification type constants (PRD §18). Stored verbatim on the
 * notifications.type column; the values are the canonical wire names used by
 * the notification resource and the realtime payloads.
 */
final class NotificationType
{
    public const REQUEST_CREATED = 'REQUEST_CREATED';

    public const REQUEST_ASSIGNED = 'REQUEST_ASSIGNED';

    public const REQUEST_PRIORITY_CHANGED = 'REQUEST_PRIORITY_CHANGED';

    public const REQUEST_STATUS_CHANGED = 'REQUEST_STATUS_CHANGED';

    public const REQUEST_COMMENT_ADDED = 'REQUEST_COMMENT_ADDED';

    public const APPROVAL_REQUESTED = 'APPROVAL_REQUESTED';

    public const APPROVAL_RESPONDED = 'APPROVAL_RESPONDED';

    public const SLA_AT_RISK = 'SLA_AT_RISK';

    public const SLA_BREACHED = 'SLA_BREACHED';

    public const REQUEST_RESOLVED = 'REQUEST_RESOLVED';

    public const INVOICE_ISSUED = 'INVOICE_ISSUED';

    public const INVOICE_OVERDUE = 'INVOICE_OVERDUE';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::REQUEST_CREATED,
            self::REQUEST_ASSIGNED,
            self::REQUEST_PRIORITY_CHANGED,
            self::REQUEST_STATUS_CHANGED,
            self::REQUEST_COMMENT_ADDED,
            self::APPROVAL_REQUESTED,
            self::APPROVAL_RESPONDED,
            self::SLA_AT_RISK,
            self::SLA_BREACHED,
            self::REQUEST_RESOLVED,
            self::INVOICE_ISSUED,
            self::INVOICE_OVERDUE,
        ];
    }
}
