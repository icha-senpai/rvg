<?php

namespace App\Domain\Operations\Services;

use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

// NEW ACTION IMPORTS
use App\Domain\Operations\Actions\{
    JoinOperation,
    LeaveOperation,
    UpdateParticipantSlot,
    UpdateParticipantStats
};

class ParticipantService
{
    /**
     * Join an operation (delegated to Action).
     */
    public function join(Operation $operation, User $user, array $data): OperationParticipant
    {
        $data = $this->normalizePayload($data);
        $role = $this->resolveRole($operation, $data['operation_role_id'] ?? null);
        $this->assertRoleCapacity($role);

        return (new JoinOperation)->execute($operation, $user, $data);
    }

    /**
     * Leave an operation.
     */
    public function leave(Operation $operation, User $user): void
    {
        (new LeaveOperation)->execute($operation, $user);
    }

    /**
     * Update a participant's slot and/or role.
     */
    public function updateSlot(Operation $operation, OperationParticipant $participant, array $data): OperationParticipant
    {
        $data = $this->normalizePayload($data);
        $role = $this->resolveRole($operation, $data['operation_role_id'] ?? null);
        $this->assertRoleCapacity($role, $participant);

        return (new UpdateParticipantSlot)->execute($operation, $participant, $data);
    }

    /**
     * Update participant stats.
     */
    public function updateStats(OperationParticipant $participant, array $stats): OperationParticipant
    {
        return (new UpdateParticipantStats)->execute($participant, $stats);
    }

    protected function normalizePayload(array $data): array
    {
        if (array_key_exists('slot', $data) && $data['slot'] === '') {
            $data['slot'] = null;
        }

        return $data;
    }

    protected function resolveRole(Operation $operation, mixed $roleId): ?OperationRole
    {
        if (! $roleId) {
            return null;
        }

        $role = OperationRole::findOrFail($roleId);

        if ($role->operation_id !== $operation->id) {
            throw ValidationException::withMessages([
                'operation_role_id' => 'Invalid role for this operation',
            ]);
        }

        return $role;
    }

    protected function assertRoleCapacity(?OperationRole $role, ?OperationParticipant $participant = null): void
    {
        if (! $role || $role->capacity === null) {
            return;
        }

        $filled = $role->participants()
            ->when($participant, fn ($query) => $query->where('id', '!=', $participant->id))
            ->count();

        if ($filled >= $role->capacity) {
            throw ValidationException::withMessages([
                'operation_role_id' => 'Role is full',
            ]);
        }
    }
}
