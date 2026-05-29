<?php

namespace App\Domain\Operations\Enums;

enum OperationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }

    public static function creatableValues(): array
    {
        return [
            self::Draft->value,
            self::Published->value,
        ];
    }

    public static function transitionableValues(): array
    {
        return [
            self::Published->value,
            self::InProgress->value,
            self::Completed->value,
            self::Canceled->value,
        ];
    }
}
