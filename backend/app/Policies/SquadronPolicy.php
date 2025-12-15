<?php

namespace App\Policies;

use App\Domain\AccessControl\AccessService;
use App\Models\User;
use App\Models\Squadron;

class SquadronPolicy
{
    public function __construct(
        protected AccessService $access
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canViewAnySquadron($user);
    }

    public function view(User $user, Squadron $squadron): bool
    {
        return $this->access->canViewSquadron($user, $squadron);
    }

    public function create(User $user): bool
    {
        return $this->access->canCreateSquadron($user);
    }

    public function update(User $user, Squadron $squadron): bool
    {
        return $this->access->canUpdateSquadron($user, $squadron);
    }

    public function delete(User $user, Squadron $squadron): bool
    {
        return $this->access->canDeleteSquadron($user, $squadron);
    }

    public function manageMembers(User $user, Squadron $squadron): bool
    {
        return $this->access->canManageSquadronMembers($user, $squadron);
    }

    public function promoteLieutenant(User $user, Squadron $squadron): bool
    {
        return $this->access->canPromoteLieutenant($user, $squadron);
    }

    public function demoteLieutenant(User $user, Squadron $squadron): bool
    {
        return $this->access->canDemoteLieutenant($user, $squadron);
    }
}
