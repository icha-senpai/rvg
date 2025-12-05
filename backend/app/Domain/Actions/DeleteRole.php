<?php

namespace App\Domain\Operations\Actions;

use App\Models\OperationRole;
use Illuminate\Validation\ValidationException;

class DeleteRole
{
    public function execute(OperationRole $role): void
    {
        if ($role->participants()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Cannot delete a role that still has participants.',
            ]);
        }

        $role->delete();
    }
}
