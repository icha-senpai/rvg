<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\States\OperationState;
use App\Models\Operation;
use App\Models\User;
use App\Domain\Operations\Events\OperationPublished;

class TransitionOperation
{
    public function execute(Operation $operation, string $targetStatus, ?string $reason = null, ?string $outcome = null): Operation
    {
        $previousStatus = $operation->status;

        $state = OperationState::from($operation);
        $updatedOperation = $state->transitionTo($targetStatus, $reason, $outcome);

        // EVENT CHECK
        if ($targetStatus === 'published' && $previousStatus !== 'published') {
            OperationPublished::dispatch($updatedOperation);
        }

        if ($targetStatus === 'completed' && $previousStatus !== 'completed') {
            $participantUserIds = $updatedOperation->participants()
                ->distinct()
                ->pluck('user_id')
                ->all();

            if (! empty($participantUserIds)) {
                User::whereIn('id', $participantUserIds)->increment('operations_completed_count');
            }

            if ($updatedOperation->created_by) {
                if ($updatedOperation->completion_outcome === 'success') {
                    User::whereKey($updatedOperation->created_by)->increment('operations_success_count');
                }

                if ($updatedOperation->completion_outcome === 'failed') {
                    User::whereKey($updatedOperation->created_by)->increment('operations_failed_count');
                }
            }
        }

        if ($targetStatus === 'canceled' && $previousStatus !== 'canceled') {
            if ($updatedOperation->created_by) {
                User::whereKey($updatedOperation->created_by)->increment('operations_canceled_count');
            }
        }

        return $updatedOperation;
    }
}