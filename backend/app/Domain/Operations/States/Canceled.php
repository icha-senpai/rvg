<?php

namespace App\Domain\Operations\States;

use App\Domain\Operations\Enums\OperationStatus;

class Canceled extends OperationState
{
    public static function name(): OperationStatus
    {
        return OperationStatus::Canceled;
    }

    public function allowedTransitions(): array
    {
        // Canceled is terminal
        return [];
    }
}
