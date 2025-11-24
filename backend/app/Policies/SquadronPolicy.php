<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Squadron;

class SquadronPolicy
{
    /**
     * Director override.
     */
    private function directorOverride(User $user): bool
    {
        return $user->isDirector();
    }

    /**
     * Can view list of squadrons.
     */
    public function viewAny(User $user): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        return $user->hasPermission('squadron.view');
    }

    /**
     * Can view a single squadron.
     */
    public function view(User $user, Squadron $squadron): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        return $user->hasPermission('squadron.view');
    }

    /**
     * Create a new squadron (org leadership only).
     */
    public function create(User $user): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        return $user->hasPermission('squadron.manage');
    }

    /**
     * Update squadron metadata.
     */
    public function update(User $user, Squadron $squadron): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        return $user->hasPermission('squadron.manage');
    }

    /**
     * Delete a squadron.
     */
    public function delete(User $user, Squadron $squadron): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        return $user->hasPermission('squadron.manage');
    }

    /**
     * Manage squadron members (add/remove).
     */
    public function manageMembers(User $user, Squadron $squadron): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        return $user->hasPermission('squadron.members.manage');
    }
}
