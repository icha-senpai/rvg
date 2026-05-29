<?php

namespace App\Domain\Squadrons\Enums;

enum SquadronMembershipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Banned = 'banned';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
