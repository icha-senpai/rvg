<?php

namespace App\Domain\Operations\States;

class InProgress extends OperationState
{
    public static function name(): string
    {
        return 'in_progress';
    }

    public function allowedTransitions(): array
    {
            return ['completed', 'canceled'];
    }
}
