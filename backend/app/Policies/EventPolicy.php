<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /* ------------------------------
       VIEW
       ------------------------------*/

    public function viewAny(User $user): bool
    {
        // Any authenticated member can see the event list
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        // Directors see everything
        if ($user->hasRole('director')) {
            return true;
        }

        // Open events visible to all
        if ($event->visibility === 'open') {
            return true;
        }

        // Otherwise must match squadron
        return $user->squadron?->squadron_id === $event->squadron_id;
    }

    /* ------------------------------
       CREATE
       ------------------------------*/

    public function create(User $user): bool
    {
        return $user->hasPermission('event.create');
    }

    /* ------------------------------
       UPDATE / DELETE
       ------------------------------*/

    public function update(User $user, Event $event): bool
    {
        // Directors override
        if ($user->hasRole('director')) {
            return true;
        }

        // Creator override — can always fix their own event
        if ($user->id === $event->created_by) {
            return true;
        }

        // Otherwise need event.manage permission
        return $user->hasPermission('event.manage');
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    /* ------------------------------
       MEMBER MANAGEMENT / STATS
       ------------------------------*/

    public function manageMembers(User $user, Event $event): bool
    {
        // Directors override
        if ($user->hasRole('director')) {
            return true;
        }

        // Creator override
        if ($user->id === $event->created_by) {
            return true;
        }

        return $user->hasPermission('event.manage');
    }

    public function adjustStats(User $user, Event $event): bool
    {
        return $this->manageMembers($user, $event);
    }

    /* ------------------------------
       HOSTING (tiered)
       ------------------------------*/

    public function hostSmall(User $user): bool
    {
        return $user->hasPermission('event.host.small');
    }

    public function hostMedium(User $user): bool
    {
        return $user->hasPermission('event.host.medium');
    }

    public function hostLarge(User $user): bool
    {
        return $user->hasPermission('event.host.large');
    }

    public function hostOrg(User $user): bool
    {
        return $user->hasPermission('event.host.org');
    }

    /* ------------------------------
       MANAGE (status, visibility, etc.)
       ------------------------------*/

    public function manage(User $user, Event $event): bool
    {
        // Directors override
        if ($user->hasRole('director')) {
            return true;
        }

        // Creator override
        if ($user->id === $event->created_by) {
            return true;
        }

        return $user->hasPermission('event.manage');
    }
}
