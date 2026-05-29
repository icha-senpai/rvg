<?php

namespace App\Domain\Operations\States;

use App\Domain\Operations\Enums\OperationStatus;

class InProgress extends OperationState
{
    public static function name(): OperationStatus
    {
        return OperationStatus::InProgress;
    }

    public function allowedTransitions(): array
    {
        return [
            OperationStatus::Completed->value,
            OperationStatus::Canceled->value,
        ];
    }
}
