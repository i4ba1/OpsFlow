<?php

namespace App\Enums;

/**
 * The kind of decision an approval captures (PRD §16): a customer acceptance
 * sign-off (e.g. UAT), an approval to send/commit to a quotation, approval of
 * a scope change request, or approval of an exception to policy.
 */
enum ApprovalType: string
{
    case Acceptance = 'ACCEPTANCE';
    case Quotation = 'QUOTATION';
    case ChangeRequest = 'CHANGE_REQUEST';
    case Exception = 'EXCEPTION';

    public function label(): string
    {
        return match ($this) {
            self::Acceptance => 'Acceptance',
            self::Quotation => 'Quotation',
            self::ChangeRequest => 'Change request',
            self::Exception => 'Exception',
        };
    }
}
