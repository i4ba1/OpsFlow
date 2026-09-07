<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case OnHold = 'ON_HOLD';
    case Completed = 'COMPLETED';
    case Archived = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::OnHold => 'On hold',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }
}
