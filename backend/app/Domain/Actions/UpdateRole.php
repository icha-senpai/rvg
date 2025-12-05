<?php

namespace App\Domain\Operations\Actions;

use App\Models\OperationRole;

class UpdateRole
{
    public function execute(OperationRole $role, array $data): OperationRole
    {
        $role->update($data);

        return $role->fresh();
    }
}
