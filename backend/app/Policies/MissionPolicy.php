<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

class MissionPolicy
{
    private function directorOverride(User $user)
    {
        return $user->isDirector();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('mission.view');
    }

    public function view(User $user, Mission $mission): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('mission.create');
    }

    public function update(User $user, Mission $mission): bool
    {
        if ($this->directorOverride($user)) return true;

        return $mission->created_by === $user->id
            || $user->hasPermission('mission.manage');
    }

    public function delete(User $user, Mission $mission): bool
    {
        return $this->update($user, $mission);
    }

    public function manageMembers(User $user, Mission $mission): bool
    {
        if ($this->directorOverride($user)) return true;

        return $mission->created_by === $user->id
            || $user->hasPermission('mission.members.manage');
    }

    public function adjustStats(User $user, Mission $mission): bool
    {
        return $this->manageMembers($user, $mission);
    }
}
