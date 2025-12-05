<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;

class UpdateOperation
{
    public function execute(Operation $operation, array $data): Operation
    {
        $operation->update($data);

        return $operation->fresh();
    }
}
