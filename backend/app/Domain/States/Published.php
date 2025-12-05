<?php

namespace App\Domain\Operations\States;

class Published extends OperationState
{
    public static function name(): string
    {
        return 'published';
    }

    public function allowedTransitions(): array
    {
        return ['in_progress', 'canceled'];
    }
}
