<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\OperationRole;

class CreateRole
{
    public function execute(Operation $operation, array $data): OperationRole
    {
        return $operation->roles()->create($data);
    }
}
