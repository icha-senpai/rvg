<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\States\OperationState;
use App\Models\Operation;
use App\Models\User;
use App\Domain\Operations\Events\OperationPublished;
use Illuminate\Support\Facades\Log;

class TransitionOperation
{
    public function execute(Operation $operation, string $targetStatus, ?string $reason = null, ?string $outcome = null): Operation
    {
        Log::info("⚙️ TransitionOperation START", [
            'id' => $operation->id,
            'current_status' => $operation->status,
            'target_status' => $targetStatus,
        ]);

        $previousStatus = $operation->status;

        $state = OperationState::from($operation);
        $updatedOperation = $state->transitionTo($targetStatus, $reason, $outcome);

        Log::info("⚙️ Transition AFTER transitionTo()", [
            'before' => $previousStatus,
            'after' => $updatedOperation->status,
            'operation_same_instance' => $updatedOperation->is($operation),
        ]);

        // EVENT CHECK
        if ($targetStatus === 'published' && $previousStatus !== 'published') {
            Log::info("🔥 DISPATCHING OperationPublished", [
                'id' => $updatedOperation->id,
                'status' => $updatedOperation->status,
            ]);

            OperationPublished::dispatch($updatedOperation);
        } else {
            Log::warning("🙅 NO DISPATCH — Conditions not met", [
                'previous' => $previousStatus,
                'target' => $targetStatus,
                'final' => $updatedOperation->status,
            ]);
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