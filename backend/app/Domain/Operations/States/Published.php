<?php

namespace App\Domain\Operations\States;

use App\Domain\Operations\Enums\OperationStatus;

class Published extends OperationState
{
    public static function name(): OperationStatus
    {
        return OperationStatus::Published;
    }

    public function allowedTransitions(): array
    {
        return [
            OperationStatus::InProgress->value,
            OperationStatus::Canceled->value,
        ];
    }
}
