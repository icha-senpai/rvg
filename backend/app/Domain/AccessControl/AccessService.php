<?php

namespace App\Domain\AccessControl;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Models\Squadron;
use App\Models\Operation;
use App\Models\OperationTemplate;
use App\Models\SquadronMember;

/**
 * Centralizes higher-level authorization rules that combine roles, permissions,
 * and squadron relationships.
 *
 * Policies call into this service when a permission check needs more context
 * than a single permission slug lookup.
 */
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
     * Return the current active membership for the given squadron id.
     */
    public function squadronMembershipForId(User $user, int $squadronId): ?SquadronMember
    {
        return SquadronMember::query()
            ->where('user_id', $user->id)
            ->where('squadron_id', $squadronId)
            ->where('membership_status', SquadronMember::STATUS_ACTIVE)
            ->latest('joined_at')
            ->first();
    }

    /**
     * Return the current active membership for the given squadron.
     */
    public function squadronMembership(User $user, Squadron $squadron): ?SquadronMember
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
        return $this->isDirectorLike($user)
            || $this->isOfficer($user);
    }

    /**
     * Return the squadron ids whose templates are visible to this user.
     */
    public function visibleOperationTemplateSquadronIds(User $user): array
    {
        if ($this->isDirectorLike($user)) {
            return [];
        }

        return $user->squadronMemberships()
            ->active()
            ->pluck('squadron_id')
            ->values()
            ->all();
    }

    /**
     * Determine whether the user can view one operation template.
     */
    public function canViewOperationTemplate(User $user, OperationTemplate $template): bool
    {
        if (! $this->canViewAnyOperationTemplate($user)) {
            return false;
        }

        if ($this->isDirectorLike($user)) {
            return true;
        }

        return match ($template->scope) {
            OperationTemplate::SCOPE_GLOBAL => true,
            OperationTemplate::SCOPE_PERSONAL => (int) $template->owner_user_id === (int) $user->id,
            OperationTemplate::SCOPE_SQUADRON => $template->squadron_id
                ? $this->squadronMembershipForId($user, (int) $template->squadron_id) !== null
                : false,
            default => false,
        };
    }

    /**
     * Determine whether the user can create an operation template with the given scope.
     */
    public function canCreateOperationTemplate(User $user, ?string $scope = null, ?int $squadronId = null): bool
    {
        if (! $this->canViewAnyOperationTemplate($user)) {
            return false;
        }

        if ($scope === null) {
            return true;
        }

        if ($this->isDirectorLike($user)) {
            return true;
        }

        return match ($scope) {
            OperationTemplate::SCOPE_GLOBAL => false,
            OperationTemplate::SCOPE_PERSONAL => true,
            OperationTemplate::SCOPE_SQUADRON => $squadronId
                ? $this->squadronMembershipForId($user, $squadronId) !== null
                : false,
            default => false,
        };
    }

    /**
     * Determine whether the user can update an operation template.
     */
    public function canUpdateOperationTemplate(User $user, OperationTemplate $template): bool
    {
        if ($this->isDirectorLike($user)) {
            return true;
        }

        if (! $this->canViewAnyOperationTemplate($user)) {
            return false;
        }

        return match ($template->scope) {
            OperationTemplate::SCOPE_GLOBAL => false,
            OperationTemplate::SCOPE_PERSONAL => (int) $template->owner_user_id === (int) $user->id,
            OperationTemplate::SCOPE_SQUADRON => $template->squadron_id
                ? $this->canUpdateSquadronTemplate($user, (int) $template->squadron_id, (int) $template->created_by)
                : false,
            default => false,
        };
    }

    /**
     * Delete an operation template with the same rules as update.
     */
    public function canDeleteOperationTemplate(User $user, OperationTemplate $template): bool
    {
        return $this->canUpdateOperationTemplate($user, $template);
    }

    /**
     * Determine whether the user can update a squadron-scoped template.
     */
    protected function canUpdateSquadronTemplate(User $user, int $squadronId, int $createdBy): bool
    {
        $squadron = Squadron::find($squadronId);
        if (! $squadron) {
            return false;
        }

        if ($createdBy === (int) $user->id) {
            return true;
        }

        return $this->isSquadronLeader($user, $squadron)
            || $this->isSquadronLieutenant($user, $squadron);
    }

    /**
     * Determine whether the user can access the operations list at all.
     */
    public function canViewAnyOperation(User $user): bool
    {
        // Director-like roles always bypass the normal operation visibility gate.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Otherwise the explicit operation.view permission is enough.
        return $this->can($user, 'operation.view');
    }

    /**
     * View a specific operation.
     */
    public function canViewOperation(User $user, Operation $operation): bool
    {
        // Director-like roles can always inspect operation records.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // A global operation.view permission also bypasses squadron scoping.
        if ($this->can($user, 'operation.view')) {
            return true;
        }

        // Open operations are intentionally visible to any authenticated user.
        if ($operation->visibility === 'open') {
            return true;
        }

        // Squadron-scoped operations are visible only to active members of that
        // squadron when no broader permission override applies.
        if ($operation->squadron_id) {
            return $this->squadronMembershipForId($user, (int) $operation->squadron_id) !== null;
        }

        // Closed global operations default to deny when no explicit permission or
        // visibility rule grants access.
        return false;
    }

    /**
     * Create an operation, optionally scoped to a squadron.
     */
    public function canCreateOperation(User $user, ?Squadron $squadron = null): bool
    {
        // Director-like roles can always create operations.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Global operations require lieutenant-or-higher role status.
        if (! $squadron) {
            return $this->atLeast($user, 'lieutenant');
        }

        // Squadron operations require lieutenant-or-higher status plus an active
        // membership in the target squadron.
        if ($this->atLeast($user, 'lieutenant')) {
            return $this->isSquadronMember($user, $squadron);
        }

        return false;
    }

    /**
     * Update an existing operation.
     */
    public function canUpdateOperation(User $user, Operation $operation): bool
    {
        // Director-like roles can always update operations.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Global operations may be edited only by their creator when that user is
        // still lieutenant-or-higher.
        if (! $operation->squadron_id) {
            return (int) $operation->created_by === (int) $user->id
                && $this->atLeast($user, 'lieutenant');
        }

        // Squadron operation creators may edit their own record while they still
        // satisfy the same squadron-scoped create rule used for new operations.
        $squadron = Squadron::find($operation->squadron_id);
        if (! $squadron) {
            return false;
        }

        if ((int) $operation->created_by === (int) $user->id
            && $this->canCreateOperation($user, $squadron)
        ) {
            return true;
        }

        // Leaders and lieutenants of the owning squadron can also update the
        // operation even if they were not the original creator.
        if ($this->isSquadronLeader($user, $squadron)) {
            return true;
        }

        if ($this->isSquadronLieutenant($user, $squadron)) {
            return true;
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
        // Director-like roles can always manage operation participation.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // A dedicated permission can also grant access outside squadron leadership.
        if ($this->can($user, 'operation.members.manage')) {
            return true;
        }

        // Otherwise only the leader of the owning squadron can manage members.
        if ($operation->squadron_id) {
            $squadron = Squadron::find($operation->squadron_id);

            if ($squadron && $this->isSquadronLeader($user, $squadron)) {
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
        // Director-like roles can always adjust operation analytics.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Either of the analytics/stat-management permissions is enough.
        if ($this->can($user, 'analytics.operation')
            || $this->can($user, 'operation.stats.manage')) {
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

    /**
     * Determine whether the user can access the squadron list.
     */
    public function canViewAnySquadron(User $user): bool
    {
        // Squadron listing is intentionally visible to any authenticated user.
        return true;
    }

    /**
     * View a specific squadron.
     */
    public function canViewSquadron(User $user, Squadron $squadron): bool
    {
        // Director-like roles can always inspect squadron records.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // A global squadron.view permission also grants visibility.
        if ($this->can($user, 'squadron.view')) {
            return true;
        }

        // Otherwise the user must currently belong to the squadron.
        return $this->isSquadronMember($user, $squadron);
    }

    /**
     * Create a new squadron.
     */
    public function canCreateSquadron(User $user): bool
    {
        // Director-like roles can always create squadrons.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Otherwise an explicit create permission is required.
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
        // Director-like roles can always update squadrons.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // A global squadron.manage permission also grants access.
        if ($this->can($user, 'squadron.manage')) {
            return true;
        }

        // Otherwise only the squadron leader may update the squadron directly.
        if ($this->isSquadronLeader($user, $squadron)) {
            return true;
        }

        return false;
    }

    /**
     * Delete a squadron.
     */
    public function canDeleteSquadron(User $user, Squadron $squadron): bool
    {
        // Director-like roles can always delete squadrons.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Deletion stays intentionally narrow because it is a destructive action.
        if ($this->can($user, 'squadron.delete')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user may manage squadron membership records.
     */
    public function canManageSquadronMembers(User $user, Squadron $squadron): bool
    {
        // Director-like roles can always manage squadron members.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Squadron leaders always manage their own roster.
        if ($this->isSquadronLeader($user, $squadron) || $squadron->leader_id === $user->id) {
            return true;
        }

        // Active lieutenants of the same squadron may also manage members.
        return $squadron->members()
            ->where('user_id', $user->id)
            ->where('role', SquadronMember::ROLE_LIEUTENANT)
            ->where('membership_status', SquadronMember::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Determine whether the user may promote a squadron member to lieutenant.
     */
    public function canPromoteLieutenant(User $user, Squadron $squadron): bool
    {
        // Director-like roles can always override the normal promotion rule.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Outside director-like roles, only the squadron leader may promote.
        if (! $this->isSquadronLeader($user, $squadron) && $squadron->leader_id !== $user->id) {
            return false;
        }

        // Promotions stop once the squadron already has its maximum lieutenant
        // count, matching the membership domain rule.
        $lieutenantCount = $squadron->members()
            ->where('role', SquadronMember::ROLE_LIEUTENANT)
            ->where('membership_status', SquadronMember::STATUS_ACTIVE)
            ->count();

        return $lieutenantCount < 2;
    }

    /**
     * Check if a user can demote a lieutenant.
     */
    public function canDemoteLieutenant(User $user, Squadron $squadron): bool
    {
        // Director-like roles can always demote lieutenants.
        if ($this->isDirectorLike($user)) {
            return true;
        }

        // Otherwise only the current squadron leader may demote lieutenants.
        return $this->isSquadronLeader($user, $squadron) || $squadron->leader_id === $user->id;
    }
}
