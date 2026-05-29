<?php

namespace App\Domain\Squadrons;

use App\Domain\AccessControl\Events\RoleAssigned;
use App\Domain\AccessControl\Events\RoleRevoked;
use App\Domain\Squadrons\Enums\SquadronMembershipStatus;
use App\Domain\Squadrons\Enums\SquadronRole;
use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Handles the extra side effects that happen when squadron leadership-adjacent
 * roles change.
 *
 * Promotions and demotions do more than change one membership column: they may
 * also update user roles, displayed rank, and cached access data.
 */
class MembershipPromotionService
{
    public function __construct(
        protected MembershipRuleService $rules
    ) {}

    /**
     * Promote an existing squadron member to lieutenant.
     *
     * This enforces the squadron-wide lieutenant cap before changing membership
     * state or syncing broader user access metadata.
     */
    public function promoteLieutenant(Squadron $squadron, User $user): SquadronMember
    {
        $member = SquadronMember::where('user_id', $user->id)
            ->where('squadron_id', $squadron->id)
            ->firstOrFail();

        $this->rules->assertLieutenantCapacity($squadron);

        $member->update([
            'role' => SquadronRole::Lieutenant->value,
            'membership_status' => SquadronMembershipStatus::Active->value,
        ]);

        // Sync the reusable application role so permission checks outside the
        // squadron domain also see this promotion.
        $lieutenantRoleId = Role::where('slug', 'lieutenant')->value('id');
        if ($lieutenantRoleId) {
            $user->roles()->syncWithoutDetaching([$lieutenantRoleId]);
            $role = Role::find($lieutenantRoleId);
            if ($role) {
                RoleAssigned::dispatch($user, $role);
            }
        }

        // Keep the visible user rank in step with the new leadership position if
        // the account is still below lieutenant rank.
        if ((int) ($user->rank_level ?? 0) < 2) {
            $user->setRank('lieutenant');
        }
        return $member->fresh();
    }

    /**
     * Demote a lieutenant back to the regular member role.
     *
     * The user loses lieutenant-specific access, regains the member role if
     * needed, and may also have their displayed rank lowered.
     */
    public function demoteLieutenant(Squadron $squadron, User $user): SquadronMember
    {
        $member = SquadronMember::where('user_id', $user->id)
            ->where('squadron_id', $squadron->id)
            ->where('role', SquadronRole::Lieutenant->value)
            ->firstOrFail();

        if ($member->role !== SquadronRole::Lieutenant->value) {
            throw ValidationException::withMessages([
                'role' => 'User is not a lieutenant.',
            ]);
        }

        $member->update([
            'role' => SquadronRole::Member->value,
        ]);

        // Remove every lieutenant role entry before reattaching the safer member
        // baseline role.
        $lieutenantRoleIds = Role::where('slug', 'lieutenant')->pluck('id')->all();
        if (! empty($lieutenantRoleIds)) {
            $revokedRoles = Role::whereIn('id', $lieutenantRoleIds)->get();
            $user->roles()->detach($lieutenantRoleIds);
            foreach ($revokedRoles as $role) {
                RoleRevoked::dispatch($user, $role);
            }
        }

        $user->unsetRelation('roles');

        $memberRoleId = Role::where('slug', 'member')->value('id');
        if ($memberRoleId) {
            $user->roles()->syncWithoutDetaching([$memberRoleId]);
            $role = Role::find($memberRoleId);
            if ($role) {
                RoleAssigned::dispatch($user, $role);
            }
        }

        // Only lower the visible rank if this user was exactly at lieutenant rank
        // because of the now-removed lieutenant assignment.
        if ((int) ($user->rank_level ?? 0) === 2 && $user->rank === 'lieutenant') {
            $user->setRank('member');
        }
        return $member->fresh();
    }
}
