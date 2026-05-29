<?php

namespace App\Domain\Operations\States;

use App\Domain\Operations\Enums\OperationStatus;

class Draft extends OperationState
{
    public static function name(): OperationStatus
    {
        return OperationStatus::Draft;
    }

    public function allowedTransitions(): array
    {
        return [
            OperationStatus::Published->value,
            OperationStatus::Canceled->value,
        ];
    }
}
