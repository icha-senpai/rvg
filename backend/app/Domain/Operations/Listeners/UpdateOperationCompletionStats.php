<?php

namespace App\Domain\Operations\Listeners;

use App\Domain\Operations\Enums\CompletionOutcome;
use App\Domain\Operations\Events\OperationCompleted;
use App\Models\User;

class UpdateOperationCompletionStats
{
    public function handle(OperationCompleted $event): void
    {
        $operation = $event->operation;

        if (! $operation->created_by || ! $operation->completion_outcome) {
            return;
        }

        if ($operation->completion_outcome === CompletionOutcome::Success->value) {
            User::whereKey($operation->created_by)->increment('operations_success_count');
        }

        if ($operation->completion_outcome === CompletionOutcome::Failed->value) {
            User::whereKey($operation->created_by)->increment('operations_failed_count');
        }
    }
}
