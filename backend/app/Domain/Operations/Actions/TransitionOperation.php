<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\States\OperationState;
use App\Models\Operation;

class TransitionOperation
{
    public function execute(Operation $operation, string $targetStatus, ?string $reason = null): Operation
    {
        $state = OperationState::from($operation);

        return $state->transitionTo($targetStatus, $reason);
    }
}
