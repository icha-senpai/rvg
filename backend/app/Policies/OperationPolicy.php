<?php

namespace App\Policies;

use App\Models\Operation;
use App\Models\User;

class OperationPolicy
{
    private function directorOverride(User $user): bool
    {
        return $user->hasRole('director');
    }

    /* VIEW LIST */
    public function viewAny(User $user): bool
    {
        // Same as both: any authenticated member can see ops list
        return true;
    }

    /* VIEW SINGLE */
    public function view(User $user, Operation $operation): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        // Open ops visible to all
        if ($operation->visibility === 'open') {
            return true;
        }

        // Squadron-limited: must match squadron if present
        if ($operation->squadron_id && $user->squadron?->squadron_id === $operation->squadron_id) {
            return true;
        }

        // Otherwise, default to "can see" if they're invited or in same org.
        // (You can tighten this later as needed.)
        return true;
    }

    /* CREATE */
    public function create(User $user): bool
    {
        // For now, allow either permission:
        return $user->hasPermission('event.create')
            || $user->hasPermission('mission.create')
            || $user->hasPermission('operation.create');
    }

    /* UPDATE / DELETE */
    public function update(User $user, Operation $operation): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        if ($operation->created_by === $user->id) {
            return true;
        }

        // Allow either legacy permission set to work:
        return $user->hasPermission('event.manage')
            || $user->hasPermission('mission.manage')
            || $user->hasPermission('operation.manage');
    }

    public function delete(User $user, Operation $operation): bool
    {
        return $this->update($user, $operation);
    }

    /* MANAGE MEMBERS / STATS */
    public function manageMembers(User $user, Operation $operation): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        if ($operation->created_by === $user->id) {
            return true;
        }

        return $user->hasPermission('event.manage')
            || $user->hasPermission('mission.manage')
            || $user->hasPermission('operation.manage');
    }

    public function adjustStats(User $user, Operation $operation): bool
    {
        return $this->manageMembers($user, $operation);
    }

    /* MANAGE (status, visibility etc) */
    public function manage(User $user, Operation $operation): bool
    {
        return $this->update($user, $operation);
    }
}
