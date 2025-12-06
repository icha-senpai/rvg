<?php

namespace App\Domain\Operations\Services;

use App\Models\Operation;
use App\Models\OperationRole;

// NEW ACTION IMPORTS
use App\Domain\Operations\Actions\{
    CreateRole,
    UpdateRole,
    DeleteRole
};

class OperationRoleService
{
    /**
     * Create a role for an operation.
     */
    public function create(Operation $operation, array $data): OperationRole
    {
        return (new CreateRole)->execute($operation, $data);
    }

    /**
     * Update a role.
     */
    public function update(OperationRole $role, array $data): OperationRole
    {
        return (new UpdateRole)->execute($role, $data);
    }

    /**
     * Delete a role (only if allowed).
     */
    public function delete(OperationRole $role): void
    {
        (new DeleteRole)->execute($role);
    }

    /**
     * Load relationships for UI/JSON hydration.
     */
    public function loadGraph(OperationRole $role): OperationRole
    {
        return $role->load([
            'operation',
            'participants.user',
        ]);
    }

    /**
     * Ensure a role belongs to a specific operation.
     */
    public function ensureRoleBelongsToOperation(Operation $operation, OperationRole $role): void
    {
        if ($role->operation_id !== $operation->id) {
            throw new \Illuminate\Validation\ValidationException(
                validator: null,
                errors: ['role' => 'This role does not belong to this operation.']
            );
        }
    }
}
