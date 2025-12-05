<?php

namespace App\Domain\Operations\Actions;

use App\Models\OperationParticipant;

class UpdateParticipantStats
{
    public function execute(OperationParticipant $participant, array $stats): OperationParticipant
    {
        $participant->update(['stats' => $stats]);

        return $participant->fresh();
    }
}
