<?php

namespace App\Domain\AccessControl;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Models\Squadron;
use App\Models\Operation;

class AccessService
{
    /**
     * Build a UserContext wrapper.
     */
    public function context(User $user): UserContext
    {
        return UserContext::for($user);
    }

    /**
     * Basic permission check.
     *
     * Example:
     *   $access->can($user, 'operation.host.small');
     */
    public function can(User $user, string $permission, ?Model $context = null): bool
    {
        $ctx = $this->context($user);

        // Future place for contextual checks (per squadron, per company, etc)
        return $ctx->hasPermission($permission);
    }

    /**
     * Check if user has any of given permissions.
     */
    public function any(User $user, array $permissions, ?Model $context = null): bool
    {
        foreach ($permissions as $perm) {
            if ($this->can($user, $perm, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check role by slug (uses UserContext).
     */
    public function hasRole(User $user, string $role): bool
    {
        return $this->context($user)->hasRole($role);
    }

    public function hasAnyRole(User $user, array $roles): bool
    {
        return $this->context($user)->hasAnyRole($roles);
    }

    /**
     * Director / Tech Director global override.
     */
    public function isDirectorLike(User $user): bool
    {
        return $this->context($user)->isDirectorLike();
    }
    /* ============================================================
     |  OPERATIONS — DDD PERMISSION RULES
     * ============================================================
     */

    public function canCreateOperation(User $user, ?Squadron $squadron = null): bool
    {
        // Directors and Tech Directors override everything
        if ($this->hasRole($user, 'director') || $this->hasRole($user, 'tech_director')) {
            return true;
        }

        // Global RBAC: any user with host or create permissions
        if ($this->can($user, 'operation.create')
            || $this->can($user, 'operation.host.small')
            || $this->can($user, 'operation.host.medium')
            || $this->can($user, 'operation.host.large')
            || $this->can($user, 'operation.host.org')) {
            return true;
        }

        // Squadron-specific creation rules
        if ($squadron) {
            // Squadron Leader can always create for their squadron
            if ($user->isSquadronLeader($squadron)) {
                return true;
            }

            // Lieutenant can create small squadron ops only
            if ($user->isSquadronLieutenant($squadron)) {
                return true;
            }
        }

        return false;
    }

    public function canViewOperation(User $user, Operation $operation): bool
    {
        // Directors override
        if ($this->hasRole($user, 'director') || $this->hasRole($user, 'tech_director')) {
            return true;
        }

        // If operation is open → everyone can view
        if ($operation->visibility === 'open') {
            return true;
        }

        // Squadron-limited operations
        if ($operation->squadron_id) {
            return $user->squadronMemberships()
                ->active()
                ->where('squadron_id', $operation->squadron_id)
                ->exists();
        }

        // Default view allowed
        return true;
    }

    public function canUpdateOperation(User $user, Operation $operation): bool
    {
        // Director override
        if ($this->hasRole($user, 'director') || $this->hasRole($user, 'tech_director')) {
            return true;
        }

        // Creator can edit their own op
        if ($operation->created_by === $user->id) {
            return true;
        }

        // Squadron leader can edit ops for their squadron
        if ($operation->squadron_id) {
            $squadron = Squadron::find($operation->squadron_id);

            if ($squadron && $user->isSquadronLeader($squadron)) {
                return true;
            }
        }

        // Global manage permission
        return $this->can($user, 'operation.manage')
            || $this->can($user, 'operation.members.manage');
    }

    public function canDeleteOperation(User $user, Operation $operation): bool
    {
        return $this->canUpdateOperation($user, $operation);
    }
}
