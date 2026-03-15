<?php

namespace App\Domain\Squadrons;

use App\Models\Role;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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

        $ltCount = SquadronMember::where('squadron_id', $squadron->id)
            ->where('role', SquadronMember::ROLE_LIEUTENANT)
            ->count();

        if ($ltCount >= 2) {
            throw ValidationException::withMessages([
                'max_lt' => 'This squadron already has the maximum of two Lieutenants.',
            ]);
        }

        $member->update([
            'role' => SquadronMember::ROLE_LIEUTENANT,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
        ]);

        // Sync the reusable application role so permission checks outside the
        // squadron domain also see this promotion.
        $lieutenantRoleId = Role::where('slug', 'lieutenant')->value('id');
        if ($lieutenantRoleId) {
            $user->roles()->syncWithoutDetaching([$lieutenantRoleId]);
        }

        // Keep the visible user rank in step with the new leadership position if
        // the account is still below lieutenant rank.
        if ((int) ($user->rank_level ?? 0) < 2) {
            $user->setRank('lieutenant');
        }

        $this->flushUserAccessCache($user);

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
            ->where('role', SquadronMember::ROLE_LIEUTENANT)
            ->firstOrFail();

        if ($member->role !== SquadronMember::ROLE_LIEUTENANT) {
            throw ValidationException::withMessages([
                'role' => 'User is not a lieutenant.',
            ]);
        }

        $member->update([
            'role' => SquadronMember::ROLE_MEMBER,
        ]);

        // Remove every lieutenant role entry before reattaching the safer member
        // baseline role.
        $lieutenantRoleIds = Role::where('slug', 'lieutenant')->pluck('id')->all();
        if (! empty($lieutenantRoleIds)) {
            $user->roles()->detach($lieutenantRoleIds);
        }

        $user->unsetRelation('roles');

        $memberRoleId = Role::where('slug', 'member')->value('id');
        if ($memberRoleId) {
            $user->roles()->syncWithoutDetaching([$memberRoleId]);
        }

        // Only lower the visible rank if this user was exactly at lieutenant rank
        // because of the now-removed lieutenant assignment.
        if ((int) ($user->rank_level ?? 0) === 2 && $user->rank === 'lieutenant') {
            $user->setRank('member');
        }

        $this->flushUserAccessCache($user);

        return $member->fresh();
    }

    /**
     * Clear cached roles and permissions so authorization checks see the new
     * access state immediately after promotion or demotion.
     */
    protected function flushUserAccessCache(User $user): void
    {
        Cache::forget("user_roles_{$user->id}");
        Cache::forget("user_permissions_{$user->id}");
    }
}
