<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Domain\Operations\Events\OperationUpdated;

class UpdateOperation
{
    public function execute(Operation $operation, array $data): Operation
    {
        $operation->update($data);

        if ($operation->wasChanged() && $operation->status === 'published') {
            OperationUpdated::dispatch($operation);
        }

        return $operation->fresh();
    }
}
