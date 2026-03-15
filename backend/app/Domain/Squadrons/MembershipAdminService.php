<?php

namespace App\Domain\Squadrons;

use App\Domain\AccessControl\AccessService;
use App\Models\Squadron;
use App\Models\SquadronMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Handles membership changes that come from admin screens and squadron management
 * screens.
 *
 * The rules here are intentionally stricter than the simple self-service flow
 * because leaders, lieutenants, and admins can all mutate other users.
 */
class MembershipAdminService
{
    public function __construct(
        protected AccessService $access
    ) {}

    /**
     * Add a user directly to the target squadron as an active member.
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
            'user_id' => $userId,
            'squadron_id' => $squadron->id,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
    }

    /**
     * Create a membership record directly from an admin panel action.
     */
    public function adminCreateMember(Squadron $squadron, int $userId): SquadronMember
    {
        return SquadronMember::create([
            'squadron_id' => $squadron->id,
            'user_id' => $userId,
            'membership_status' => SquadronMember::STATUS_ACTIVE,
            'role' => null,
            'joined_at' => now(),
        ]);
    }

    /**
     * Change only the membership status while confirming the member belongs to
     * the squadron being managed.
     */
    public function adminUpdateStatus(Squadron $squadron, SquadronMember $member, string $status): SquadronMember
    {
        $this->assertMemberBelongsToSquadron($squadron, $member);

        $member->update([
            'membership_status' => $status,
        ]);

        return $member;
    }

    /**
     * Update a member's role and status.
     */
    public function adminUpdateMember(SquadronMember $member, ?string $role, string $status): SquadronMember
    {
        $member->update([
            'role' => $this->normalizeRole($role),
            'membership_status' => $status,
        ]);

        return $member->fresh();
    }

    /**
     * Remove a member from the target squadron from an admin flow.
     */
    public function adminRemoveMember(Squadron $squadron, SquadronMember $member): void
    {
        $this->assertMemberBelongsToSquadron($squadron, $member);

        $member->update([
            'left_at' => now(),
            'membership_status' => SquadronMember::STATUS_PENDING,
        ]);

        $member->delete();
    }

    /**
     * Delete a membership record directly when the caller already resolved it.
     */
    public function adminDeleteMember(SquadronMember $member): void
    {
        $member->delete();
    }

    /**
     * Update a member from the normal squadron management screen.
     *
     * This route protects leaders from accidentally removing their own leadership
     * role through the UI.
     */
    public function updateMemberFromManage(
        Squadron $squadron,
        SquadronMember $member,
        ?string $role,
        string $status,
        User $actingUser
    ): SquadronMember {
        $this->assertMemberBelongsToSquadron($squadron, $member);

        // The acting user may edit other members, but they cannot demote themselves
        // out of leadership through the manage screen.
        if ($member->user_id === $actingUser->id && $role !== SquadronMember::ROLE_LEADER) {
            throw ValidationException::withMessages([
                'role' => 'You cannot demote yourself.',
            ]);
        }

        $member->update([
            'role' => $this->normalizeRole($role),
            'membership_status' => $status,
        ]);

        return $member->fresh();
    }

    /**
     * Remove a member from the manage screen with lieutenant-specific safety
     * rules applied.
     */
    public function removeMemberFromManage(
        Squadron $squadron,
        SquadronMember $member,
        User $actingUser
    ): void {
        $this->assertMemberBelongsToSquadron($squadron, $member);

        // Prevent leaders or lieutenants from removing their own membership from
        // the same UI they use to manage everyone else.
        if ($member->user_id === $actingUser->id) {
            throw ValidationException::withMessages([
                'member' => 'You cannot remove yourself.',
            ]);
        }

        // Lieutenants can help manage members, but they must never be able to
        // kick the current leader or a member flagged as leader.
        if (
            $this->access->isSquadronLieutenant($actingUser, $squadron)
            && (
                $member->user_id === $squadron->leader_id
                || $member->role === SquadronMember::ROLE_LEADER
            )
        ) {
            throw ValidationException::withMessages([
                'member' => 'Lieutenants cannot remove the squadron leader.',
            ]);
        }

        $member->delete();
    }

    /**
     * Fail fast when a controller passes a member record from a different squadron.
     */
    protected function assertMemberBelongsToSquadron(Squadron $squadron, SquadronMember $member): void
    {
        if ($member->squadron_id !== $squadron->id) {
            throw ValidationException::withMessages([
                'member' => 'Member does not belong to this squadron.',
            ]);
        }
    }

    /**
     * Convert the literal string "null" from some form payloads into a real null
     * so Eloquent stores the absence of a role consistently.
     */
    protected function normalizeRole(?string $role): ?string
    {
        return $role === 'null' ? null : $role;
    }
}
