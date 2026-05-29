<?php

namespace App\Domain\Operations\Enums;

enum CompletionOutcome: string
{
    case Success = 'success';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_map(
            static fn (self $outcome): string => $outcome->value,
            self::cases()
        );
    }
}
