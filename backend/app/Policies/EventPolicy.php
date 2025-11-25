<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    private function directorOverride(User $user)
    {
        return $user->isDirector()
            ? Response::allow()
            : Response::deny('Not authorized.');
    }

    private function squadronLeadership(User $user, Event $event)
    {
        return $user->hasPermission('event.manage') 
            && $user->squadron_id === $event->squadron_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('event.view');
    }

    public function view(User $user, Event $event): bool
    {
        if ($this->directorOverride($user)) return true;

        if ($event->visibility === 'open') return true;

        return $user->squadron_id === $event->squadron_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('event.create');
    }

    public function update(User $user, Event $event): bool
    {
        if ($this->directorOverride($user)) return true;

        return $this->squadronLeadership($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        if ($this->directorOverride($user)) return true;

        return $this->squadronLeadership($user, $event);
    }

    public function manageMembers(User $user, Event $event): bool
    {
        if ($this->directorOverride($user)) return true;

        return $this->squadronLeadership($user, $event);
    }

    public function adjustStats(User $user, Event $event): bool
    {
        return $this->manageMembers($user, $event);
    }
}
