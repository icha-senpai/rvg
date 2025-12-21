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
        $payload = [];

        if (array_key_exists('operation_role_id', $data)) {
            $payload['operation_role_id'] = $data['operation_role_id'];
        }

        if (array_key_exists('slot', $data)) {
            $payload['slot'] = $data['slot'];
        }

        if ($payload !== []) {
            $participant->update($payload);
        }

        return $participant->fresh();
    }
}
