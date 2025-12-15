<?php

namespace App\Domain\AccessControl;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Models\Squadron;
use App\Models\Operation;
use App\Models\SquadronMember;

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

    /**
     * "Can this user see the operations list at all?"
     */
    public function canViewAnyOperation(User $user): bool
    {
        // Director / Tech Director always can
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Anyone with operation.view can see the list
        return $this->can($user, 'operation.view');
    }

    /**
     * View a specific operation.
     */
    public function canViewOperation(User $user, Operation $operation): bool
    {
        // Directors override
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Global view permission
        if ($this->can($user, 'operation.view')) {
            return true;
        }

        // If operation is open → everyone can view
        if ($operation->visibility === 'open') {
            return true;
        }

        // Squadron-limited operations: must belong to that squadron
        if ($operation->squadron_id) {
            return $user->squadronMemberships()
                ->active()
                ->where('squadron_id', $operation->squadron_id)
                ->exists();
        }

        // Default: deny
        return false;
    }

    /**
     * Create an operation, optionally scoped to a squadron.
     */
    public function canCreateOperation(User $user, ?Squadron $squadron = null): bool
    {
        // Directors and Tech Directors override everything
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Global RBAC: any user with create or host permissions
        $hostingPerms = PermissionRegistry::group('operation.hosting');

        if ($this->can($user, 'operation.create')
            || $this->any($user, $hostingPerms)
        ) {
            return true;
        }

        // Squadron-specific creation rules
        if ($squadron) {
            $ctx = $this->context($user);

            // Squadron Leader can always create for their squadron
            if ($ctx->isSquadronLeader($squadron)) {
                return true;
            }

            // Lieutenant can create small squadron ops only
            if ($ctx->isSquadronLieutenant($squadron)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update an existing operation.
     */
    public function canUpdateOperation(User $user, Operation $operation): bool
    {
        // Director override
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Global manage permission
        if ($this->can($user, 'operation.manage')) {
            return true;
        }

        // Creator can edit their own op
        if ($operation->created_by === $user->id) {
            return true;
        }

        // Squadron leader can edit ops for their squadron
        if ($operation->squadron_id) {
            $squadron = Squadron::find($operation->squadron_id);

            if ($squadron && $this->context($user)->isSquadronLeader($squadron)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete an operation: same power as update.
     */
    public function canDeleteOperation(User $user, Operation $operation): bool
    {
        return $this->canUpdateOperation($user, $operation);
    }

    /**
     * Manage members (add/remove participants, move them between slots/roles).
     */
    public function canManageOperationMembers(User $user, Operation $operation): bool
    {
        // Director / Tech Director override
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Global "operation.members.manage" permission
        if ($this->can($user, 'operation.members.manage')) {
            return true;
        }

        // Squadron leader for this squadron can manage members
        if ($operation->squadron_id) {
            $squadron = Squadron::find($operation->squadron_id);

            if ($squadron && $this->context($user)->isSquadronLeader($squadron)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adjust operation stats (post-op analytics, performance, etc).
     */
    public function canAdjustOperationStats(User $user, Operation $operation): bool
    {
        // Director-like
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Either analytics.operation OR full operation.manage
        if ($this->can($user, 'analytics.operation')
            || $this->can($user, 'operation.manage')) {
            return true;
        }

        return false;
    }

    /**
     * High-level "can manage this operation" umbrella.
     * Used for admin UIs / dangerous actions.
     */
    public function canManageOperation(User $user, Operation $operation): bool
    {
        return $this->canUpdateOperation($user, $operation)
            || $this->canManageOperationMembers($user, $operation)
            || $this->canAdjustOperationStats($user, $operation);
    }

    /* ============================================================
     |  SQUADRONS — DDD PERMISSION RULES
     * ============================================================ */

    /**
     * Can the user see squadrons at all?
     */
    public function canViewAnySquadron(User $user): bool
    {
        // Director / Tech Director override
        if ($this->isDirectorLike($user)) {
            return true;
        }

        return $this->can($user, 'squadron.view');
    }

    /**
     * View a specific squadron.
     */
    public function canViewSquadron(User $user, Squadron $squadron): bool
    {
        // Director override
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Global permission
        if ($this->can($user, 'squadron.view')) {
            return true;
        }

        // Active member of the squadron
        return $user->squadronMemberships()
            ->active()
            ->where('squadron_id', $squadron->id)
            ->exists();
    }

    /**
     * Create a new squadron.
     */
    public function canCreateSquadron(User $user): bool
    {
        // Director / Tech Director override
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Explicit create permission
        if ($this->can($user, 'squadron.create')) {
            return true;
        }

        return false;
    }

    /**
     * Update an existing squadron.
     */
    public function canUpdateSquadron(User $user, Squadron $squadron): bool
    {
        // Director override
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Global manage permission
        if ($this->can($user, 'squadron.manage')) {
            return true;
        }

        // Squadron Leader can update their squadron
        if ($this->context($user)->isSquadronLeader($squadron)) {
            return true;
        }

        return false;
    }

    /**
     * Delete a squadron.
     */
    public function canDeleteSquadron(User $user, Squadron $squadron): bool
    {
        // Director override
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Explicit delete permission (dangerous on purpose)
        if ($this->can($user, 'squadron.delete')) {
            return true;
        }

        return false;
    }


    public function canManageSquadronMembers(User $user, Squadron $squadron): bool
    {
        // Directors and Tech Directors override everything
        if ($this->hasRole($user, 'director') || $this->hasRole($user, 'tech_director')) {
            return true;
        }

        // Squadron leader
        if ($squadron->leader_id === $user->id) {
            return true;
        }

        // Lieutenant of this squadron
        return $squadron->members()
            ->where('user_id', $user->id)
            ->where('role', SquadronMember::ROLE_LIEUTENANT)
            ->exists();
    }

    public function canPromoteLieutenant(User $user, Squadron $squadron): bool
    {
    // Directors override everything
    if ($this->hasRole($user, 'director') || $this->hasRole($user, 'tech_director')) {
        return true;
    }

    // Only squadron leader can promote
    if ($squadron->leader_id !== $user->id) {
        return false;
    }

    // Enforce max 2 lieutenants
    $lieutenantCount = $squadron->members()
        ->where('role', SquadronMember::ROLE_LIEUTENANT)
        ->count();

    return $lieutenantCount < 2;
    }

    /**
     * Check if a user can demote a lieutenant.
     */
    public function canDemoteLieutenant(User $user, Squadron $squadron): bool
    {
        // Directors override
        if ($this->hasRole($user, 'director') || $this->hasRole($user, 'tech_director')) {
            return true;
        }

        // Squadron leader only
        return $squadron->leader_id === $user->id;
    }

}
