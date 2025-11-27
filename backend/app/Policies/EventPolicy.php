<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /* ------------------------------
        DIRECTOR OVERRIDE
       ------------------------------*/
    private function isDirector(User $user): bool
    {
        return $user->rank >= 5; // Admiral / Grand Admiral
    }

    /* ------------------------------
        SQUADRON OFFICER CHECK
       ------------------------------*/
    private function isSquadronLeader(User $user, Event $event): bool
    {
        return $user->squadrons()
            ->where('squadrons.id', $event->squadron_id)
            ->wherePivotIn('rank', ['lieutenant', 'commander', 'wing_commander'])
            ->exists();
    }

    /* ------------------------------
        GENERAL VIEW
       ------------------------------*/
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        if ($this->isDirector($user)) return true;

        if ($event->visibility === 'open') return true;

        return $user->squadrons()
            ->where('squadrons.id', $event->squadron_id)
            ->exists();
    }

    /* ------------------------------
        CREATE EVENT
       ------------------------------*/
    public function create(User $user): bool
    {
        return $this->isDirector($user)
            || $user->rank >= 3; // Commander+
    }

    /* ------------------------------
        UPDATE / DELETE / MANAGE
       ------------------------------*/
    public function update(User $user, Event $event): bool
    {
        if ($this->isDirector($user)) return true;

        // Creator OR squadron leadership
        return $user->id === $event->created_by
            || $this->isSquadronLeader($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function manageMembers(User $user, Event $event): bool
    {
        if ($this->isDirector($user)) return true;

        return $this->isSquadronLeader($user, $event);
    }

    public function adjustStats(User $user, Event $event): bool
    {
        return $this->manageMembers($user, $event);
    }

    public function manage(User $user, Event $event): bool
    {
        if ($this->isDirector($user)) return true;

        return $user->id === $event->created_by
            || $this->isSquadronLeader($user, $event);
    }
}
