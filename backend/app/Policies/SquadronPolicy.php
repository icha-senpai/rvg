<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Squadron;

class SquadronPolicy
{
    /**
     * Director / Tech Director override.
     */
    private function directorOverride(User $user): bool
    {
        return $user->hasRole('director') || $user->hasRole('tech_director');
    }

    /**
     * Check if this user is the squadron leader (per-squadron role).
     */
    private function isSquadronLeaderOf(User $user, Squadron $squadron): bool
    {
        return $user->isSquadronLeader($squadron);
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
        // Only Director or Tech Director can create squadrons
        return $user->hasRole('director') || $user->hasRole('tech_director');
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

        // Squadron Leader can update THEIR squadron
        if ($this->isSquadronLeaderOf($user, $squadron)) {
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

        // Squadron Leader can manage THEIR squadron
        if ($this->isSquadronLeaderOf($user, $squadron)) {
            return true;
        }

        // Wing Commander, Admiral, Grand Admiral can manage members everywhere
        if ($user->hasPermission('squadron.members.manage')) {
            return true;
        }

         // Lieutenant can manage their own squadron ONLY
        if ($user->isSquadronLieutenant($squadron)) {
            return true;
        }

        return false;
    }

    public function promoteLieutenant(User $user, Squadron $squadron): bool
    {
        // Directors / Tech Directors can always do it
        if ($this->directorOverride($user)) {
            return true;
        }

        // Only the squadron leader can promote lieutenants
        if ($user->isSquadronLeader($squadron)) {
            return true;
        }

        return false;
    }


}
