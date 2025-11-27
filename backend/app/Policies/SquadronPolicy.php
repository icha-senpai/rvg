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
        return $user->hasRole('director') || $user->hasRole('tech_director');
    }

    /**
     * Check if this user is the squadron commander
     * AND the squadron matches their own assigned squadron.
     */
    private function isSquadronCommanderOf(User $user, Squadron $squadron): bool
    {
        // User must have the commander_squadron role
        if (! $user->hasRole('commander_squadron')) {
            return false;
        }

        // User must have an active squadron membership record
        $member = $user->squadron;
        if (! $member) {
            return false;
        }

        // They must match the squadron they’re trying to manage
        return intval($member->squadron_id) === intval($squadron->id);
    }

    /* ------------------------------
       VIEW
       ------------------------------*/

    public function viewAny(User $user): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        return $user->hasPermission('squadron.view');
    }

    public function view(User $user, Squadron $squadron): bool
    {
        if ($this->directorOverride($user)) {
            return true;
        }

        return $user->hasPermission('squadron.view');
    }

    /* ------------------------------
       CREATE (Director / TechDirector ONLY)
       ------------------------------*/

    public function create(User $user): bool
    {
        return $this->directorOverride($user);
    }

    /* ------------------------------
       UPDATE
       ------------------------------*/

    public function update(User $user, Squadron $squadron): bool
    {
        // Directors & Tech Director always allowed
        if ($this->directorOverride($user)) {
            return true;
        }

        // Squadron Commander can update THEIR squadron
        if ($this->isSquadronCommanderOf($user, $squadron)) {
            return true;
        }

        // Wing Commander, Admiral, Grand Admiral can update ALL squadrons
        if ($user->hasPermission('squadron.manage')) {
            return true;
        }

        return false;
    }

    /* ------------------------------
       DELETE (Director only)
       ------------------------------*/

    public function delete(User $user, Squadron $squadron): bool
    {
        // Only Director or Tech Director can delete squadrons
        return $this->directorOverride($user);
    }

    /* ------------------------------
       MANAGE MEMBERS
       ------------------------------*/

    public function manageMembers(User $user, Squadron $squadron): bool
    {
        // Directors & Tech Director override
        if ($this->directorOverride($user)) {
            return true;
        }

        // Squadron Commander can manage THEIR squadron
        if ($this->isSquadronCommanderOf($user, $squadron)) {
            return true;
        }

        // Wing Commander, Admiral, Grand Admiral can manage members everywhere
        if ($user->hasPermission('squadron.members.manage')) {
            return true;
        }

        return false;
    }
}
