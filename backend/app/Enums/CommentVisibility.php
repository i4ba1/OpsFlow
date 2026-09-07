<?php

namespace App\Enums;

/**
 * Comment visibility (PRD §14.2). PUBLIC comments are visible to request
 * participants including customer users; INTERNAL comments are restricted to
 * staff holding the comment.internal permission.
 */
enum CommentVisibility: string
{
    case Public = 'PUBLIC';
    case Internal = 'INTERNAL';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Internal => 'Internal',
        };
    }
}
