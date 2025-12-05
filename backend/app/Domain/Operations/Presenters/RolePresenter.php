<?php

namespace App\Domain\Operations\Presenters;

use App\Models\OperationRole;

class RolePresenter
{
    protected OperationRole $role;

    public function __construct(OperationRole $role)
    {
        $this->role = $role->load([
            'participants.user',
        ]);
    }

    public static function make(OperationRole $role): self
    {
        return new static($role);
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->role->id,
            'operation_id'      => $this->role->operation_id,
            'role_name'         => $this->role->role_name,
            'role_display_name' => $this->role->role_display_name,
            'capacity'          => $this->role->capacity,
            'min_required'      => $this->role->min_required,
            'description'       => $this->role->description,
            'requirements'      => $this->role->requirements ?? [],

            // Full participant list for this role
            'participants' => ParticipantListPresenter::make(
                $this->role->participants
            )->toArray(),
        ];
    }
}
