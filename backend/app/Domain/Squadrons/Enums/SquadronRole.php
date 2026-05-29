<?php

namespace App\Domain\Squadrons\Enums;

enum SquadronRole: string
{
    case Member = 'member';
    case Leader = 'leader';
    case Lieutenant = 'lieutenant';

    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases()
        );
    }
}
