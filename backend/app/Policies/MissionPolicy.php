<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    private function directorOverride(User $user): bool
    {
        return $user->hasRole('director');
    }

    /* ------------------------------
       VIEW
       ------------------------------*/

    public function viewAny(User $user): bool
    {
        // Every authenticated member can see mission listings
        return true;
    }

    public function view(User $user, Mission $mission): bool
    {
        return $this->viewAny($user);
    }

    /* ------------------------------
       CREATE
       ------------------------------*/

    public function create(User $user): bool
    {
        return $user->hasPermission('mission.create');
    }

    /* ------------------------------
       UPDATE / DELETE
       ------------------------------*/

    public function update(User $user, Mission $mission): bool
    {
        // Director override
        if ($this->directorOverride($user)) {
            return true;
        }

        // Creator override
        if ($mission->created_by === $user->id) {
            return true;
        }

        // Requires manage-level permission
        return $user->hasPermission('mission.manage');
    }

    public function delete(User $user, Mission $mission): bool
    {
        return $this->update($user, $mission);
    }

    /* ------------------------------
       MEMBER MANAGEMENT / STATS
       ------------------------------*/

    public function manageMembers(User $user, Mission $mission): bool
    {
        // Director override
        if ($this->directorOverride($user)) {
            return true;
        }

        // Creator override
        if ($mission->created_by === $user->id) {
            return true;
        }

        // Mission management includes member control in your model
        return $user->hasPermission('mission.manage');
    }

    public function adjustStats(User $user, Mission $mission): bool
    {
        return $this->manageMembers($user, $mission);
    }
}
