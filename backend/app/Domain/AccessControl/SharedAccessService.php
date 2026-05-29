<?php

namespace App\Domain\AccessControl;

use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SharedAccessService
{
    public function __construct(
        protected SquadronMembershipReadService $memberships
    ) {}

    public function context(User $user): UserContext
    {
        return UserContext::for($user);
    }

    public function can(User $user, string $permission, ?Model $context = null): bool
    {
        return $this->context($user)->hasPermission($permission);
    }

    public function any(User $user, array $permissions, ?Model $context = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($user, $permission, $context)) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(User $user, string $role): bool
    {
        return $this->context($user)->hasRole($role);
    }

    public function hasAnyRole(User $user, array $roles): bool
    {
        return $this->context($user)->hasAnyRole($roles);
    }

    public function isDirectorLike(User $user): bool
    {
        return $this->context($user)->isDirectorLike();
    }

    public function atLeast(User $user, string $role): bool
    {
        $user->loadMissing('roles:id,slug');

        return RoleHierarchy::userAtLeast($user, $role);
    }

    public function squadronMembershipForId(User $user, int $squadronId): ?SquadronMember
    {
        return $this->memberships->activeMembershipForId($user, $squadronId);
    }

    public function squadronMembership(User $user, Squadron $squadron): ?SquadronMember
    {
        return $this->memberships->activeMembership($user, $squadron);
    }

    public function isSquadronMember(User $user, Squadron $squadron): bool
    {
        return $this->squadronMembership($user, $squadron) !== null;
    }

    public function isSquadronLeader(User $user, Squadron $squadron): bool
    {
        return $this->memberships->isLeader($user, $squadron);
    }

    public function isSquadronLieutenant(User $user, Squadron $squadron): bool
    {
        return $this->memberships->isLieutenant($user, $squadron);
    }

    public function isOfficer(User $user): bool
    {
        return $this->atLeast($user, 'lieutenant');
    }
}
