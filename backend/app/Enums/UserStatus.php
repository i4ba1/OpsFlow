<?php

namespace App\Enums;

enum UserStatus: string
{
    case Invited = 'INVITED';
    case Active = 'ACTIVE';
    case Disabled = 'DISABLED';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Disabled => 'Disabled',
        };
    }
}
