<?php

namespace App\Domain\Squadrons\Enums;

enum SquadronStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Disbanded = 'disbanded';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
