<?php

namespace App\Enums;

/**
 * The threshold events the SLA monitor records (PRD §13.5). Mirrors the
 * notification types dispatched for each crossing.
 */
enum SlaEventType: string
{
    case AtRisk = 'AT_RISK';
    case Breached = 'BREACHED';
    case Met = 'MET';

    public function label(): string
    {
        return match ($this) {
            self::AtRisk => 'At risk',
            self::Breached => 'Breached',
            self::Met => 'Met',
        };
    }
}
