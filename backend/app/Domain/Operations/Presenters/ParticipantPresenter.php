<?php

namespace App\Domain\Operations\Presenters;

use App\Models\OperationParticipant;

class ParticipantPresenter
{
    protected OperationParticipant $participant;

    public function __construct(OperationParticipant $participant)
    {
        $this->participant = $participant->load(['user', 'role']);
    }

    public static function make(OperationParticipant $participant): self
    {
        return new static($participant);
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->participant->id,
            'user_id'           => $this->participant->user_id,
            'slot'              => $this->participant->slot,
            'operation_role_id' => $this->participant->operation_role_id,
            'attendance_status' => $this->participant->attendance_status,
            'notes'             => $this->participant->notes,
            'stats'             => $this->participant->stats ?? [],

            'user' => [
                'id'            => $this->participant->user->id,
                'name'          => $this->participant->user->name,
                'display_name'  => $this->participant->user->display_name,
                'rank'          => $this->participant->user->rank,
                'rank_level'    => $this->participant->user->rank_level,
            ],

            'role' => $this->participant->role ? [
                'id'                => $this->participant->role->id,
                'role_name'         => $this->participant->role->role_name,
                'role_display_name' => $this->participant->role->role_display_name,
                'capacity'          => $this->participant->role->capacity,
            ] : null,
        ];
    }
}
