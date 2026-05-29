<?php

namespace App\Domain\AccessControl;

use App\Domain\Squadrons\Enums\SquadronMembershipStatus;
use App\Domain\Squadrons\Enums\SquadronRole;
use App\Models\Operation;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;

class SquadronMembershipReadService
{
    public function activeMembership(User $user, Squadron $squadron): ?SquadronMember
    {
        return SquadronMember::query()
            ->where('user_id', $user->id)
            ->where('squadron_id', $squadron->id)
            ->where('membership_status', SquadronMembershipStatus::Active->value)
            ->latest('joined_at')
            ->first();
    }

    public function activeMembershipForId(User $user, int $squadronId): ?SquadronMember
    {
        return SquadronMember::query()
            ->where('user_id', $user->id)
            ->where('squadron_id', $squadronId)
            ->where('membership_status', SquadronMembershipStatus::Active->value)
            ->latest('joined_at')
            ->first();
    }

    public function isLeader(User $user, Squadron $squadron): bool
    {
        return $this->activeMembership($user, $squadron)?->role === SquadronRole::Leader->value;
    }

    public function isLieutenant(User $user, Squadron $squadron): bool
    {
        return $this->activeMembership($user, $squadron)?->role === SquadronRole::Lieutenant->value;
    }

    public function squadronForOperation(Operation $operation): ?Squadron
    {
        if (! $operation->squadron_id) {
            return null;
        }

        return Squadron::find($operation->squadron_id);
    }
}
