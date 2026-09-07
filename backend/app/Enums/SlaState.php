<?php

namespace App\Enums;

/**
 * SLA state of a service request, derived by the SlaStateEvaluator (PRD §13.4).
 */
enum SlaState: string
{
    case Healthy = 'HEALTHY';
    case AtRisk = 'AT_RISK';
    case Breached = 'BREACHED';
    case Met = 'MET';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'On track',
            self::AtRisk => 'At risk',
            self::Breached => 'Breached',
            self::Met => 'Met',
        };
    }
}
