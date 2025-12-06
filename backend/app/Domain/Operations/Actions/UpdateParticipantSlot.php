<?php

namespace App\Domain\Operations\Actions;

use App\Models\Operation;
use App\Models\OperationParticipant;

class UpdateParticipantSlot
{
    public function execute(
        Operation $operation,
        OperationParticipant $participant,
        array $data
    ): OperationParticipant {
        $participant->update([
            'operation_role_id' => $data['operation_role_id'] ?? $participant->operation_role_id,
            'slot'              => $data['slot'] ?? $participant->slot,
        ]);

        return $participant->fresh();
    }
}
