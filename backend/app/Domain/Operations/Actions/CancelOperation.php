<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelOperation
{
    public function execute(Operation $operation, ?string $reason = null): Operation
    {
        $wasCanceled = $operation->status === 'canceled';
        $normalizedReason = trim((string) $reason);

        if ($normalizedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Cancellation reason is required.',
            ]);
        }

        $operation->status = 'canceled';
        $operation->cancellation_reason = $normalizedReason;

        $operation->save();

        if (! $wasCanceled && $operation->created_by) {
            User::whereKey($operation->created_by)->increment('operations_canceled_count');
        }

        return $operation->fresh();
    }
}
