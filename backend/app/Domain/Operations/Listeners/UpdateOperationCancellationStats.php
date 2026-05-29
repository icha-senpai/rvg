<?php

namespace App\Domain\Operations\Listeners;

use App\Domain\Operations\Events\OperationCanceled;
use App\Models\User;

class UpdateOperationCancellationStats
{
    public function handle(OperationCanceled $event): void
    {
        $operation = $event->operation;

        if ($operation->created_by) {
            User::whereKey($operation->created_by)->increment('operations_canceled_count');
        }
    }
}
