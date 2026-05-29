<?php

namespace App\Domain\Operations\States;

use App\Domain\Operations\Enums\OperationStatus;

class Completed extends OperationState
{
    public static function name(): OperationStatus
    {
        return OperationStatus::Completed;
    }

    public function allowedTransitions(): array
    {
        // Completed is terminal
        return [];
    }
}
