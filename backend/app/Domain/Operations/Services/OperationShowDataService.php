<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Presenters\OperationPresenter;
use App\Models\Operation;

class OperationShowDataService
{
    public function build(Operation $operation, int|string|null $userId): array
    {
        $operation->load([
            'squadron',
            'creator',
            'participants.user',
            'images',
        ]);

        $participants = $operation->participants;

        $participantsBySlot = $participants
            ->whereNotNull('slot')
            ->groupBy('slot');

        $unassignedParticipants = $participants
            ->whereNull('slot')
            ->values();

        $currentParticipant = $userId
            ? $participants->firstWhere('user_id', $userId)
            : null;

        return [
            'operation' => OperationPresenter::make($operation)->full(),
            'participants' => $participants,
            'participantsBySlot' => $participantsBySlot,
            'unassignedParticipants' => $unassignedParticipants,
            'currentParticipant' => $currentParticipant,
        ];
    }
}
