<?php

namespace App\Domain\Squadrons;

use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    /**
     * Admin / director adds a member directly to a squadron.
     */
    public function adminAddMember(Squadron $squadron, int $userId): SquadronMember
    {
        $exists = SquadronMember::where('user_id', $userId)
            ->where('squadron_id', $squadron->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user_id' => 'User already in squadron.',
            ]);
        }

        return SquadronMember::create([
            'user_id'          => $userId,
            'squadron_id'      => $squadron->id,
            'membership_status'=> SquadronMember::STATUS_ACTIVE,
            'joined_at'        => now(),
        ]);
    }

    /**
     * Admin updates membership status.
     */
    public function adminUpdateStatus(Squadron $squadron, SquadronMember $member, string $status): SquadronMember
    {
        if ($member->squadron_id !== $squadron->id) {
            throw ValidationException::withMessages([
                'member' => 'Member does not belong to this squadron.',
            ]);
        }

        $member->update([
            'membership_status' => $status,
        ]);

        return $member;
    }

    /**
     * Admin removes a member (kick).
     */
    public function adminRemoveMember(Squadron $squadron, SquadronMember $member): void
    {
        if ($member->squadron_id !== $squadron->id) {
            throw ValidationException::withMessages([
                'member' => 'Member does not belong to this squadron.',
            ]);
        }

        $member->update([
            'left_at'           => now(),
            'membership_status' => SquadronMember::STATUS_PENDING,
        ]);

        $member->delete();
    }

    /**
     * User joins a squadron themselves.
     */
    public function userJoin(Squadron $squadron, User $user): SquadronMember
    {
        $already = SquadronMember::where('user_id', $user->id)->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'member' => 'Already in a squadron.',
            ]);
        }

        return SquadronMember::create([
            'user_id'          => $user->id,
            'squadron_id'      => $squadron->id,
            'membership_status'=> SquadronMember::STATUS_PENDING,
            'joined_at'        => now(),
        ]);
    }

    /**
     * User leaves their squadron.
     */
    public function userLeave(Squadron $squadron, User $user): void
    {
        $member = SquadronMember::where('user_id', $user->id)
            ->where('squadron_id', $squadron->id)
            ->first();

        if (!$member) {
            throw ValidationException::withMessages([
                'member' => 'Not a member of this squadron.',
            ]);
        }

        $member->update([
            'left_at' => now(),
        ]);

        $member->delete();
    }

    /**
     * Used by the web manage screen to update role + status.
     */
    public function updateMemberFromManage(
        Squadron $squadron,
        SquadronMember $member,
        ?string $role,
        string $status,
        User $actingUser
    ): SquadronMember {
        if ($member->squadron_id !== $squadron->id) {
            throw ValidationException::withMessages([
                'member' => 'Member does not belong to this squadron.',
            ]);
        }

        // Prevent leader from demoting themselves
        if ($member->user_id === $actingUser->id && $role !== SquadronMember::ROLE_LEADER) {
            throw ValidationException::withMessages([
                'role' => 'You cannot demote yourself.',
            ]);
        }

        $member->update([
            'role'              => $role === 'null' ? null : $role,
            'membership_status' => $status,
        ]);

        return $member->fresh();
    }

    /**
     * Used by the manage screen to remove a member.
     */
    public function removeMemberFromManage(
        Squadron $squadron,
        SquadronMember $member,
        User $actingUser
    ): void {
        if ($member->squadron_id !== $squadron->id) {
            throw ValidationException::withMessages([
                'member' => 'Member does not belong to this squadron.',
            ]);
        }

        // Leaders cannot remove themselves
        if ($member->user_id === $actingUser->id) {
            throw ValidationException::withMessages([
                'member' => 'You cannot remove yourself.',
            ]);
        }

        $member->delete();
    }

    /**
     * Promote a user to lieutenant, enforcing max 2.
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
            'role'              => SquadronMember::ROLE_LIEUTENANT,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
        ]);

        return $member->fresh();
    }
}
