<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\User;

class CancelOperation
{
    public function execute(Operation $operation, ?string $reason = null): Operation
    {
        $wasCanceled = $operation->status === 'canceled';

        $operation->status = 'canceled';

        if ($reason) {
            $operation->cancellation_reason = $reason;
        }

        $operation->save();

        if (! $wasCanceled && $operation->created_by) {
            User::whereKey($operation->created_by)->increment('operations_canceled_count');
        }

        return $operation->fresh();
    }
}
