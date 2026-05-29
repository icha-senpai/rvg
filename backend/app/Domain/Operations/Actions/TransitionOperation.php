<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\OperationStatus;
use App\Domain\Operations\Events\OperationCanceled;
use App\Domain\Operations\Events\OperationCompleted;
use App\Domain\Operations\States\OperationState;
use App\Domain\Operations\Events\OperationPublished;
use App\Models\Operation;

class TransitionOperation
{
    public function execute(Operation $operation, string $targetStatus, ?string $reason = null, ?string $outcome = null): Operation
    {
        $previousStatus = $operation->status;

        $state = OperationState::from($operation);
        $updatedOperation = $state->transitionTo($targetStatus, $reason, $outcome);

        if ($targetStatus === OperationStatus::Published->value && $previousStatus !== OperationStatus::Published->value) {
            OperationPublished::dispatch($updatedOperation);
        }

        if ($targetStatus === OperationStatus::Completed->value && $previousStatus !== OperationStatus::Completed->value) {
            OperationCompleted::dispatch($updatedOperation);
        }

        if ($targetStatus === OperationStatus::Canceled->value && $previousStatus !== OperationStatus::Canceled->value) {
            OperationCanceled::dispatch($updatedOperation);
        }

        return $updatedOperation;
    }
}
