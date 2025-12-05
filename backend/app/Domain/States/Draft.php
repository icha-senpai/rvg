<?php

namespace App\Domain\Operations\States;

class Draft extends OperationState
{
    public static function name(): string
    {
        return 'draft';
    }

    public function allowedTransitions(): array
    {
        return ['published', 'canceled'];
    }
}
