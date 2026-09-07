<?php

namespace App\Enums;

enum OrganizationStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Inactive = 'INACTIVE';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Inactive => 'Inactive',
        };
    }
}
