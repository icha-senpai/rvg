<?php

namespace App\Domain\Operations\States;

class Completed extends OperationState
{
    public static function name(): string
    {
        return 'completed';
    }

    public function allowedTransitions(): array
    {
        // Completed is terminal
        return [];
    }
}
