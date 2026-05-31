<?php

namespace App\Application\Operations;

use App\Application\Operations\Presenters\OperationPresenter;
use App\Domain\AccessControl\AccessService;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Collection;

class OperationShowDataService
{
    public function __construct(
        protected AccessService $access
    ) {}

    public function build(Operation $operation, ?User $viewer = null): array
    {
        $operation->load([
            'squadron',
            'creator',
            'participants.user',
            'images',
        ]);

        $canViewSlots = $viewer ? $this->access->canViewOperationSlots($viewer, $operation) : false;
        $canAssignSlots = $viewer ? $this->access->canAssignOperationSlots($viewer, $operation) : false;

        $participants = $this->participantsPayload($operation, $canViewSlots);
        $currentParticipant = null;

        if ($viewer) {
            $currentParticipantModel = $operation->participants->firstWhere('user_id', $viewer->id);

            if ($currentParticipantModel) {
                $currentParticipant = $this->participantPayload($currentParticipantModel, true);
            }
        }

        $operationPayload = OperationPresenter::make($operation)->full();
        $operationPayload['participants'] = $participants->values()->all();
        $operationPayload['slots'] = $canViewSlots ? ($operationPayload['slots'] ?? []) : [];
        $operationPayload['permissions'] = [
            'can_view_slots' => $canViewSlots,
            'can_assign_slots' => $canAssignSlots,
        ];

        return [
            'operation' => $operationPayload,
            'participants' => $participants->values()->all(),
            'participantsBySlot' => $canViewSlots
                ? $participants->filter(fn (array $participant) => filled($participant['slot']))->groupBy('slot')->all()
                : [],
            'unassignedParticipants' => $canViewSlots
                ? $participants->filter(fn (array $participant) => blank($participant['slot']))->values()->all()
                : [],
            'currentParticipant' => $currentParticipant,
        ];
    }

    public function editor(Operation $operation): array
    {
        return [
            'mission' => OperationPresenter::make($operation)->form(),
            'squadronId' => $operation->squadron_id,
        ];
    }

    protected function participantsPayload(Operation $operation, bool $canViewSlots): Collection
    {
        return $operation->participants
            ->map(fn ($participant) => $this->participantPayload($participant, $canViewSlots))
            ->values();
    }

    protected function participantPayload($participant, bool $includeSlot): array
    {
        return [
            'id' => $participant->id,
            'slot' => $includeSlot ? $participant->slot : null,
            'attendance_status' => $participant->attendance_status,
            'notes' => $participant->notes,
            'user' => [
                'id' => $participant->user?->id,
                'rsi_handle' => $participant->user?->rsi_handle,
                'display_name' => $participant->user?->display_name,
                'name' => $participant->user?->name,
                'discord_avatar' => $participant->user?->discord_avatar,
                'avatar' => $participant->user?->avatar,
            ],
        ];
    }
}
