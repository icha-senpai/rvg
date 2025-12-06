<?php

namespace App\Domain\Operations\Services;

use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\User;

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
        return (new UpdateParticipantSlot)->execute($operation, $participant, $data);
    }

    /**
     * Update participant stats.
     */
    public function updateStats(OperationParticipant $participant, array $stats): OperationParticipant
    {
        return (new UpdateParticipantStats)->execute($participant, $stats);
    }
}
