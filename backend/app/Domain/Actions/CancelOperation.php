<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;

class CancelOperation
{
    public function execute(Operation $operation, ?string $reason = null): Operation
    {
        $operation->status = 'canceled';

        if ($reason) {
            $operation->cancellation_reason = $reason;
        }

        $operation->save();

        return $operation->fresh();
    }
}
