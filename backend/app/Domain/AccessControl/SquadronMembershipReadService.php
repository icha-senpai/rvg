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
    /** @var array<string, ?SquadronMember> */
    protected array $activeMemberships = [];

    public function activeMembership(User $user, Squadron $squadron): ?SquadronMember
    {
        return $this->activeMembershipForId($user, $squadron->id);
    }

    public function activeMembershipForId(User $user, int $squadronId): ?SquadronMember
    {
        $cacheKey = $this->membershipCacheKey($user, $squadronId);

        if (! array_key_exists($cacheKey, $this->activeMemberships)) {
            $this->activeMemberships[$cacheKey] = $this->resolveActiveMembership($user, $squadronId);
        }

        return $this->activeMemberships[$cacheKey];
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

    protected function resolveActiveMembership(User $user, int $squadronId): ?SquadronMember
    {
        return SquadronMember::query()
            ->where('user_id', $user->id)
            ->where('squadron_id', $squadronId)
            ->where('membership_status', SquadronMembershipStatus::Active->value)
            ->latest('joined_at')
            ->first();
    }

    protected function membershipCacheKey(User $user, int $squadronId): string
    {
        $userKey = $user->getKey() !== null
            ? 'user:' . $user->getKey()
            : 'object:' . spl_object_id($user);

        return $userKey . '|squadron:' . $squadronId;
    }
}
