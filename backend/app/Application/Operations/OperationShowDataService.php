<?php

namespace App\Application\Operations;

use App\Application\Operations\Presenters\OperationPresenter;
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

        return [
            'operation' => OperationPresenter::make($operation)->full(),
            'participants' => $participants,
            'participantsBySlot' => $participants
                ->whereNotNull('slot')
                ->groupBy('slot'),
            'unassignedParticipants' => $participants
                ->whereNull('slot')
                ->values(),
            'currentParticipant' => $userId
                ? $participants->firstWhere('user_id', $userId)
                : null,
        ];
    }

    public function editor(Operation $operation): array
    {
        return [
            'mission' => OperationPresenter::make($operation)->form(),
            'squadronId' => $operation->squadron_id,
        ];
    }
}
