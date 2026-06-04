<?php

namespace App\Domain\AccessControl;

use App\Domain\Squadrons\MembershipRuleService;
use App\Models\Squadron;
use App\Models\User;

class SquadronAccessService
{
    public function __construct(
        protected SharedAccessService $shared,
        protected MembershipRuleService $membershipRules,
        protected SquadronMembershipReadService $memberships
    ) {}

    public function canViewAnySquadron(User $user): bool
    {
        return true;
    }

    public function canViewSquadron(User $user, Squadron $squadron): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if ($this->shared->can($user, 'squadron.view')) {
            return true;
        }

        return $this->shared->isSquadronMember($user, $squadron);
    }

    public function canCreateSquadron(User $user): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        return $this->shared->can($user, 'squadron.create');
    }

    public function canUpdateSquadron(User $user, Squadron $squadron): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if ($this->shared->can($user, 'squadron.manage')) {
            return true;
        }

        return $this->shared->isSquadronLeader($user, $squadron);
    }

    public function canDeleteSquadron(User $user, Squadron $squadron): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        return $this->shared->can($user, 'squadron.delete');
    }

    public function canManageSquadronMembers(User $user, Squadron $squadron): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if ($this->shared->isSquadronLeader($user, $squadron) || $squadron->leader_id === $user->id) {
            return true;
        }

        return $this->memberships->isLieutenant($user, $squadron);
    }

    public function canPromoteLieutenant(User $user, Squadron $squadron): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if (! $this->shared->isSquadronLeader($user, $squadron) && $squadron->leader_id !== $user->id) {
            return false;
        }

        return $this->membershipRules->hasLieutenantCapacity($squadron);
    }

    public function canDemoteLieutenant(User $user, Squadron $squadron): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        return $this->shared->isSquadronLeader($user, $squadron) || $squadron->leader_id === $user->id;
    }

    public function canViewLedger(User $user, Squadron $squadron): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if ($this->shared->can($user, 'ledger.manage-squadron-ledger')) {
            return true;
        }

        return $this->shared->isSquadronMember($user, $squadron);
    }

    public function canManageLedger(User $user, Squadron $squadron): bool
    {
        if ($this->shared->isDirectorLike($user)) {
            return true;
        }

        if ($this->shared->can($user, 'ledger.manage-squadron-ledger')) {
            return true;
        }

        return $this->canManageSquadronMembers($user, $squadron);
    }
}
