<?php

namespace App\Domain\AccessControl;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Models\Squadron;
use App\Models\Operation;
use App\Models\OperationTemplate;

/**
 * Centralizes higher-level authorization rules that combine roles, permissions,
 * and squadron relationships.
 *
 * Policies call into this service when a permission check needs more context
 * than a single permission slug lookup.
 */
class AccessService
{
    protected function templates(): OperationTemplateAccessService
    {
        return app(OperationTemplateAccessService::class);
    }

    protected function operations(): OperationAccessService
    {
        return app(OperationAccessService::class);
    }

    protected function squadrons(): SquadronAccessService
    {
        return app(SquadronAccessService::class);
    }

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

        // This is the shared hook for future contextual permission rules, such as
        // squadron-scoped or company-scoped permission variants.
        return $ctx->hasPermission($permission);
    }

    /**
     * Check whether the user has any permission from the provided list.
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
     * Check whether the user has the given role slug.
     */
    public function hasRole(User $user, string $role): bool
    {
        return $this->context($user)->hasRole($role);
    }

    /**
     * Check whether the user has any role from the provided list.
     */
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

    /**
     * Check whether the user meets or exceeds the given role threshold.
     */
    public function atLeast(User $user, string $role): bool
    {
        $user->loadMissing('roles:id,slug');

        return RoleHierarchy::userAtLeast($user, $role);
    }

    /**
     * Return the current active membership for the given squadron.
     */
    public function squadronMembership(User $user, Squadron $squadron)
    {
        return $this->squadronMembershipForId($user, $squadron->id);
    }

    /**
     * Check whether the user is an active member of the given squadron.
     */
    public function isSquadronMember(User $user, Squadron $squadron): bool
    {
        return $this->squadronMembership($user, $squadron) !== null;
    }

    /**
     * Check whether the user is the active leader of the given squadron.
     */
    public function isSquadronLeader(User $user, Squadron $squadron): bool
    {
        return $this->context($user)->isSquadronLeader($squadron);
    }

    /**
     * Check whether the user is an active lieutenant of the given squadron.
     */
    public function isSquadronLieutenant(User $user, Squadron $squadron): bool
    {
        return $this->context($user)->isSquadronLieutenant($squadron);
    }

    /**
     * Check whether the user is lieutenant-or-higher.
     */
    public function isOfficer(User $user): bool
    {
        return $this->atLeast($user, 'lieutenant');
    }

    /**
     * Determine whether the user can access operation templates at all.
     */
    public function canViewAnyOperationTemplate(User $user): bool
    {
        return $this->templates()->canViewAnyOperationTemplate($user);
    }

    /**
     * Return the squadron ids whose templates are visible to this user.
     */
    public function visibleOperationTemplateSquadronIds(User $user): array
    {
        return $this->templates()->visibleOperationTemplateSquadronIds($user);
    }

    /**
     * Determine whether the user can view one operation template.
     */
    public function canViewOperationTemplate(User $user, OperationTemplate $template): bool
    {
        return $this->templates()->canViewOperationTemplate($user, $template);
    }

    /**
     * Determine whether the user can create an operation template with the given scope.
     */
    public function canCreateOperationTemplate(User $user, ?string $scope = null, ?int $squadronId = null): bool
    {
        return $this->templates()->canCreateOperationTemplate($user, $scope, $squadronId);
    }

    /**
     * Determine whether the user can update an operation template.
     */
    public function canUpdateOperationTemplate(User $user, OperationTemplate $template): bool
    {
        return $this->templates()->canUpdateOperationTemplate($user, $template);
    }

    /**
     * Delete an operation template with the same rules as update.
     */
    public function canDeleteOperationTemplate(User $user, OperationTemplate $template): bool
    {
        return $this->templates()->canDeleteOperationTemplate($user, $template);
    }

    /**
     * Determine whether the user can access the operations list at all.
     */
    public function canViewAnyOperation(User $user): bool
    {
        return $this->operations()->canViewAnyOperation($user);
    }

    /**
     * View a specific operation.
     */
    public function canViewOperation(User $user, Operation $operation): bool
    {
        return $this->operations()->canViewOperation($user, $operation);
    }

    /**
     * Create an operation, optionally scoped to a squadron.
     */
    public function canCreateOperation(User $user, ?Squadron $squadron = null): bool
    {
        return $this->operations()->canCreateOperation($user, $squadron);
    }

    /**
     * Update an existing operation.
     */
    public function canUpdateOperation(User $user, Operation $operation): bool
    {
        return $this->operations()->canUpdateOperation($user, $operation);
    }

    /**
     * Delete an operation: same power as update.
     */
    public function canDeleteOperation(User $user, Operation $operation): bool
    {
        return $this->operations()->canDeleteOperation($user, $operation);
    }

    /**
     * Manage members (add/remove participants, move them between slots/roles).
     */
    public function canManageOperationMembers(User $user, Operation $operation): bool
    {
        return $this->operations()->canManageOperationMembers($user, $operation);
    }

    /**
     * Adjust operation stats (post-op analytics, performance, etc).
     */
    public function canAdjustOperationStats(User $user, Operation $operation): bool
    {
        return $this->operations()->canAdjustOperationStats($user, $operation);
    }

    /**
     * High-level "can manage this operation" umbrella.
     * Used for admin UIs / dangerous actions.
     */
    public function canManageOperation(User $user, Operation $operation): bool
    {
        return $this->operations()->canManageOperation($user, $operation);
    }

    /**
     * Determine whether the user can access the squadron list.
     */
    public function canViewAnySquadron(User $user): bool
    {
        return $this->squadrons()->canViewAnySquadron($user);
    }

    /**
     * View a specific squadron.
     */
    public function canViewSquadron(User $user, Squadron $squadron): bool
    {
        return $this->squadrons()->canViewSquadron($user, $squadron);
    }

    /**
     * Create a new squadron.
     */
    public function canCreateSquadron(User $user): bool
    {
        return $this->squadrons()->canCreateSquadron($user);
    }

    /**
     * Update an existing squadron.
     */
    public function canUpdateSquadron(User $user, Squadron $squadron): bool
    {
        return $this->squadrons()->canUpdateSquadron($user, $squadron);
    }

    /**
     * Delete a squadron.
     */
    public function canDeleteSquadron(User $user, Squadron $squadron): bool
    {
        return $this->squadrons()->canDeleteSquadron($user, $squadron);
    }

    /**
     * Determine whether the user may manage squadron membership records.
     */
    public function canManageSquadronMembers(User $user, Squadron $squadron): bool
    {
        return $this->squadrons()->canManageSquadronMembers($user, $squadron);
    }

    /**
     * Determine whether the user may promote a squadron member to lieutenant.
     */
    public function canPromoteLieutenant(User $user, Squadron $squadron): bool
    {
        return $this->squadrons()->canPromoteLieutenant($user, $squadron);
    }

    /**
     * Check if a user can demote a lieutenant.
     */
    public function canDemoteLieutenant(User $user, Squadron $squadron): bool
    {
        return $this->squadrons()->canDemoteLieutenant($user, $squadron);
    }
}
