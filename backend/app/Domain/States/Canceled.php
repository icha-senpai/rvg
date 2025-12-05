<?php

namespace App\Domain\Operations\States;

class Canceled extends OperationState
{
    public static function name(): string
    {
        return 'canceled';
    }

    public function allowedTransitions(): array
    {
        // Canceled is terminal
        return [];
    }
}
